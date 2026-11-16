<?php

declare(strict_types=1);

/*
 * Audience measurement for civilina.com (milestone M1), without cookies.
 *
 * - Public pages send one beacon per page view to /visite/ (assets/js/theme.js).
 * - The visitor is a hash of (daily salt + IP + browser). The salt changes
 *   every day and the previous one is discarded: a visitor cannot be followed
 *   from one day to the next, and no IP address is stored.
 * - A "visit" is therefore one visitor on one day. Visits by source count only
 *   the entry pages (internal navigation is excluded).
 * - Back-office administrators browsing the site are not counted.
 */

require_once __DIR__ . '/civilina_db.php';
require_once __DIR__ . '/civilina_journal.php';

const CIVILINA_STATS_SOURCES = [
    'search' => 'Moteurs de recherche',
    'linkedin' => 'LinkedIn',
    'social' => 'Autres réseaux sociaux',
    'referral' => 'Sites référents',
    'newsletter' => 'Newsletter',
    'campaign' => 'Campagnes (liens suivis)',
    'direct' => 'Accès direct',
];

const CIVILINA_STATS_DEVICES = [
    'mobile' => 'Mobile',
    'tablet' => 'Tablette',
    'desktop' => 'Ordinateur',
];

const CIVILINA_STATS_RETENTION_DAYS = 400;

/* ---------- Recording ---------- */

function civilina_stats_daily_salt(PDO $db, string $day): string
{
    $row = $db->query("SELECT value FROM settings WHERE key = 'visit_salt'")->fetchColumn();
    $data = is_string($row) ? json_decode($row, true) : null;
    if (is_array($data) && ($data['day'] ?? '') === $day && is_string($data['salt'] ?? null)) {
        return $data['salt'];
    }
    $salt = bin2hex(random_bytes(16));
    $db->prepare("INSERT INTO settings(key, value) VALUES ('visit_salt', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value")
        ->execute([json_encode(['day' => $day, 'salt' => $salt])]);
    return $salt;
}

/** @return array{0: string, 1: string, 2: string} source, referrer host, campaign */
function civilina_stats_source(string $referrerHost, string $siteHost, array $utm): array
{
    $strip = static fn (string $h): string => (string) preg_replace('~^www\.~', '', mb_strtolower(trim($h)));
    $host = $strip($referrerHost);
    $site = $strip((string) preg_replace('~:\d+$~', '', $siteHost));
    $campaign = mb_substr(trim((string) ($utm['utm_campaign'] ?? '')), 0, 80);
    $utmSource = mb_strtolower(trim((string) ($utm['utm_source'] ?? '')));

    if ($host !== '' && $host === $site && $utmSource === '') {
        return ['internal', '', ''];
    }
    if ($host === $site) {
        $host = '';
    }

    $probe = $utmSource !== '' ? $utmSource : $host;
    if ($probe === '') {
        return ['direct', '', $campaign];
    }
    if (preg_match('~(^|\.)(linkedin\.com|lnkd\.in)$|^linkedin$~', $probe)) {
        return ['linkedin', $host, $campaign];
    }
    if (preg_match('~(^|\.)(google\.[a-z.]+|bing\.com|duckduckgo\.com|yahoo\.com|qwant\.com|ecosia\.org|yandex\.[a-z.]+|baidu\.com)$|^(google|bing)$~', $probe)) {
        return ['search', $host, $campaign];
    }
    if (preg_match('~(^|\.)(facebook\.com|fb\.com|instagram\.com|t\.co|x\.com|twitter\.com|youtube\.com|whatsapp\.com|wa\.me|tiktok\.com)$|^(facebook|instagram|twitter|x|youtube|whatsapp)$~', $probe)) {
        return ['social', $host, $campaign];
    }
    if ($utmSource === 'newsletter') {
        return ['newsletter', '', $campaign];
    }
    if ($utmSource !== '') {
        return ['campaign', $host !== '' ? $host : mb_substr($utmSource, 0, 80), $campaign];
    }
    return ['referral', $host, $campaign];
}

function civilina_stats_record(string $path, string $referrer, string $query, int $width, string $siteHost): void
{
    $db = civilina_db();
    $now = time();
    $day = date('Y-m-d', $now);
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
    $visitor = substr(hash('sha256', civilina_stats_daily_salt($db, $day) . '|' . $ip . '|' . $agent), 0, 16);

    parse_str(ltrim(mb_substr($query, 0, 500), '?'), $utm);
    [$source, $host, $campaign] = civilina_stats_source((string) parse_url($referrer, PHP_URL_HOST), $siteHost, is_array($utm) ? $utm : []);
    $device = $width <= 0 ? '' : ($width < 768 ? 'mobile' : ($width < 1100 ? 'tablet' : 'desktop'));

    $db->prepare('INSERT INTO page_views(created_at, day, path, visitor, source, referrer_host, campaign, device) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([date('c', $now), $day, $path, $visitor, $source, mb_substr($host, 0, 120), $campaign, $device]);

    if (random_int(1, 200) === 1) {
        $db->prepare('DELETE FROM page_views WHERE day < ?')->execute([date('Y-m-d', strtotime('-' . CIVILINA_STATS_RETENTION_DAYS . ' days', $now))]);
    }
}

/** True when the beacon comes from a logged-in administrator (their browsing is not counted). */
function civilina_stats_is_admin_visit(): bool
{
    $id = (string) ($_COOKIE[session_name()] ?? '');
    if ($id === '' || !preg_match('~^[a-zA-Z0-9,-]{20,128}$~', $id)) {
        return false;
    }
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start(['read_and_close' => true]);
    }
    $userId = (string) ($_SESSION['user_id'] ?? '');
    if ($userId === '') {
        return false;
    }
    $statement = civilina_db()->prepare('SELECT role FROM users WHERE id = ?');
    $statement->execute([$userId]);
    return $statement->fetchColumn() === 'admin';
}

