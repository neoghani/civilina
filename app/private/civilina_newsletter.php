<?php

declare(strict_types=1);

/*
 * Newsletter (milestone M3).
 *
 * - Subscribers (schema v4) register on the site and confirm by e-mail (double
 *   opt-in). Only confirmed subscribers receive campaigns. Each e-mail carries a
 *   personal, signed link to change the topics (axes) or unsubscribe, plus the
 *   List-Unsubscribe headers (one click in Gmail, Outlook, Apple Mail).
 * - E-mails go through the Brevo transactional API, one call per recipient.
 *   The API key is entered by the administrator in the back-office and stored
 *   in the private database; it is never displayed again nor written to logs.
 * - A campaign is a snapshot of recipients (campaign_sends) processed in small
 *   batches under a lock, within the daily quota of the Brevo plan: on the
 *   campaign page, by the back-office and by the page-view collector (lazy
 *   processing, as for scheduled articles). An interrupted batch never sends
 *   the same e-mail twice.
 * - Local tests: when CIVILINA_MAIL_OUTBOX is set (dev router), e-mails are
 *   written as JSON files in that folder instead of being sent.
 */

require_once __DIR__ . '/civilina_articles.php';

/** Topics offered to subscribers: the three axes. */
const CIVILINA_NL_AXES = [
    'engineering' => 'Engineering',
    'entreprise' => 'Entreprise',
    'digital' => 'Digital',
];

const CIVILINA_NL_STATUSES = [
    'pending' => 'En attente de confirmation',
    'confirmed' => 'Confirmé',
    'unsubscribed' => 'Désinscrit',
    'blocked' => 'Bloqué (rebond ou plainte)',
];

const CIVILINA_CAMPAIGN_STATUSES = [
    'draft' => 'Brouillon',
    'scheduled' => 'Programmée',
    'sending' => 'Envoi en cours',
    'sent' => 'Envoyée',
];

const CIVILINA_NL_CONFIRM_TTL = 7 * 86400;
const CIVILINA_NL_RESEND_DELAY = 600;
const CIVILINA_NL_DEFAULT_DAILY_LIMIT = 280;
const CIVILINA_BREVO_API = 'https://api.brevo.com/v3';

/* ---------- Settings ---------- */

function civilina_setting(string $key, string $default = ''): string
{
    $statement = civilina_db()->prepare('SELECT value FROM settings WHERE key = ?');
    $statement->execute([$key]);
    $value = $statement->fetchColumn();
    return $value === false ? $default : (string) $value;
}

function civilina_setting_set(string $key, string $value): void
{
    civilina_db()->prepare('INSERT INTO settings(key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value')
        ->execute([$key, $value]);
}

function civilina_nl_config(): array
{
    return [
        'has_key' => civilina_setting('nl_brevo_key') !== '',
        'key_hint' => civilina_nl_key_hint(),
        'sender_name' => civilina_setting('nl_sender_name', 'Civilina'),
        'sender_email' => civilina_setting('nl_sender_email'),
        'reply_to' => civilina_setting('nl_reply_to'),
        'legal' => civilina_setting('nl_legal', 'Civilina · civilina.com'),
        'daily_limit' => max(1, (int) civilina_setting('nl_daily_limit', (string) CIVILINA_NL_DEFAULT_DAILY_LIMIT)),
    ];
}

/** Last 4 characters of the stored key, for the settings screen. */
function civilina_nl_key_hint(): string
{
    $key = civilina_setting('nl_brevo_key');
    return $key === '' ? '' : '…' . substr($key, -4);
}

function civilina_nl_outbox(): string
{
    $dir = getenv('CIVILINA_MAIL_OUTBOX');
    return is_string($dir) ? $dir : '';
}

/** E-mails can be sent: a key (or the local outbox) and a sender address. */
function civilina_nl_ready(): bool
{
    return (civilina_nl_outbox() !== '' || civilina_setting('nl_brevo_key') !== '') && civilina_setting('nl_sender_email') !== '';
}

/** Absolute site URL for links in e-mails (the local preview keeps its own host). */
function civilina_site_url(): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? '');
    if (preg_match('~^(localhost|127\.0\.0\.1)(:\d+)?$~', $host)) {
        return 'http://' . $host;
    }
    return 'https://civilina.com';
}

/* ---------- Brevo and sending ---------- */

/** @return array{0: int, 1: mixed, 2: string} HTTP status, decoded body, error message ('' if none) */
function civilina_brevo_request(string $method, string $path, ?array $body = null, ?string $key = null): array
{
    $key = $key ?? civilina_setting('nl_brevo_key');
    if ($key === '') {
        return [0, null, 'Clé d\'API Brevo non renseignée.'];
    }
    $headers = ['accept: application/json', 'api-key: ' . $key];
    $payload = null;
    if ($body !== null) {
        $payload = (string) json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $headers[] = 'content-type: application/json';
    }
    $url = CIVILINA_BREVO_API . $path;
    $error = '';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($payload !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        }
        $response = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        if ($response === false) {
            $error = 'Connexion à Brevo impossible (' . curl_error($ch) . ').';
        }
    } else {
        $context = stream_context_create(['http' => [
            'method' => $method, 'header' => implode("\r\n", $headers), 'content' => $payload ?? '',
            'timeout' => 15, 'ignore_errors' => true,
        ]]);
        $response = @file_get_contents($url, false, $context);
        $status = 0;
        foreach ($http_response_header ?? [] as $line) {
            if (preg_match('~^HTTP/\S+\s+(\d{3})~', $line, $m)) {
                $status = (int) $m[1];
            }
        }
        if ($response === false) {
            $error = 'Connexion à Brevo impossible.';
        }
    }
    $data = is_string($response) && $response !== '' ? json_decode($response, true) : null;
    if ($error === '' && ($status < 200 || $status >= 300)) {
        $message = is_array($data) ? (string) ($data['message'] ?? '') : '';
        $error = 'Brevo a refusé la demande (HTTP ' . $status . ($message !== '' ? ' : ' . $message : '') . ').';
    }
    return [$status, $data, $error];
}

