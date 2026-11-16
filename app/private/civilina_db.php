<?php

declare(strict_types=1);

/*
 * Civilina data layer: one SQLite file outside public_html.
 *
 * - storage/civilina.sqlite holds users, contact requests, login attempts,
 *   page views (v2), articles (v3), the newsletter (v4),
 *   offers, leads and resource downloads (v5),
 *   the audit log and settings.
 * - The schema is versioned with PRAGMA user_version; version 1 imports the
 *   legacy JSON files (users.json, contact_requests.json) once. Those files
 *   are left untouched as an archive and are no longer written.
 * - Self-contained: the contact handler uses it without loading the auth layer.
 */

date_default_timezone_set('Africa/Algiers');

const CIVILINA_DB_VERSION = 5;

/** Axes used across the back-office (contacts, articles, later offers and prospects). */
const CIVILINA_AXES = [
    'engineering' => 'Engineering',
    'entreprise' => 'Entreprise',
    'digital' => 'Digital',
    'ia' => 'CIVILINA IA',
    'a_qualifier' => 'À qualifier',
];

const CIVILINA_CONTACT_STATUSES = [
    'new' => 'Nouvelle',
    'in_progress' => 'En cours',
    'done' => 'Traitée',
    'archived' => 'Archivée',
];

function civilina_db_private_dir(): string
{
    // Local dev router (app/tools/dev-router.php) points this to a git-ignored folder.
    $dir = getenv('CIVILINA_PRIVATE_DIR');
    return is_string($dir) && $dir !== '' ? $dir : __DIR__;
}

function civilina_db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dir = civilina_db_private_dir() . '/storage';
    if (!is_dir($dir)) {
        mkdir($dir, 0700, true);
    }

    $pdo = new PDO('sqlite:' . $dir . '/civilina.sqlite', null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA foreign_keys = ON');

    civilina_db_migrate($pdo);

    return $pdo;
}

