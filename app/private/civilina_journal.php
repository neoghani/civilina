<?php

declare(strict_types=1);

/*
 * Journal inventory, read from the published static pages
 * (public_html/journal/<slug>/index.html). Until the editor (milestone M2)
 * stores articles in the database, the published HTML is the source of truth.
 */

const CIVILINA_PILL_AXES = [
    'pill-eng' => 'engineering',
    'pill-ent' => 'entreprise',
    'pill-dig' => 'digital',
    'pill-ia' => 'ia',
];

function civilina_journal_articles(): array
{
    static $articles = null;
    if ($articles !== null) {
        return $articles;
    }

    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/journal';
    $articles = [];
    foreach (glob($root . '/*/index.html') ?: [] as $file) {
        $html = (string) file_get_contents($file);
        $slug = basename(dirname($file));

        preg_match('~<h1>(.*?)</h1>~s', $html, $title);
        preg_match('~"datePublished":"([0-9-]{10})"~', $html, $date);
        preg_match('~<span class="pill (pill-[a-z]+)">(.*?)</span>~', $html, $pill);
        preg_match('~<div class="prose">(.*?)<div class="sources">~s', $html, $prose);

        if (!$title) {
            continue;
        }

        $articles[] = [
            'slug' => $slug,
            'url' => '/journal/' . $slug . '/',
            'title' => html_entity_decode(strip_tags($title[1]), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'date' => $date[1] ?? '',
            'axis' => CIVILINA_PILL_AXES[$pill[1] ?? ''] ?? 'a_qualifier',
            'category' => html_entity_decode(strip_tags($pill[2] ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            'words' => $prose ? count(preg_split('/\s+/u', trim(strip_tags($prose[1])), -1, PREG_SPLIT_NO_EMPTY) ?: []) : 0,
            'mtime' => (int) filemtime($file),
        ];
    }

    // Newest first; articles published the same day by upload order.
    usort($articles, static fn (array $a, array $b): int => [$b['date'], $b['mtime']] <=> [$a['date'], $a['mtime']]);

    return $articles;
}

function civilina_format_date(string $isoDate): string
{
    $months = ['janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
    $time = strtotime($isoDate);
    if ($time === false) {
        return $isoDate;
    }
    return (int) date('j', $time) . ' ' . $months[(int) date('n', $time) - 1] . ' ' . date('Y', $time);
}
