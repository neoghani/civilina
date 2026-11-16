<?php

declare(strict_types=1);

/*
 * Anonymous page-view collector (milestone M1), called by assets/js/theme.js.
 * Sets no cookie and stores no IP address: see civilina_private/civilina_stats.php.
 * Always answers 204, whatever happens, so it never disturbs the page.
 */

header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$done = static function (): void {
    http_response_code(204);
    exit;
};

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    $done();
}

// Only beacons sent by pages of this site.
$host = (string) ($_SERVER['HTTP_HOST'] ?? '');
$origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
if ($origin === '' || parse_url($origin, PHP_URL_HOST) !== parse_url('//' . $host, PHP_URL_HOST)) {
    $done();
}

// Robots and automated tools are not visitors.
$agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
if ($agent === '' || preg_match('~bot|crawl|spider|slurp|headless|lighthouse|pagespeed|preview|monitor|curl|wget|python|java/|facebookexternalhit~i', $agent)) {
    $done();
}

$data = json_decode((string) file_get_contents('php://input', false, null, 0, 2048), true);
if (!is_array($data)) {
    $done();
}

// Only paths of pages that exist on the site.
$path = (string) ($data['p'] ?? '');
$path = (string) preg_replace('~/index\.html$~', '/', $path);
if ($path === '' || strlen($path) > 200 || !preg_match('~^/([a-z0-9-]+/)*$~', $path)) {
    $done();
}
$docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
$exists = $path === '/'
    ? (is_file($docRoot . '/default.html') || is_file($docRoot . '/index.html'))
    : (is_file($docRoot . $path . 'index.html') || is_file($docRoot . $path . 'index.php'));
if (!$exists) {
    $done();
}

require dirname($docRoot) . '/civilina_private/civilina_stats.php';

try {
    if (!civilina_stats_is_admin_visit()) {
        civilina_stats_record($path, (string) ($data['r'] ?? ''), (string) ($data['q'] ?? ''), (int) ($data['w'] ?? 0), $host);
    }
} catch (Throwable $e) {
    error_log('civilina visite: ' . $e->getMessage());
}

// Scheduled articles and newsletter e-mails are processed on the first visit after their time.
try {
    require_once dirname($docRoot) . '/civilina_private/civilina_publish.php';
    civilina_articles_publish_due();
    require_once dirname($docRoot) . '/civilina_private/civilina_newsletter.php';
    civilina_newsletter_process(4.0);
} catch (Throwable $e) {
    error_log('civilina scheduled work: ' . $e->getMessage());
}

$done();
