<?php

declare(strict_types=1);

/*
 * One-off (16/11/2026): links the free templates from the site (band on the
 * homepage, the Journal page and the axis pages, footer link on these pages and
 * on every article). Pages are backed up first. Deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_sales.php';

try {
    $result = civilina_site_link_resources('deploiement-liens-modeles');
    echo 'changed=' . count($result['changed']) . "\n" . implode("\n", $result['changed']) . "\n";
    echo 'skipped=' . implode(' | ', $result['skipped']) . "\n";
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
