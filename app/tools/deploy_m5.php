<?php

declare(strict_types=1);

/*
 * One-off (milestone M5): run once on the server after uploading the M5 code.
 * 1. adds the security headers to public_html/.htaccess (backup first);
 * 2. makes the private files readable by the account only;
 * 3. creates a backup and verifies it (restore test).
 * Deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$private = dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private';
require $private . '/civilina_backup.php';

const CIVILINA_HEADERS_BLOCK = <<<'TXT'

# Civilina M5: security headers on every response.
<IfModule mod_headers.c>
  Header always unset X-Powered-By
  Header unset X-Powered-By
  Header always set Strict-Transport-Security "max-age=15552000"
  Header always set X-Content-Type-Options "nosniff"
  Header always set X-Frame-Options "SAMEORIGIN"
  Header always set Referrer-Policy "strict-origin-when-cross-origin"
  Header always set Permissions-Policy "camera=(), microphone=(), geolocation=(), payment=()"
</IfModule>

TXT;

try {
    $htaccess = civilina_site_root() . '/.htaccess';
    $current = (string) file_get_contents($htaccess);
    if (str_contains($current, '# Civilina M5: security headers')) {
        echo "htaccess=already\n";
    } else {
        civilina_backup_files([$htaccess], 'htaccess-m5');
        civilina_write_file($htaccess, rtrim($current, "\n") . "\n" . CIVILINA_HEADERS_BLOCK);
        echo "htaccess=updated\n";
    }
    echo 'permissions_changed=' . civilina_backup_harden_permissions() . "\n";
    $backup = civilina_backup_run('mise en service M5');
    echo 'backup=' . ($backup['ok'] ? $backup['file'] . ' ' . $backup['files'] . ' fichiers ' . round($backup['size'] / 1024) . ' Ko' : 'ERROR ' . $backup['error']) . "\n";
    if ($backup['ok']) {
        $verify = civilina_backup_verify($backup['file']);
        echo 'restore_test=' . ($verify['ok'] ? 'OK' : 'FAILED') . "\n  " . implode("\n  ", $verify['report']) . "\n";
    }
    echo 'zip=' . (class_exists('ZipArchive') ? 'yes' : 'no') . ' sqlite=' . (new PDO('sqlite::memory:'))->query('select sqlite_version()')->fetchColumn() . "\n";
    civilina_audit('security.hardened', 'M5 : en-têtes, droits, sauvegarde');
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