/* ---------- Reporting ---------- */

/** First and last day (inclusive) of the last $days days, and of the period before it. */
function civilina_stats_range(int $days, int $offset = 0): array
{
    $end = strtotime('today') - $offset * $days * 86400;
    return [date('Y-m-d', $end - ($days - 1) * 86400), date('Y-m-d', $end)];
}

function civilina_stats_totals(string $from, string $to): array
{
    $statement = civilina_db()->prepare('SELECT COUNT(*) AS views, COUNT(DISTINCT day || visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ?');
    $statement->execute([$from, $to]);
    $row = $statement->fetch() ?: ['views' => 0, 'visits' => 0];
    return ['views' => (int) $row['views'], 'visits' => (int) $row['visits']];
}

/** One row per day of the range, days without traffic included. */
function civilina_stats_daily(string $from, string $to): array
{
    $statement = civilina_db()->prepare('SELECT day, COUNT(*) AS views, COUNT(DISTINCT visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ? GROUP BY day');
    $statement->execute([$from, $to]);
    $byDay = [];
    foreach ($statement->fetchAll() as $row) {
        $byDay[$row['day']] = ['views' => (int) $row['views'], 'visits' => (int) $row['visits']];
    }
    $rows = [];
    for ($t = strtotime($from); $t <= strtotime($to); $t += 86400) {
        $day = date('Y-m-d', $t);
        $rows[] = ['day' => $day] + ($byDay[$day] ?? ['views' => 0, 'visits' => 0]);
    }
    return $rows;
}

function civilina_stats_pages(string $from, string $to, int $limit = 15): array
{
    $statement = civilina_db()->prepare('SELECT path, COUNT(*) AS views, COUNT(DISTINCT day || visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ? GROUP BY path ORDER BY views DESC, path LIMIT ' . max(1, $limit));
    $statement->execute([$from, $to]);
    return $statement->fetchAll();
}

function civilina_stats_sources(string $from, string $to): array
{
    $statement = civilina_db()->prepare("SELECT source, COUNT(DISTINCT day || visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ? AND source != 'internal' GROUP BY source ORDER BY visits DESC");
    $statement->execute([$from, $to]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

function civilina_stats_referrers(string $from, string $to, int $limit = 8): array
{
    $statement = civilina_db()->prepare("SELECT referrer_host, COUNT(DISTINCT day || visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ? AND referrer_host != '' GROUP BY referrer_host ORDER BY visits DESC LIMIT " . max(1, $limit));
    $statement->execute([$from, $to]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

function civilina_stats_devices(string $from, string $to): array
{
    $statement = civilina_db()->prepare("SELECT device, COUNT(DISTINCT day || visitor) AS visits FROM page_views WHERE day BETWEEN ? AND ? AND device != '' GROUP BY device");
    $statement->execute([$from, $to]);
    return $statement->fetchAll(PDO::FETCH_KEY_PAIR);
}

function civilina_stats_contacts(string $from, string $to): int
{
    $statement = civilina_db()->prepare('SELECT COUNT(*) FROM contacts WHERE substr(created_at, 1, 10) BETWEEN ? AND ?');
    $statement->execute([$from, $to]);
    return (int) $statement->fetchColumn();
}

/** Page label and axis, from the fixed pages and the Journal inventory. */
function civilina_stats_page_info(string $path): array
{
    static $articles = null;
    if ($articles === null) {
        $articles = [];
        foreach (civilina_journal_articles() as $article) {
            $articles[$article['url']] = $article;
        }
    }
    $fixed = [
        '/' => ['Accueil', 'other'],
        '/engineering/' => ['CIVILINA Engineering', 'engineering'],
        '/gestion-entreprise/' => ['CIVILINA Entreprise', 'entreprise'],
        '/digital/' => ['CIVILINA Digital', 'digital'],
        '/ia/' => ['CIVILINA IA', 'other'],
        '/journal/' => ['Journal (accueil)', 'other'],
    ];
    if (isset($fixed[$path])) {
        return ['label' => $fixed[$path][0], 'axis' => $fixed[$path][1], 'article' => false];
    }
    if (isset($articles[$path])) {
        $axis = $articles[$path]['axis'];
        return ['label' => $articles[$path]['title'], 'axis' => in_array($axis, ['engineering', 'entreprise', 'digital'], true) ? $axis : 'other', 'article' => true];
    }
    return ['label' => $path, 'axis' => 'other', 'article' => false];
}

/** Page views grouped by axis (axis page + its articles). */
function civilina_stats_axes(string $from, string $to): array
{
    $totals = ['engineering' => 0, 'entreprise' => 0, 'digital' => 0, 'other' => 0];
    foreach (civilina_stats_pages($from, $to, 1000) as $row) {
        $totals[civilina_stats_page_info($row['path'])['axis']] += (int) $row['views'];
    }
    return $totals;
}

/** Percentage change, or null when there is nothing to compare with. */
function civilina_stats_trend(int $current, int $previous): ?int
{
    return $previous > 0 ? (int) round(($current - $previous) * 100 / $previous) : null;
}
