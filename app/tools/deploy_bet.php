<?php

declare(strict_types=1);

/*
 * One-off (17/11/2026, phase 1 of the engineering-firm space): adds the
 * promotion band to the homepage, the Engineering page and the Journal page,
 * points the pilot calls to action of the IA and Engineering pages to
 * /bureaux-etudes/#pilote, adds the page to the sitemap and draws its share
 * image. Pages are backed up first. Deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_sales.php';

try {
    $result = civilina_site_promote_bet('deploiement-bureaux-etudes');
    echo 'changed=' . implode(',', $result['changed']) . "\n";
    echo 'skipped=' . implode(' | ', $result['skipped']) . "\n";
    echo 'share=' . (is_file(civilina_site_root() . '/assets/brand/share/civilina-bet.png') ? 'yes' : 'no') . "\n";
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
