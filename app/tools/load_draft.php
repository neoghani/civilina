<?php

declare(strict_types=1);

/*
 * One-off: loads an article prepared as JSON (docs/site-vitrine/brouillons/)
 * into the editor as a DRAFT. It never publishes: publishing stays a click
 * in the back-office. Imports the published articles first if the editor is
 * still empty, so the automatic import is not skipped.
 *
 * Locally: php load_draft.php <document_root> <draft.json>
 * Server:  copied under a random name with the JSON in $json, called once, deletes itself.
 */

if (PHP_SAPI === 'cli') {
    $_SERVER['DOCUMENT_ROOT'] = $argv[1] ?? '';
    $json = (string) file_get_contents($argv[2] ?? '');
} else {
    header('Content-Type: text/plain; charset=utf-8');
    header('Cache-Control: no-store');
    $json = '__JSON__';
}

require dirname(rtrim($_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_publish.php';

$draft = json_decode($json, true);
if (!is_array($draft) || empty($draft['slug'])) {
    echo "refused: invalid draft\n";
} else {
    $imported = 0;
    if ((int) civilina_db()->query('SELECT COUNT(*) FROM articles')->fetchColumn() === 0) {
        $imported = civilina_articles_import_all();
        civilina_audit('article.imported', $imported . ' articles');
    }
    if (civilina_article_find_by_slug($draft['slug'])) {
        echo "skipped: an article with this slug already exists; imported=$imported\n";
    } else {
        $article = array_replace_recursive(civilina_article_defaults(), array_intersect_key($draft, civilina_article_defaults()));
        $article['related'] = $draft['related'] ?? [];
        $article += ['slug' => $draft['slug'], 'status' => 'draft', 'publish_at' => '', 'date_published' => '', 'date_modified' => ''];
        $id = civilina_article_save($article);
        civilina_audit('article.created', $draft['slug'] . ' (brouillon préparé)');
        $render = civilina_md_render($article['body']);
        $problems = civilina_article_problems(civilina_article_find($id));
        echo 'ok: draft id=' . $id . ' imported=' . $imported . ' words=' . $render['words'] . ' sections=' . count($render['toc']) . ' problems=' . count($problems) . "\n";
        foreach ($problems as $problem) {
            echo ' - ' . $problem . "\n";
        }
    }
}

if (PHP_SAPI !== 'cli') {
    @unlink(__FILE__);
}