function civilina_db_migrate(PDO $pdo): void
{
    $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($version >= CIVILINA_DB_VERSION) {
        return;
    }

    $pdo->exec('BEGIN IMMEDIATE');
    try {
        // Re-check inside the lock: another request may have migrated meanwhile.
        $version = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
        if ($version < 1) {
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS settings (
                    key TEXT PRIMARY KEY,
                    value TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS users (
                    id TEXT PRIMARY KEY,
                    email TEXT NOT NULL UNIQUE,
                    role TEXT NOT NULL DEFAULT 'visitor',
                    data TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS contacts (
                    id TEXT PRIMARY KEY,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    status TEXT NOT NULL DEFAULT 'new',
                    axis TEXT NOT NULL DEFAULT 'a_qualifier',
                    name TEXT NOT NULL,
                    company TEXT NOT NULL DEFAULT '',
                    position TEXT NOT NULL DEFAULT '',
                    email TEXT NOT NULL,
                    phone TEXT NOT NULL DEFAULT '',
                    need TEXT NOT NULL DEFAULT '',
                    message TEXT NOT NULL DEFAULT '',
                    note TEXT NOT NULL DEFAULT '',
                    ip_hash TEXT NOT NULL DEFAULT '',
                    user_agent TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS contacts_status ON contacts(status, created_at);
                CREATE TABLE IF NOT EXISTS login_attempts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email_hash TEXT NOT NULL,
                    ip_hash TEXT NOT NULL,
                    success INTEGER NOT NULL,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS login_attempts_email ON login_attempts(email_hash, created_at);
                CREATE INDEX IF NOT EXISTS login_attempts_ip ON login_attempts(ip_hash, created_at);
                CREATE TABLE IF NOT EXISTS audit_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    user_id TEXT NOT NULL DEFAULT '',
                    action TEXT NOT NULL,
                    detail TEXT NOT NULL DEFAULT '',
                    ip_hash TEXT NOT NULL DEFAULT ''
                );
                SQL);

            $salt = bin2hex(random_bytes(16));
            $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES (?, ?)')->execute(['hash_salt', $salt]);

            civilina_db_import_legacy($pdo);
            $pdo->exec('PRAGMA user_version = 1');
            $version = 1;
        }
        if ($version < 2) {
            // M1: anonymous page views (no cookie; visitor = daily-salted hash, IP never stored).
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS page_views (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    day TEXT NOT NULL,
                    path TEXT NOT NULL,
                    visitor TEXT NOT NULL,
                    source TEXT NOT NULL,
                    referrer_host TEXT NOT NULL DEFAULT '',
                    campaign TEXT NOT NULL DEFAULT '',
                    device TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS page_views_day ON page_views(day);
                CREATE INDEX IF NOT EXISTS page_views_path ON page_views(path, day);
                SQL);
            $pdo->exec('PRAGMA user_version = 2');
            $version = 2;
        }
        if ($version < 3) {
            // M2: Journal articles (content as JSON, body in the Civilina writing syntax).
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS articles (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    slug TEXT NOT NULL UNIQUE,
                    status TEXT NOT NULL DEFAULT 'draft',
                    publish_at TEXT NOT NULL DEFAULT '',
                    date_published TEXT NOT NULL DEFAULT '',
                    date_modified TEXT NOT NULL DEFAULT '',
                    data TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
                CREATE INDEX IF NOT EXISTS articles_status ON articles(status, date_published);
                SQL);
            $pdo->exec('PRAGMA user_version = 3');
            $version = 3;
        }
        if ($version < 4) {
            // M3: newsletter (double opt-in subscribers, campaigns, per-recipient sends, e-mail quota log).
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS subscribers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    email TEXT NOT NULL UNIQUE,
                    first_name TEXT NOT NULL DEFAULT '',
                    axes TEXT NOT NULL DEFAULT 'engineering,entreprise,digital',
                    status TEXT NOT NULL DEFAULT 'pending',
                    source TEXT NOT NULL DEFAULT '',
                    confirm_hash TEXT NOT NULL DEFAULT '',
                    confirm_sent_at TEXT NOT NULL DEFAULT '',
                    consent_at TEXT NOT NULL DEFAULT '',
                    confirmed_at TEXT NOT NULL DEFAULT '',
                    unsubscribed_at TEXT NOT NULL DEFAULT '',
                    ip_hash TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
                CREATE INDEX IF NOT EXISTS subscribers_status ON subscribers(status);
                CREATE TABLE IF NOT EXISTS campaigns (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    status TEXT NOT NULL DEFAULT 'draft',
                    subject TEXT NOT NULL DEFAULT '',
                    data TEXT NOT NULL,
                    scheduled_at TEXT NOT NULL DEFAULT '',
                    started_at TEXT NOT NULL DEFAULT '',
                    sent_at TEXT NOT NULL DEFAULT '',
                    recipients INTEGER NOT NULL DEFAULT 0,
                    stats TEXT NOT NULL DEFAULT '',
                    stats_at TEXT NOT NULL DEFAULT '',
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS campaign_sends (
                    campaign_id INTEGER NOT NULL,
                    subscriber_id INTEGER NOT NULL,
                    status TEXT NOT NULL DEFAULT 'queued',
                    message_id TEXT NOT NULL DEFAULT '',
                    error TEXT NOT NULL DEFAULT '',
                    sent_at TEXT NOT NULL DEFAULT '',
                    PRIMARY KEY (campaign_id, subscriber_id)
                );
                CREATE INDEX IF NOT EXISTS campaign_sends_status ON campaign_sends(status, campaign_id);
                CREATE TABLE IF NOT EXISTS email_log (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    day TEXT NOT NULL,
                    kind TEXT NOT NULL,
                    ok INTEGER NOT NULL,
                    detail TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS email_log_day ON email_log(day);
                CREATE TABLE IF NOT EXISTS form_hits (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    kind TEXT NOT NULL,
                    ip_hash TEXT NOT NULL,
                    created_at INTEGER NOT NULL
                );
                CREATE INDEX IF NOT EXISTS form_hits_ip ON form_hits(kind, ip_hash, created_at);
                SQL);
            $pdo->prepare('INSERT OR IGNORE INTO settings(key, value) VALUES (?, ?)')->execute(['newsletter_secret', bin2hex(random_bytes(32))]);
            $pdo->exec('PRAGMA user_version = 4');
            $version = 4;
        }
        if ($version < 5) {
            // M4: offers by axis, prospecting (leads + timeline), gated resources.
            $pdo->exec(<<<'SQL'
                CREATE TABLE IF NOT EXISTS offers (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    axis TEXT NOT NULL,
                    tier TEXT NOT NULL,
                    position INTEGER NOT NULL DEFAULT 0,
                    status TEXT NOT NULL DEFAULT 'draft',
                    data TEXT NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL
                );
                CREATE TABLE IF NOT EXISTS leads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    source TEXT NOT NULL DEFAULT 'manuel',
                    source_ref TEXT NOT NULL DEFAULT '',
                    axis TEXT NOT NULL DEFAULT 'a_qualifier',
                    stage TEXT NOT NULL DEFAULT 'nouveau',
                    name TEXT NOT NULL DEFAULT '',
                    company TEXT NOT NULL DEFAULT '',
                    position TEXT NOT NULL DEFAULT '',
                    email TEXT NOT NULL DEFAULT '',
                    phone TEXT NOT NULL DEFAULT '',
                    offer_id INTEGER NOT NULL DEFAULT 0,
                    value_da INTEGER NOT NULL DEFAULT 0,
                    next_action TEXT NOT NULL DEFAULT '',
                    next_action_at TEXT NOT NULL DEFAULT '',
                    notes TEXT NOT NULL DEFAULT '',
                    lost_reason TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS leads_stage ON leads(stage, updated_at);
                CREATE INDEX IF NOT EXISTS leads_email ON leads(email);
                CREATE INDEX IF NOT EXISTS leads_next ON leads(next_action_at);
                CREATE TABLE IF NOT EXISTS lead_events (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    lead_id INTEGER NOT NULL,
                    created_at TEXT NOT NULL,
                    user_id TEXT NOT NULL DEFAULT '',
                    type TEXT NOT NULL,
                    detail TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS lead_events_lead ON lead_events(lead_id, id);
                CREATE TABLE IF NOT EXISTS resource_downloads (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    created_at TEXT NOT NULL,
                    resource TEXT NOT NULL,
                    email TEXT NOT NULL,
                    first_name TEXT NOT NULL DEFAULT '',
                    company TEXT NOT NULL DEFAULT '',
                    lead_id INTEGER NOT NULL DEFAULT 0,
                    newsletter INTEGER NOT NULL DEFAULT 0,
                    ip_hash TEXT NOT NULL DEFAULT '',
                    downloads INTEGER NOT NULL DEFAULT 0,
                    last_download_at TEXT NOT NULL DEFAULT ''
                );
                CREATE INDEX IF NOT EXISTS resource_downloads_res ON resource_downloads(resource, created_at);
                SQL);
            // Existing contact requests become leads (archived ones are left out).
            $pdo->exec(<<<'SQL'
                INSERT INTO leads(created_at, updated_at, source, source_ref, axis, stage, name, company, position, email, phone, notes)
                SELECT created_at, updated_at, 'contact', id, axis,
                       CASE status WHEN 'new' THEN 'nouveau' ELSE 'qualifie' END,
                       name, company, position, email, phone, 'Besoin : ' || need
                FROM contacts WHERE status != 'archived'
                SQL);
            $pdo->exec("INSERT INTO lead_events(lead_id, created_at, type, detail) SELECT id, created_at, 'creation', 'Demande de contact (reprise)' FROM leads WHERE source = 'contact'");
            $pdo->exec('PRAGMA user_version = 5');
        }
        $pdo->exec('COMMIT');
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function civilina_db_read_legacy_json(string $file): array
{
    $path = civilina_db_private_dir() . '/' . $file;
    if (!is_file($path)) {
        return [];
    }
    $content = (string) file_get_contents($path);
    $content = preg_replace('/^\xEF\xBB\xBF/', '', $content) ?? $content;
    $data = json_decode($content, true);
    return is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
}

function civilina_db_import_legacy(PDO $pdo): void
{
    $now = gmdate('c');

    $insertUser = $pdo->prepare('INSERT OR IGNORE INTO users(id, email, role, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)');
    foreach (civilina_db_read_legacy_json('users.json') as $user) {
        $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
        $id = (string) ($user['id'] ?? '');
        if ($email === '' || $id === '') {
            continue;
        }
        $user['email'] = $email;
        $insertUser->execute([
            $id,
            $email,
            (string) ($user['role'] ?? 'visitor'),
            json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            (string) ($user['created_at'] ?? $now),
            (string) ($user['updated_at'] ?? $now),
        ]);
    }

    foreach (civilina_db_read_legacy_json('contact_requests.json') as $request) {
        if (!isset($request['id'], $request['email'])) {
            continue;
        }
        civilina_contact_insert($request, $pdo);
    }
}

/** Salted hash for IP addresses and e-mails used in counters: no raw value is stored. */
function civilina_hash(string $value, ?PDO $pdo = null): string
{
    static $salt = null;
    if ($salt === null) {
        $pdo = $pdo ?? civilina_db();
        $salt = (string) $pdo->query("SELECT value FROM settings WHERE key = 'hash_salt'")->fetchColumn();
    }
    return hash('sha256', $salt . '|' . $value);
}

function civilina_client_ip(): string
{
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

/* ---------- Contacts ---------- */

/** Maps the "need" chosen in the contact form to a business axis. */
function civilina_axis_for_need(string $need): string
{
    $map = [
        'Management de projet' => 'engineering',
        'Structuration processus' => 'entreprise',
        'Qualité ISO 9001' => 'entreprise',
        'QHSE' => 'entreprise',
        'Audit' => 'entreprise',
        'Supply Chain' => 'entreprise',
        'Tableaux de bord' => 'entreprise',
        'Accompagnement mensuel' => 'entreprise',
        'Plateforme Laravel métier' => 'digital',
    ];
    return $map[$need] ?? 'a_qualifier';
}

function civilina_contact_insert(array $request, ?PDO $pdo = null): void
{
    $pdo = $pdo ?? civilina_db();
    $created = (string) ($request['created_at'] ?? date('c'));
    $need = (string) ($request['need'] ?? '');
    $ip = (string) ($request['ip'] ?? '');

    $insert = $pdo->prepare(
        'INSERT OR IGNORE INTO contacts(id, created_at, updated_at, status, axis, name, company, position, email, phone, need, message, ip_hash, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $insert->execute([
        (string) $request['id'],
        $created,
        $created,
        array_key_exists((string) ($request['status'] ?? ''), CIVILINA_CONTACT_STATUSES) ? (string) $request['status'] : 'new',
        (string) ($request['axis'] ?? civilina_axis_for_need($need)),
        (string) ($request['name'] ?? ''),
        (string) ($request['company'] ?? ''),
        (string) ($request['position'] ?? ''),
        (string) ($request['email'] ?? ''),
        (string) ($request['phone'] ?? ''),
        $need,
        (string) ($request['message'] ?? ''),
        $ip !== '' ? civilina_hash($ip, $pdo) : '',
        mb_substr((string) ($request['user_agent'] ?? ''), 0, 500),
    ]);

    // M4: every new request also feeds the prospecting pipeline (once the leads table exists).
    if ($insert->rowCount() > 0 && (int) $pdo->query('PRAGMA user_version')->fetchColumn() >= 5) {
        $isQuote = (int) ($request['offer_id'] ?? 0) > 0;
        civilina_lead_upsert([
            'source' => $isQuote ? 'devis' : 'contact', 'source_ref' => (string) $request['id'],
            'axis' => (string) ($request['axis'] ?? civilina_axis_for_need($need)),
            'name' => (string) ($request['name'] ?? ''), 'company' => (string) ($request['company'] ?? ''),
            'position' => (string) ($request['position'] ?? ''), 'email' => (string) ($request['email'] ?? ''),
            'phone' => (string) ($request['phone'] ?? ''), 'offer_id' => (int) ($request['offer_id'] ?? 0),
        ], $isQuote ? 'devis' : 'contact', ($isQuote ? 'Demande de devis : ' : 'Demande de contact : ') . $need, $pdo);
    }
}

const CIVILINA_LEAD_STAGES = [
    'nouveau' => 'Nouveau',
    'qualifie' => 'Qualifié',
    'proposition' => 'Proposition envoyée',
    'gagne' => 'Gagné',
    'perdu' => 'Perdu',
];

/**
 * Creates a lead, or adds the event to the open lead of the same e-mail address
 * (a person who downloads a resource then asks for a quote stays one prospect).
 * @return int lead id
 */
function civilina_lead_upsert(array $data, string $eventType, string $detail, ?PDO $pdo = null, string $userId = ''): int
{
    $pdo = $pdo ?? civilina_db();
    $now = date('c');
    $email = mb_strtolower(trim((string) ($data['email'] ?? '')));
    $lead = null;
    if ($email !== '') {
        $find = $pdo->prepare("SELECT * FROM leads WHERE email = ? AND stage NOT IN ('gagne', 'perdu') ORDER BY id DESC LIMIT 1");
        $find->execute([$email]);
        $lead = $find->fetch() ?: null;
    }
    if ($lead) {
        $id = (int) $lead['id'];
        // Fill only the empty fields; a quote request moves a new lead to "qualified".
        $fill = [];
        foreach (['name', 'company', 'position', 'phone'] as $field) {
            if ((string) $lead[$field] === '' && trim((string) ($data[$field] ?? '')) !== '') {
                $fill[$field] = trim((string) $data[$field]);
            }
        }
        if ($lead['axis'] === 'a_qualifier' && ($data['axis'] ?? 'a_qualifier') !== 'a_qualifier') {
            $fill['axis'] = (string) $data['axis'];
        }
        if ((int) ($data['offer_id'] ?? 0) > 0) {
            $fill['offer_id'] = (int) $data['offer_id'];
        }
        if ($eventType === 'devis' && $lead['stage'] === 'nouveau') {
            $fill['stage'] = 'qualifie';
        }
        $fill['updated_at'] = $now;
        $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($fill)));
        $pdo->prepare('UPDATE leads SET ' . $set . ' WHERE id = ?')->execute([...array_values($fill), $id]);
    } else {
        $pdo->prepare('INSERT INTO leads(created_at, updated_at, source, source_ref, axis, stage, name, company, position, email, phone, offer_id, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $now, $now, (string) ($data['source'] ?? 'manuel'), (string) ($data['source_ref'] ?? ''),
                (string) ($data['axis'] ?? 'a_qualifier'), (string) ($data['stage'] ?? 'nouveau'),
                mb_substr(trim((string) ($data['name'] ?? '')), 0, 160), mb_substr(trim((string) ($data['company'] ?? '')), 0, 160),
                mb_substr(trim((string) ($data['position'] ?? '')), 0, 160), $email, mb_substr(trim((string) ($data['phone'] ?? '')), 0, 80),
                (int) ($data['offer_id'] ?? 0), mb_substr(trim((string) ($data['notes'] ?? '')), 0, 4000),
            ]);
        $id = (int) $pdo->lastInsertId();
        $eventType = $eventType === '' ? 'creation' : $eventType;
    }
    $pdo->prepare('INSERT INTO lead_events(lead_id, created_at, user_id, type, detail) VALUES (?, ?, ?, ?, ?)')
        ->execute([$id, $now, $userId, $eventType, mb_substr($detail, 0, 1000)]);
    return $id;
}

/* ---------- Audit log ---------- */

function civilina_audit(string $action, string $detail = '', string $userId = ''): void
{
    try {
        civilina_db()->prepare('INSERT INTO audit_log(created_at, user_id, action, detail, ip_hash) VALUES (?, ?, ?, ?, ?)')
            ->execute([date('c'), $userId, $action, mb_substr($detail, 0, 500), civilina_hash(civilina_client_ip())]);
    } catch (Throwable $e) {
        error_log('civilina_audit: ' . $e->getMessage());
    }
}

/* ---------- Login throttling ---------- */

const CIVILINA_LOGIN_WINDOW = 900;          // 15 minutes
const CIVILINA_LOGIN_MAX_PER_EMAIL = 5;
const CIVILINA_LOGIN_MAX_PER_IP = 20;

function civilina_login_blocked(string $email): bool
{
    $since = time() - CIVILINA_LOGIN_WINDOW;
    $db = civilina_db();

    $byEmail = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_hash = ? AND success = 0 AND created_at > ?');
    $byEmail->execute([civilina_hash(mb_strtolower(trim($email))), $since]);
    if ((int) $byEmail->fetchColumn() >= CIVILINA_LOGIN_MAX_PER_EMAIL) {
        return true;
    }

    $byIp = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_hash = ? AND success = 0 AND created_at > ?');
    $byIp->execute([civilina_hash(civilina_client_ip()), $since]);
    return (int) $byIp->fetchColumn() >= CIVILINA_LOGIN_MAX_PER_IP;
}

function civilina_record_login_attempt(string $email, bool $success): void
{
    $db = civilina_db();
    $db->prepare('INSERT INTO login_attempts(email_hash, ip_hash, success, created_at) VALUES (?, ?, ?, ?)')
        ->execute([civilina_hash(mb_strtolower(trim($email))), civilina_hash(civilina_client_ip()), $success ? 1 : 0, time()]);

    if ($success) {
        // A successful login clears the failures for this account.
        $db->prepare('DELETE FROM login_attempts WHERE email_hash = ? AND success = 0')
            ->execute([civilina_hash(mb_strtolower(trim($email)))]);
    }

    if (random_int(1, 50) === 1) {
        $db->prepare('DELETE FROM login_attempts WHERE created_at < ?')->execute([time() - 30 * 86400]);
    }
}
