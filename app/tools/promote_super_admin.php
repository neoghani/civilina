<?php

declare(strict_types=1);

/*
 * One-off: grants the super administrator flag to an existing admin account.
 *
 * Deployment: copy to public_html under a random name, replace __EMAIL__,
 * call it once over HTTPS; the script deletes itself after running.
 * Locally: php promote_super_admin.php <document_root> <email>
 */

if (PHP_SAPI === 'cli') {
    $_SERVER['DOCUMENT_ROOT'] = $argv[1] ?? '';
    $email = $argv[2] ?? '';
} else {
    $email = '__EMAIL__';
    header('Content-Type: text/plain; charset=utf-8');
}

require dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/')) . '/civilina_private/civilina_auth.php';

$user = civilina_find_user_by_email($email);
if (!$user || (string) ($user['role'] ?? '') !== 'admin') {
    echo "refused: no admin account with this e-mail\n";
} else {
    $user['super_admin'] = true;
    $user['updated_at'] = gmdate('c');
    civilina_save_user($user);
    civilina_audit('user.role', $user['id'] . ' : admin → super administrateur', (string) $user['id']);
    $counts = civilina_db()->query('SELECT (SELECT COUNT(*) FROM users) AS users, (SELECT COUNT(*) FROM contacts) AS contacts')->fetch();
    echo 'ok: super admin granted; users=' . $counts['users'] . ' contacts=' . $counts['contacts'] . "\n";
}

if (PHP_SAPI !== 'cli') {
    @unlink(__FILE__);
}
