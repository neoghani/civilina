<?php

declare(strict_types=1);

/*
 * One-off (SEO pass of 16/11/2026): run once on the server after uploading
 * civilina_seo.php, the updated articles/publish modules and private/fonts/.
 * Applies civilina_seo_apply(): shorter titles and descriptions, PNG share
 * images, Open Graph/X tags, structured data, favicon. Deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$private = dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private';
require $private . '/civilina_seo.php';

try {
    foreach (['Inter-Regular.ttf', 'Inter-Bold.ttf', 'Inter-ExtraBold.ttf'] as $font) {
        echo $font . '=' . (is_file($private . '/fonts/' . $font) ? hash_file('sha256', $private . '/fonts/' . $font) : 'MISSING') . "\n";
    }
    $result = civilina_seo_apply('deploiement-seo');
    echo 'pages=' . count($result['files']) . "\n";
    echo 'images=' . $result['images'] . "\n";
    echo implode("\n", $result['files']) . "\n";
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