function civilina_mail_log(string $kind, bool $ok, string $detail = ''): void
{
    civilina_db()->prepare('INSERT INTO email_log(created_at, day, kind, ok, detail) VALUES (?, ?, ?, ?, ?)')
        ->execute([date('c'), date('Y-m-d'), $kind, $ok ? 1 : 0, mb_substr($detail, 0, 300)]);
}

function civilina_mail_sent_today(): int
{
    $statement = civilina_db()->prepare('SELECT COUNT(*) FROM email_log WHERE day = ? AND ok = 1');
    $statement->execute([date('Y-m-d')]);
    return (int) $statement->fetchColumn();
}

function civilina_mail_quota_left(): int
{
    return max(0, civilina_nl_config()['daily_limit'] - civilina_mail_sent_today());
}

/**
 * Sends one e-mail. $options: kind (log label), headers, tags.
 * @return array{ok: bool, id: string, error: string, status: int}
 */
function civilina_mail_send(string $email, string $name, string $subject, string $html, string $text, array $options = []): array
{
    $config = civilina_nl_config();
    $to = ['email' => $email];
    if ($name !== '') {
        $to['name'] = $name;
    }
    $payload = [
        'sender' => ['name' => $config['sender_name'], 'email' => $config['sender_email']],
        'to' => [$to],
        'subject' => $subject,
        'htmlContent' => $html,
        'textContent' => $text,
    ];
    if ($config['reply_to'] !== '') {
        $payload['replyTo'] = ['email' => $config['reply_to']];
    }
    if (!empty($options['headers'])) {
        $payload['headers'] = $options['headers'];
    }
    if (!empty($options['tags'])) {
        $payload['tags'] = array_values($options['tags']);
    }
    $kind = (string) ($options['kind'] ?? 'mail');

    $outbox = civilina_nl_outbox();
    if ($outbox !== '') {
        if (!is_dir($outbox)) {
            mkdir($outbox, 0700, true);
        }
        $id = 'local-' . date('Ymd-His') . '-' . substr(sprintf('%.6F', microtime(true)), -6) . '-' . bin2hex(random_bytes(2));
        file_put_contents($outbox . '/' . $id . '.json', json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        civilina_mail_log($kind, true, $id);
        return ['ok' => true, 'id' => $id, 'error' => '', 'status' => 201];
    }

    [$status, $data, $error] = civilina_brevo_request('POST', '/smtp/email', $payload);
    $ok = $error === '';
    civilina_mail_log($kind, $ok, $ok ? '' : $error);
    return ['ok' => $ok, 'id' => $ok && is_array($data) ? (string) ($data['messageId'] ?? '') : '', 'error' => $error, 'status' => $status];
}

/* ---------- E-mail layout ---------- */

function civilina_mail_e(string $s): string
{
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** Inline Civilina markup (bold, italics, links) for e-mails: absolute links, inline link colour. */
function civilina_mail_inline(string $markup): string
{
    $html = civilina_md_inline($markup);
    $html = (string) preg_replace('~href="/~', 'href="' . civilina_site_url() . '/', $html);
    return str_replace('<a href=', '<a style="color:#003b8c" href=', $html);
}

/** Paragraphs separated by a blank line. */
function civilina_mail_paragraphs(string $markup): string
{
    $out = '';
    foreach (preg_split('~\R\s*\R~u', trim($markup)) ?: [] as $paragraph) {
        $paragraph = trim((string) preg_replace('~\s*\R\s*~u', ' ', $paragraph));
        if ($paragraph !== '') {
            $out .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#252a31">' . civilina_mail_inline($paragraph) . '</p>';
        }
    }
    return $out;
}

function civilina_mail_text(string $markup): string
{
    return trim(html_entity_decode(strip_tags(civilina_md_inline((string) preg_replace('~\[([^\]]+)\]\(([^)]+)\)~', '$1 ($2)', $markup))), ENT_QUOTES, 'UTF-8'));
}

/** Table-based layout that renders in Outlook, Gmail and Apple Mail. */
function civilina_mail_shell(string $preheader, string $inner, string $footer): string
{
    $site = civilina_site_url();
    $pre = civilina_mail_e($preheader);
    return <<<HTML
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="color-scheme" content="light">
<title>Civilina</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fa">
<div style="display:none;max-height:0;overflow:hidden;opacity:0">{$pre}</div>
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fa">
<tr><td align="center" style="padding:24px 12px">
<table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#ffffff;border:1px solid #dce3eb;border-radius:16px;font-family:Inter,Arial,Helvetica,sans-serif">
<tr><td style="padding:24px 28px 8px">
<a href="{$site}/" style="text-decoration:none;color:#102033;font-weight:800;font-size:18px;letter-spacing:2px"><img src="https://civilina.com/assets/brand/civilina-logo.png" width="32" height="32" alt="" style="vertical-align:middle;border:0;margin-right:8px">CIVILINA</a>
</td></tr>
<tr><td style="padding:8px 28px 12px">
{$inner}
</td></tr>
<tr><td style="padding:18px 28px 24px;border-top:1px solid #dce3eb;font-size:13px;line-height:1.55;color:#5d6673">
{$footer}
</td></tr>
</table>
</td></tr>
</table>
</body>
</html>
HTML;
}

/* ---------- Subscribers ---------- */

function civilina_nl_normalize_email(string $email): string
{
    return mb_strtolower(trim($email));
}

function civilina_nl_valid_email(string $email): bool
{
    return $email !== '' && strlen($email) <= 180 && filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

/** Keeps the known axes; nothing chosen means all three. */
function civilina_nl_clean_axes(mixed $input): array
{
    $input = is_array($input) ? $input : explode(',', (string) $input);
    $axes = array_values(array_intersect(array_keys(CIVILINA_NL_AXES), array_map('strval', $input)));
    return $axes !== [] ? $axes : array_keys(CIVILINA_NL_AXES);
}

function civilina_nl_axes_label(string $axes): string
{
    return implode(', ', array_map(static fn (string $a): string => CIVILINA_NL_AXES[$a], civilina_nl_clean_axes($axes)));
}

function civilina_nl_find(int $id): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM subscribers WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function civilina_nl_find_by_email(string $email): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM subscribers WHERE email = ?');
    $statement->execute([civilina_nl_normalize_email($email)]);
    return $statement->fetch() ?: null;
}

/** Signed personal link token "<id>.<signature>" (preferences and unsubscribe). */
function civilina_nl_manage_token(int $id): string
{
    $secret = civilina_setting('newsletter_secret');
    if (strlen($secret) < 32) {
        throw new RuntimeException('Secret de la newsletter manquant.');
    }
    return $id . '.' . substr(hash_hmac('sha256', 'nl-manage|' . $id, $secret), 0, 32);
}

function civilina_nl_from_manage_token(string $token): ?array
{
    if (!preg_match('~^(\d{1,10})\.([a-f0-9]{32})$~', $token, $m)) {
        return null;
    }
    if (!hash_equals(civilina_nl_manage_token((int) $m[1]), $token)) {
        return null;
    }
    return civilina_nl_find((int) $m[1]);
}

function civilina_nl_manage_url(int $id): string
{
    return civilina_site_url() . '/newsletter/?u=' . civilina_nl_manage_token($id);
}

/**
 * Registration from a public form. Never reveals whether the address is known.
 * @return string sent | queued | invalid
 */
function civilina_nl_subscribe(string $email, string $firstName, mixed $axes, string $source): string
{
    $email = civilina_nl_normalize_email($email);
    if (!civilina_nl_valid_email($email)) {
        return 'invalid';
    }
    $firstName = mb_substr(trim((string) preg_replace('~\s+~u', ' ', $firstName)), 0, 80);
    $source = mb_substr((string) preg_replace('~[^a-z0-9:/_-]~', '', mb_strtolower($source)), 0, 120);
    $axesValue = implode(',', civilina_nl_clean_axes($axes));
    $now = date('c');
    $db = civilina_db();
    $row = civilina_nl_find_by_email($email);

    // A confirmed or blocked address is left as it is: an anonymous form must not
    // change someone's preferences, and blocked addresses are never mailed again.
    if ($row && in_array($row['status'], ['confirmed', 'blocked'], true)) {
        return civilina_nl_ready() ? 'sent' : 'queued';
    }
    // Same address again within a few minutes: no second e-mail.
    if ($row && $row['status'] === 'pending' && $row['confirm_sent_at'] !== '' && strtotime($row['confirm_sent_at']) > time() - CIVILINA_NL_RESEND_DELAY) {
        return 'sent';
    }

    if ($row) {
        $db->prepare("UPDATE subscribers SET status = 'pending', first_name = ?, axes = ?, source = ?, consent_at = ?, ip_hash = ?, confirm_sent_at = '', updated_at = ? WHERE id = ?")
            ->execute([$firstName, $axesValue, $source, $now, civilina_hash(civilina_client_ip()), $now, $row['id']]);
        $id = (int) $row['id'];
    } else {
        $db->prepare("INSERT INTO subscribers(email, first_name, axes, status, source, consent_at, ip_hash, created_at, updated_at) VALUES (?, ?, ?, 'pending', ?, ?, ?, ?, ?)")
            ->execute([$email, $firstName, $axesValue, $source, $now, civilina_hash(civilina_client_ip()), $now, $now]);
        $id = (int) $db->lastInsertId();
    }

    if (civilina_nl_ready() && civilina_mail_quota_left() > 0 && civilina_nl_send_confirmation($id)) {
        return 'sent';
    }
    return 'queued';
}

/** Sends (or re-sends) the confirmation e-mail with a fresh single-use link. */
function civilina_nl_send_confirmation(int $id): bool
{
    $s = civilina_nl_find($id);
    if (!$s || $s['status'] !== 'pending') {
        return false;
    }
    $token = bin2hex(random_bytes(24));
    $url = civilina_site_url() . '/newsletter/?c=' . $token;
    $hello = $s['first_name'] !== '' ? 'Bonjour ' . $s['first_name'] . ',' : 'Bonjour,';
    $axes = civilina_nl_axes_label($s['axes']);

    $inner = '<h1 style="margin:8px 0 14px;font-size:24px;line-height:1.3;color:#102033">Confirmez votre inscription</h1>'
        . '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#252a31">' . civilina_mail_e($hello) . '</p>'
        . '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#252a31">Vous avez demandé à recevoir la newsletter Civilina (' . civilina_mail_e($axes) . '). Cliquez sur le bouton pour confirmer votre adresse.</p>'
        . '<p style="margin:22px 0"><a href="' . civilina_mail_e($url) . '" style="display:inline-block;background:#003b8c;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:13px 22px;border-radius:999px">Confirmer mon inscription</a></p>'
        . '<p style="margin:0 0 14px;font-size:14px;line-height:1.6;color:#5d6673">Ce lien est valable 7 jours. Si vous n\'êtes pas à l\'origine de cette demande, ignorez cet e-mail : vous ne serez pas inscrit.</p>';
    $footer = civilina_mail_e(civilina_nl_config()['legal']);
    $html = civilina_mail_shell('Un clic pour confirmer votre inscription à la newsletter Civilina.', $inner, $footer);
    $text = $hello . "\n\nVous avez demandé à recevoir la newsletter Civilina (" . $axes . ").\nConfirmez votre adresse en ouvrant ce lien :\n" . $url
        . "\n\nCe lien est valable 7 jours. Si vous n'êtes pas à l'origine de cette demande, ignorez cet e-mail : vous ne serez pas inscrit.\n\n" . civilina_nl_config()['legal'];

    $result = civilina_mail_send($s['email'], $s['first_name'], 'Confirmez votre inscription à la newsletter Civilina', $html, $text, ['kind' => 'confirmation', 'tags' => ['civilina-confirmation']]);
    if ($result['ok']) {
        civilina_db()->prepare('UPDATE subscribers SET confirm_hash = ?, confirm_sent_at = ?, updated_at = ? WHERE id = ?')
            ->execute([hash('sha256', $token), date('c'), date('c'), $id]);
    }
    return $result['ok'];
}

/** Checks a confirmation link without using it. @return array{state: string, subscriber: ?array} ready | already | expired | unknown */
function civilina_nl_confirm_check(string $token): array
{
    if (!preg_match('~^[a-f0-9]{48}$~', $token)) {
        return ['state' => 'unknown', 'subscriber' => null];
    }
    $statement = civilina_db()->prepare('SELECT * FROM subscribers WHERE confirm_hash = ?');
    $statement->execute([hash('sha256', $token)]);
    $s = $statement->fetch();
    if (!$s) {
        return ['state' => 'unknown', 'subscriber' => null];
    }
    if ($s['status'] === 'confirmed') {
        return ['state' => 'already', 'subscriber' => $s];
    }
    if ($s['status'] !== 'pending' || strtotime($s['confirm_sent_at']) < time() - CIVILINA_NL_CONFIRM_TTL) {
        return ['state' => 'expired', 'subscriber' => $s];
    }
    return ['state' => 'ready', 'subscriber' => $s];
}

/** @return array{state: string, subscriber: ?array} state: confirmed | already | expired | unknown */
function civilina_nl_confirm(string $token): array
{
    $check = civilina_nl_confirm_check($token);
    if ($check['state'] !== 'ready') {
        return $check;
    }
    $s = $check['subscriber'];
    $now = date('c');
    civilina_db()->prepare("UPDATE subscribers SET status = 'confirmed', confirmed_at = ?, unsubscribed_at = '', updated_at = ? WHERE id = ?")
        ->execute([$now, $now, $s['id']]);
    civilina_audit('newsletter.confirmed', '#' . $s['id']);
    return ['state' => 'confirmed', 'subscriber' => civilina_nl_find((int) $s['id'])];
}

function civilina_nl_unsubscribe(int $id, string $how): void
{
    $now = date('c');
    civilina_db()->prepare("UPDATE subscribers SET status = 'unsubscribed', unsubscribed_at = ?, updated_at = ? WHERE id = ? AND status IN ('pending', 'confirmed')")
        ->execute([$now, $now, $id]);
    civilina_audit('newsletter.unsubscribed', '#' . $id . ' (' . $how . ')');
}

function civilina_nl_set_axes(int $id, mixed $axes): void
{
    civilina_db()->prepare('UPDATE subscribers SET axes = ?, updated_at = ? WHERE id = ?')
        ->execute([implode(',', civilina_nl_clean_axes($axes)), date('c'), $id]);
}

/** Counts by status and, for confirmed subscribers, by axis. */
function civilina_nl_counts(): array
{
    $db = civilina_db();
    $byStatus = $db->query('SELECT status, COUNT(*) FROM subscribers GROUP BY status')->fetchAll(PDO::FETCH_KEY_PAIR);
    $byAxis = array_fill_keys(array_keys(CIVILINA_NL_AXES), 0);
    foreach ($db->query("SELECT axes FROM subscribers WHERE status = 'confirmed'")->fetchAll(PDO::FETCH_COLUMN) as $axes) {
        foreach (civilina_nl_clean_axes((string) $axes) as $axis) {
            $byAxis[$axis]++;
        }
    }
    $since = date('c', strtotime('-30 days'));
    $recent = $db->prepare("SELECT COUNT(*) FROM subscribers WHERE status = 'confirmed' AND confirmed_at >= ?");
    $recent->execute([$since]);
    return [
        'status' => array_map('intval', $byStatus) + array_fill_keys(array_keys(CIVILINA_NL_STATUSES), 0),
        'axes' => $byAxis,
        'confirmed_30d' => (int) $recent->fetchColumn(),
    ];
}

/* ---------- Campaigns ---------- */

function civilina_campaign_defaults(): array
{
    return [
        'subject' => '', 'preheader' => '', 'title' => '', 'intro' => '', 'articles' => [],
        'axes' => array_keys(CIVILINA_NL_AXES), 'outro' => '', 'test_email' => '',
    ];
}

function civilina_campaign_from_row(array $row): array
{
    $data = json_decode((string) $row['data'], true);
    $c = array_replace(civilina_campaign_defaults(), is_array($data) ? $data : []);
    $c['subject'] = (string) $row['subject'];
    $stats = json_decode((string) $row['stats'], true);
    return $c + [
        'id' => (int) $row['id'], 'status' => (string) $row['status'], 'scheduled_at' => (string) $row['scheduled_at'],
        'started_at' => (string) $row['started_at'], 'sent_at' => (string) $row['sent_at'],
        'recipients' => (int) $row['recipients'], 'stats' => is_array($stats) ? $stats : [], 'stats_at' => (string) $row['stats_at'],
        'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at'],
    ];
}

function civilina_campaign_find(int $id): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM campaigns WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    return $row ? civilina_campaign_from_row($row) : null;
}

