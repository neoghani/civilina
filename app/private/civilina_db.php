<?php

declare(strict_types=1);

/*
 * Civilina data layer: one SQLite file outside public_html.
 *
 * - storage/civilina.sqlite holds users, contact requests, login attempts,
 *   the audit log and settings.
 * - The schema is versioned with PRAGMA user_version; version 1 imports the
 *   legacy JSON files (users.json, contact_requests.json) once. Those files
 *   are left untouched as an archive and are no longer written.
 * - Self-contained: the contact handler uses it without loading the auth layer.
 */

date_default_timezone_set('Africa/Algiers');

const CIVILINA_DB_VERSION = 1;

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

    $pdo->prepare(
        'INSERT OR IGNORE INTO contacts(id, created_at, updated_at, status, axis, name, company, position, email, phone, need, message, ip_hash, user_agent)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    )->execute([
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
