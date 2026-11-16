<?php

declare(strict_types=1);

/*
 * One-off (milestone M3): run once on the server after the M3 code is uploaded.
 * 1. migrates the database to schema v4 (newsletter tables);
 * 2. adds the newsletter box to the homepage, the Journal page and the pages
 *    of the articles already online (backups in civilina_private/backups/journal/);
 * 3. checks that the server can reach the Brevo API (expects HTTP 401 without a key).
 * Copied under a random name in public_html, called once, deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$private = dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private';
require $private . '/civilina_publish.php';
require $private . '/civilina_newsletter.php';

try {
    $db = civilina_db();
    echo 'schema=' . (int) $db->query('PRAGMA user_version')->fetchColumn() . "\n";
    echo 'tables=' . implode(',', $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name IN ('subscribers','campaigns','campaign_sends','email_log','form_hits') ORDER BY name")->fetchAll(PDO::FETCH_COLUMN)) . "\n";
    echo 'secret=' . (strlen(civilina_setting('newsletter_secret')) === 64 ? 'ok' : 'MISSING') . "\n";
    echo 'curl=' . (function_exists('curl_init') ? 'yes' : 'no') . "\n";
    [$status, , $error] = civilina_brevo_request('GET', '/account', null, 'xkeysib-connectivity-check');
    echo 'brevo=' . $status . ($status === 401 ? ' (reachable)' : ' ' . $error) . "\n";
    echo 'boxes=' . implode(',', civilina_newsletter_insert_boxes()) . "\n";
    $pages = civilina_articles_insert_newsletter_box('deploiement-m3');
    echo 'article_pages=' . count($pages) . "\n";
    civilina_audit('newsletter.deployed', 'M3 : ' . count($pages) . ' pages d\'articles');
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