function civilina_campaigns_all(): array
{
    return array_map('civilina_campaign_from_row', civilina_db()->query('SELECT * FROM campaigns ORDER BY id DESC')->fetchAll());
}

function civilina_campaign_save(array $c): int
{
    $db = civilina_db();
    $now = date('c');
    $data = json_encode(array_intersect_key($c, civilina_campaign_defaults()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!empty($c['id'])) {
        $db->prepare('UPDATE campaigns SET subject = ?, data = ?, status = ?, scheduled_at = ?, updated_at = ? WHERE id = ?')
            ->execute([$c['subject'], $data, $c['status'] ?? 'draft', $c['scheduled_at'] ?? '', $now, $c['id']]);
        return (int) $c['id'];
    }
    $db->prepare('INSERT INTO campaigns(status, subject, data, scheduled_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)')
        ->execute([$c['status'] ?? 'draft', $c['subject'], $data, $c['scheduled_at'] ?? '', $now, $now]);
    return (int) $db->lastInsertId();
}

function civilina_campaign_delete(int $id): bool
{
    $statement = civilina_db()->prepare("DELETE FROM campaigns WHERE id = ? AND status = 'draft'");
    $statement->execute([$id]);
    return $statement->rowCount() > 0;
}

/** Confirmed subscribers who chose at least one of the campaign axes. */
function civilina_campaign_audience(array $axes): array
{
    $axes = civilina_nl_clean_axes($axes);
    $out = [];
    foreach (civilina_db()->query("SELECT id, email, first_name, axes FROM subscribers WHERE status = 'confirmed' ORDER BY id")->fetchAll() as $s) {
        if (array_intersect($axes, civilina_nl_clean_axes((string) $s['axes'])) !== []) {
            $out[] = $s;
        }
    }
    return $out;
}

/** Articles published since the last campaign sent (or the 3 latest), in the chosen axes. */
function civilina_campaign_suggest_articles(array $axes): array
{
    $axes = civilina_nl_clean_axes($axes);
    $last = (string) civilina_db()->query("SELECT MAX(sent_at) FROM campaigns WHERE status = 'sent'")->fetchColumn();
    $since = $last !== '' ? substr($last, 0, 10) : '';
    $pool = array_values(array_filter(civilina_articles_published(), static fn (array $a): bool => in_array($a['axis'], $axes, true)));
    $fresh = $since !== '' ? array_values(array_filter($pool, static fn (array $a): bool => $a['date_published'] > $since)) : [];
    return array_column(array_slice($fresh !== [] ? $fresh : $pool, 0, $fresh !== [] ? 6 : 3), 'slug');
}

/** The selected articles that are online, in the chosen order. */
function civilina_campaign_articles(array $c): array
{
    $bySlug = [];
    foreach (civilina_articles_published() as $a) {
        $bySlug[$a['slug']] = $a;
    }
    $out = [];
    foreach ($c['articles'] as $slug) {
        if (isset($bySlug[$slug])) {
            $out[] = $bySlug[$slug];
        }
    }
    return $out;
}

function civilina_campaign_problems(array $c, bool $forSending): array
{
    $problems = [];
    if (trim($c['subject']) === '') {
        $problems[] = 'Renseignez l\'objet de l\'e-mail.';
    }
    if (trim($c['intro']) === '' && civilina_campaign_articles($c) === []) {
        $problems[] = 'Ajoutez un texte d\'introduction ou au moins un article.';
    }
    if (!civilina_nl_ready()) {
        $problems[] = 'L\'envoi n\'est pas configuré : clé d\'API Brevo et adresse d\'expéditeur à renseigner dans les réglages.';
    }
    if ($forSending && civilina_campaign_audience($c['axes']) === []) {
        $problems[] = 'Aucun abonné confirmé ne correspond aux axes choisis.';
    }
    return $problems;
}

function civilina_campaign_tag(array $c): string
{
    return 'civilina-campagne-' . (int) ($c['id'] ?? 0);
}

function civilina_campaign_link(string $url, array $c): string
{
    $query = http_build_query(['utm_source' => 'newsletter', 'utm_medium' => 'email', 'utm_campaign' => 'newsletter-' . (int) ($c['id'] ?? 0)]);
    return $url . (str_contains($url, '?') ? '&' : '?') . $query;
}

/**
 * The campaign e-mail for one subscriber (null: preview or test, with a neutral link).
 * @return array{html: string, text: string}
 */
function civilina_campaign_email(array $c, ?array $subscriber): array
{
    $site = civilina_site_url();
    $manage = $subscriber ? civilina_nl_manage_url((int) $subscriber['id']) : $site . '/newsletter/';
    $axes = $subscriber ? civilina_nl_axes_label((string) $subscriber['axes']) : implode(', ', array_map(static fn (string $a): string => CIVILINA_NL_AXES[$a], civilina_nl_clean_axes($c['axes'])));
    $colors = ['engineering' => '#003b8c', 'entreprise' => '#16845b', 'digital' => '#0077ab'];

    $inner = '';
    $text = '';
    if (trim($c['title']) !== '') {
        $inner .= '<h1 style="margin:8px 0 14px;font-size:24px;line-height:1.3;color:#102033">' . civilina_mail_e($c['title']) . '</h1>';
        $text .= $c['title'] . "\n\n";
    }
    if ($subscriber && $subscriber['first_name'] !== '') {
        $inner .= '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#252a31">Bonjour ' . civilina_mail_e($subscriber['first_name']) . ',</p>';
        $text .= 'Bonjour ' . $subscriber['first_name'] . ",\n\n";
    }
    $inner .= civilina_mail_paragraphs($c['intro']);
    $text .= civilina_mail_text($c['intro']) . "\n\n";

    foreach (civilina_campaign_articles($c) as $a) {
        $link = civilina_campaign_link($site . '/journal/' . $a['slug'] . '/', $c);
        $color = $colors[$a['axis']] ?? '#003b8c';
        $summary = $a['card_text'] !== '' ? $a['card_text'] : $a['description'];
        $inner .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 14px;border:1px solid #dce3eb;border-left:4px solid ' . $color . ';border-radius:12px"><tr><td style="padding:16px 18px">'
            . '<div style="font-size:12px;font-weight:700;letter-spacing:1px;text-transform:uppercase;color:' . $color . '">' . civilina_mail_e(civilina_article_pill($a)) . '</div>'
            . '<a href="' . civilina_mail_e($link) . '" style="display:block;margin:6px 0;font-size:18px;font-weight:700;line-height:1.35;color:#102033;text-decoration:none">' . civilina_mail_e($a['title']) . '</a>'
            . '<div style="font-size:15px;line-height:1.55;color:#5d6673">' . civilina_mail_e($summary) . '</div>'
            . '<a href="' . civilina_mail_e($link) . '" style="display:inline-block;margin-top:10px;font-size:15px;font-weight:700;color:' . $color . ';text-decoration:none">Lire l\'article →</a>'
            . '</td></tr></table>';
        $text .= '• ' . $a['title'] . ' — ' . $summary . "\n  " . $link . "\n\n";
    }
    if (trim($c['outro']) !== '') {
        $inner .= '<div style="margin-top:8px">' . civilina_mail_paragraphs($c['outro']) . '</div>';
        $text .= civilina_mail_text($c['outro']) . "\n\n";
    }
    $inner .= '<p style="margin:18px 0 6px;font-size:16px;line-height:1.6;color:#252a31">Abdelghani Bouhouche<br><span style="color:#5d6673;font-size:14px">Ingénieur civil, PMP · Fondateur de Civilina</span></p>';
    $text .= "Abdelghani Bouhouche\nIngénieur civil, PMP · Fondateur de Civilina\n\n";

    $legal = civilina_nl_config()['legal'];
    $footer = 'Vous recevez cet e-mail parce que vous êtes inscrit à la newsletter Civilina (' . civilina_mail_e($axes) . ').<br>'
        . '<a href="' . civilina_mail_e($manage) . '" style="color:#5d6673">Choisir mes thèmes ou me désinscrire</a> · <a href="' . civilina_mail_e(civilina_campaign_link($site . '/journal/', $c)) . '" style="color:#5d6673">Tous les articles</a><br>'
        . civilina_mail_e($legal);
    $text .= "—\nVous recevez cet e-mail parce que vous êtes inscrit à la newsletter Civilina (" . $axes . ").\nChoisir mes thèmes ou me désinscrire : " . $manage . "\n" . $legal . "\n";

    return ['html' => civilina_mail_shell($c['preheader'], $inner, $footer), 'text' => $text];
}

/** Test e-mail: same content, subject prefixed with [Test]. */
function civilina_campaign_send_test(array $c, string $email): array
{
    $mail = civilina_campaign_email($c, null);
    return civilina_mail_send($email, '', '[Test] ' . $c['subject'], $mail['html'], $mail['text'], ['kind' => 'test', 'tags' => ['civilina-test']]);
}

/** Freezes the list of recipients and starts sending. */
function civilina_campaign_start(int $id, string $userId): array
{
    $c = civilina_campaign_find($id);
    if (!$c || !in_array($c['status'], ['draft', 'scheduled'], true)) {
        return ['ok' => false, 'messages' => ['Cette campagne a déjà été envoyée.']];
    }
    $problems = civilina_campaign_problems($c, true);
    if ($problems !== []) {
        return ['ok' => false, 'messages' => $problems];
    }
    $db = civilina_db();
    $audience = civilina_campaign_audience($c['axes']);
    $db->exec('BEGIN IMMEDIATE');
    try {
        $insert = $db->prepare("INSERT OR IGNORE INTO campaign_sends(campaign_id, subscriber_id, status) VALUES (?, ?, 'queued')");
        foreach ($audience as $s) {
            $insert->execute([$id, $s['id']]);
        }
        $db->prepare("UPDATE campaigns SET status = 'sending', started_at = ?, scheduled_at = '', recipients = ?, updated_at = ? WHERE id = ? AND status IN ('draft', 'scheduled')")
            ->execute([date('c'), count($audience), date('c'), $id]);
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
    civilina_audit('newsletter.campaign_started', '#' . $id . ' : ' . count($audience) . ' destinataires', $userId);
    return ['ok' => true, 'messages' => [count($audience) . ' destinataires']];
}

/** Progress of one campaign: counts by send status. */
function civilina_campaign_progress(int $id): array
{
    $statement = civilina_db()->prepare('SELECT status, COUNT(*) FROM campaign_sends WHERE campaign_id = ? GROUP BY status');
    $statement->execute([$id]);
    return array_map('intval', $statement->fetchAll(PDO::FETCH_KEY_PAIR)) + ['queued' => 0, 'sending' => 0, 'sent' => 0, 'failed' => 0, 'skipped' => 0];
}

/**
 * Background work, cheap when there is nothing to do: starts the scheduled
 * campaigns that are due, sends queued campaign e-mails and the confirmation
 * e-mails waiting for a key, within the time budget and the daily quota.
 * @return array{sent: int, confirmations: int, paused: string}
 */
function civilina_newsletter_process(float $budget = 5.0): array
{
    $result = ['sent' => 0, 'confirmations' => 0, 'paused' => ''];
    $db = civilina_db();
    $deadline = microtime(true) + $budget;

    $due = $db->prepare("SELECT id FROM campaigns WHERE status = 'scheduled' AND scheduled_at != '' AND scheduled_at <= ?");
    $due->execute([date('Y-m-d H:i')]);
    $dueIds = $due->fetchAll(PDO::FETCH_COLUMN);
    $sending = (int) $db->query("SELECT COUNT(*) FROM campaigns WHERE status = 'sending'")->fetchColumn();
    $waiting = (int) $db->query("SELECT COUNT(*) FROM subscribers WHERE status = 'pending' AND confirm_sent_at = ''")->fetchColumn();
    if ($dueIds === [] && $sending === 0 && $waiting === 0) {
        return $result;
    }
    if (!civilina_nl_ready()) {
        $result['paused'] = 'not_configured';
        return $result;
    }

    $lock = fopen(civilina_db_private_dir() . '/storage/newsletter.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX | LOCK_NB)) {
        $result['paused'] = 'busy';
        return $result;
    }
    try {
        foreach ($dueIds as $id) {
            $started = civilina_campaign_start((int) $id, 'programmation');
            if (!$started['ok']) {
                $db->prepare("UPDATE campaigns SET status = 'draft', updated_at = ? WHERE id = ? AND status = 'scheduled'")->execute([date('c'), $id]);
                civilina_audit('newsletter.schedule_failed', '#' . $id . ' : ' . implode(' ', $started['messages']));
            }
        }

        // Confirmation e-mails first: a visitor is waiting for them.
        $pending = $db->query("SELECT id FROM subscribers WHERE status = 'pending' AND confirm_sent_at = '' ORDER BY id LIMIT 20")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($pending as $id) {
            if (microtime(true) > $deadline || civilina_mail_quota_left() <= 0) {
                break;
            }
            $result['confirmations'] += civilina_nl_send_confirmation((int) $id) ? 1 : 0;
        }

        foreach ($db->query("SELECT id FROM campaigns WHERE status = 'sending' ORDER BY id")->fetchAll(PDO::FETCH_COLUMN) as $campaignId) {
            $c = civilina_campaign_find((int) $campaignId);
            $queue = $db->prepare("SELECT s.subscriber_id, b.email, b.first_name, b.axes, b.status FROM campaign_sends s LEFT JOIN subscribers b ON b.id = s.subscriber_id WHERE s.campaign_id = ? AND s.status = 'queued' ORDER BY s.subscriber_id LIMIT 200");
            $queue->execute([$campaignId]);
            $mark = $db->prepare('UPDATE campaign_sends SET status = ?, message_id = ?, error = ?, sent_at = ? WHERE campaign_id = ? AND subscriber_id = ?');
            foreach ($queue->fetchAll() as $row) {
                if (microtime(true) > $deadline) {
                    $result['paused'] = 'time';
                    break 2;
                }
                if (civilina_mail_quota_left() <= 0) {
                    $result['paused'] = 'quota';
                    break 2;
                }
                // Unsubscribed (or deleted) since the campaign started: skipped.
                if (($row['status'] ?? '') !== 'confirmed') {
                    $mark->execute(['skipped', '', '', date('c'), $campaignId, $row['subscriber_id']]);
                    continue;
                }
                // Marked before the call: an interrupted request never sends twice.
                $mark->execute(['sending', '', '', date('c'), $campaignId, $row['subscriber_id']]);
                $mail = civilina_campaign_email($c, $row + ['id' => $row['subscriber_id']]);
                $manage = civilina_nl_manage_url((int) $row['subscriber_id']);
                $sent = civilina_mail_send($row['email'], $row['first_name'], $c['subject'], $mail['html'], $mail['text'], [
                    'kind' => 'campaign',
                    'tags' => [civilina_campaign_tag($c)],
                    'headers' => ['List-Unsubscribe' => '<' . $manage . '>', 'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click'],
                ]);
                if ($sent['ok']) {
                    $mark->execute(['sent', $sent['id'], '', date('c'), $campaignId, $row['subscriber_id']]);
                    $result['sent']++;
                } elseif (in_array($sent['status'], [0, 401, 402, 403, 429], true) || $sent['status'] >= 500) {
                    // Key, credits, rate limit or Brevo unavailable: back in the queue, retried later.
                    $mark->execute(['queued', '', $sent['error'], '', $campaignId, $row['subscriber_id']]);
                    $result['paused'] = 'error';
                    civilina_setting_set('nl_last_error', date('c') . ' ' . $sent['error']);
                    break 2;
                } else {
                    $mark->execute(['failed', '', $sent['error'], date('c'), $campaignId, $row['subscriber_id']]);
                }
            }
            $left = $db->prepare("SELECT COUNT(*) FROM campaign_sends WHERE campaign_id = ? AND status = 'queued'");
            $left->execute([$campaignId]);
            if ((int) $left->fetchColumn() === 0) {
                $db->prepare("UPDATE campaigns SET status = 'sent', sent_at = ?, updated_at = ? WHERE id = ? AND status = 'sending'")->execute([date('c'), date('c'), $campaignId]);
                civilina_audit('newsletter.campaign_sent', '#' . $campaignId);
            }
        }
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
    return $result;
}

/**
 * Opening and click statistics from Brevo (aggregated report of the campaign tag),
 * and the addresses Brevo blocked (hard bounces, complaints, unsubscriptions).
 * @return string error message, '' when done
 */
function civilina_campaign_refresh_stats(int $id): string
{
    $c = civilina_campaign_find($id);
    if (!$c || $c['started_at'] === '') {
        return 'Cette campagne n\'a pas encore été envoyée.';
    }
    $progress = civilina_campaign_progress($id);
    $stats = ['sent' => $progress['sent'], 'failed' => $progress['failed']];
    if (civilina_nl_outbox() === '') {
        $start = substr($c['started_at'], 0, 10);
        $query = http_build_query(['startDate' => $start, 'endDate' => date('Y-m-d'), 'tag' => civilina_campaign_tag($c)]);
        [, $data, $error] = civilina_brevo_request('GET', '/smtp/statistics/aggregatedReport?' . $query);
        if ($error !== '') {
            return $error;
        }
        foreach (['requests', 'delivered', 'hardBounces', 'softBounces', 'clicks', 'uniqueClicks', 'opens', 'uniqueOpens', 'spamReports', 'blocked', 'invalid', 'unsubscribed'] as $key) {
            $stats[$key] = (int) ($data[$key] ?? 0);
        }
        civilina_nl_sync_blocked($start);
    }
    civilina_db()->prepare('UPDATE campaigns SET stats = ?, stats_at = ? WHERE id = ?')
        ->execute([json_encode($stats), date('c'), $id]);
    return '';
}

/** Marks the subscribers that Brevo blocked since a date. */
function civilina_nl_sync_blocked(string $since): void
{
    [, $data, $error] = civilina_brevo_request('GET', '/smtp/blockedContacts?' . http_build_query(['startDate' => $since, 'endDate' => date('Y-m-d'), 'limit' => 100]));
    if ($error !== '' || !is_array($data)) {
        return;
    }
    foreach ($data['contacts'] ?? [] as $contact) {
        $email = civilina_nl_normalize_email((string) ($contact['email'] ?? ''));
        $code = (string) ($contact['reason']['code'] ?? '');
        $s = $email !== '' ? civilina_nl_find_by_email($email) : null;
        if (!$s || !in_array($s['status'], ['pending', 'confirmed'], true)) {
            continue;
        }
        if (str_starts_with($code, 'unsubscribed')) {
            civilina_nl_unsubscribe((int) $s['id'], 'Brevo');
        } else {
            civilina_db()->prepare("UPDATE subscribers SET status = 'blocked', updated_at = ? WHERE id = ?")->execute([date('c'), $s['id']]);
            civilina_audit('newsletter.blocked', '#' . $s['id'] . ' (' . $code . ')');
        }
    }
}

/** Account, sender and domain checks shown in the settings. */
function civilina_nl_check_brevo(): array
{
    $checks = [];
    if (civilina_nl_outbox() !== '') {
        return [['ok' => true, 'label' => 'Mode local : les e-mails sont écrits dans le dossier outbox, rien n\'est envoyé.']];
    }
    [, $account, $error] = civilina_brevo_request('GET', '/account');
    if ($error !== '') {
        return [['ok' => false, 'label' => 'Connexion au compte Brevo : ' . $error]];
    }
    $credits = '';
    foreach ($account['plan'] ?? [] as $plan) {
        if (isset($plan['credits'])) {
            $credits = ' · crédits e-mail restants : ' . (int) $plan['credits'] . ' (' . (string) ($plan['type'] ?? '') . ')';
        }
    }
    $checks[] = ['ok' => true, 'label' => 'Compte Brevo connecté' . $credits];

    $senderEmail = civilina_nl_normalize_email(civilina_setting('nl_sender_email'));
    [, $senders] = civilina_brevo_request('GET', '/senders');
    $active = false;
    foreach ($senders['senders'] ?? [] as $sender) {
        if (civilina_nl_normalize_email((string) ($sender['email'] ?? '')) === $senderEmail && !empty($sender['active'])) {
            $active = true;
        }
    }
    $checks[] = ['ok' => $active, 'label' => $active ? 'Expéditeur ' . $senderEmail . ' validé dans Brevo' : 'Expéditeur ' . ($senderEmail !== '' ? $senderEmail : '(non renseigné)') . ' : à ajouter et valider dans Brevo (Expéditeurs, domaines et IP dédiées)'];

    $domain = substr((string) strrchr($senderEmail, '@'), 1);
    [, $domains] = civilina_brevo_request('GET', '/senders/domains');
    $authenticated = false;
    foreach ($domains['domains'] ?? [] as $d) {
        if (mb_strtolower((string) ($d['domain_name'] ?? '')) === $domain && !empty($d['authenticated'])) {
            $authenticated = true;
        }
    }
    $checks[] = ['ok' => $authenticated, 'label' => $authenticated ? 'Domaine ' . $domain . ' authentifié (DKIM, DMARC)' : 'Domaine ' . ($domain !== '' ? $domain : '—') . ' non authentifié : ajoutez dans cPanel (Zone Editor) les enregistrements DNS fournis par Brevo'];
    return $checks;
}
