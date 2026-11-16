<?php

declare(strict_types=1);

/*
 * Journal publishing (milestone M2): import of the hand-made pages and
 * generation of the static files. See civilina_articles.php.
 */

require_once __DIR__ . '/civilina_articles.php';

function civilina_site_root(): string
{
    return rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
}

function civilina_home_file(): string
{
    $root = civilina_site_root();
    return is_file($root . '/default.html') ? $root . '/default.html' : $root . '/index.html';
}

function civilina_html_dom(string $html): DOMXPath
{
    $dom = new DOMDocument();
    libxml_use_internal_errors(true);
    $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_NOERROR | LIBXML_NOWARNING);
    libxml_clear_errors();
    return new DOMXPath($dom);
}

/* ---------- Import of the existing pages ---------- */

/** Reads one published page (and its cover and cards) into an article record. */
function civilina_article_import_page(string $slug, array $cards, array $homeCards): ?array
{
    $root = civilina_site_root();
    $file = $root . '/journal/' . $slug . '/index.html';
    if (!is_file($file)) {
        return null;
    }
    $x = civilina_html_dom((string) file_get_contents($file));
    $text = static fn (string $q): string => trim((string) preg_replace('~\s+~u', ' ', (string) ($x->query($q)->item(0)?->textContent ?? '')));
    $attr = static fn (string $q, string $name): string => (string) ($x->query($q)->item(0)?->getAttribute($name) ?? '');

    $a = civilina_article_defaults();
    $a['slug'] = $slug;
    $a['title'] = $text('//h1');
    $a['seo_title'] = $text('//title');
    $a['description'] = $attr('//meta[@name="description"]', 'content');
    $crumbs = explode('›', $text('//p[@class="crumbs"]'));
    $a['crumb'] = trim((string) end($crumbs));

    $pill = $x->query('//span[contains(@class,"pill ")]')->item(0);
    $pillClass = $pill instanceof DOMElement ? $pill->getAttribute('class') : '';
    foreach (CIVILINA_ARTICLE_AXES as $key => $axis) {
        if (str_contains($pillClass, 'pill-' . $axis[1])) {
            $a['axis'] = $key;
        }
    }
    $pillText = $pill ? trim($pill->textContent) : '';
    $a['rubric'] = trim((string) preg_replace('~^[^·]+·\s*~u', '', $pillText));
    if ($a['rubric'] === $pillText) {
        $a['rubric'] = '';
    }

    preg_match('~Lecture\s*:\s*(\d+)\s*min~u', $text('//div[@class="art-meta"]'), $m);
    $a['minutes'] = (int) ($m[1] ?? 0);

    $ld = json_decode($text('//script[@type="application/ld+json"]'), true) ?: [];
    $a['headline'] = (string) ($ld['headline'] ?? '') !== $a['title'] ? (string) ($ld['headline'] ?? '') : '';

    $img = $x->query('//figure[@class="art-cover"]/img')->item(0);
    $coverFile = $img instanceof DOMElement ? basename($img->getAttribute('src')) : '';
    $a['cover']['file'] = $coverFile;
    $a['cover']['alt'] = $img instanceof DOMElement ? $img->getAttribute('alt') : '';
    $svgPath = $root . '/assets/journal/' . $coverFile;
    if ($coverFile !== '' && is_file($svgPath)) {
        $svg = (string) file_get_contents($svgPath);
        if (preg_match_all('~stop-color="(#[0-9a-fA-F]{6})"~', $svg, $mm)) {
            $a['cover']['color'] = strtolower((string) end($mm[1]));
        }
        if (preg_match('~fill="#7fd3ff"[^>]*>([^<]*)</text>~', $svg, $mm)) {
            $a['cover']['kicker'] = html_entity_decode($mm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        if (preg_match_all('~<tspan x="88" y="\d+">([^<]*)</tspan>~', $svg, $mm)) {
            $a['cover']['line1'] = html_entity_decode($mm[1][0] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
            $a['cover']['line2'] = html_entity_decode($mm[1][1] ?? '', ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
        if (preg_match('~fill="#c9d6ea">([^<]*)</text>~', $svg, $mm)) {
            $a['cover']['subtitle'] = html_entity_decode($mm[1], ENT_QUOTES | ENT_XML1, 'UTF-8');
        }
    }

    $chapo = $x->query('//p[@class="chapo"]')->item(0);
    $a['chapo'] = $chapo ? trim(civilina_md_from_inline($chapo)) : '';
    $prose = $x->query('//div[@class="prose"]')->item(0);
    $a['body'] = $prose ? civilina_md_from_blocks($prose) : '';

    $lines = [];
    foreach ($x->query('//div[@class="sources"]/ul/li') as $li) {
        $lines[] = '- ' . trim(civilina_md_from_inline($li));
    }
    $a['sources'] = implode("\n", $lines);
    $sourcesTitle = $text('//div[@class="sources"]/strong');
    $a['sources_title'] = $sourcesTitle !== 'Références citées' ? $sourcesTitle : '';
    $note = $x->query('//div[@class="sources"]/p')->item(0);
    $a['sources_note'] = $note ? trim(civilina_md_from_inline($note)) : '';

    $a['cta'] = [
        'title' => $text('//div[@class="art-cta"]/h3'),
        'text' => $text('//div[@class="art-cta"]/p'),
        'url' => civilina_md_normalize_href($attr('//div[@class="art-cta"]/a', 'href')),
        'label' => $text('//div[@class="art-cta"]/a'),
    ];

    foreach ($x->query('//footer//h5[.="Journal"]/following-sibling::ul/li/a') as $link) {
        $href = civilina_md_normalize_href($link->getAttribute('href'));
        if (preg_match('~^/journal/([a-z0-9-]+)/$~', $href, $mm)) {
            $a['related'][] = [$mm[1], trim($link->textContent)];
        }
    }

    $a['card_text'] = $cards[$slug] ?? '';
    $homeCat = $homeCards[$slug]['cat'] ?? '';
    $a['home_cat'] = $homeCat !== '' && $homeCat !== civilina_article_pill($a) ? $homeCat : '';
    $a['home_title'] = $homeCards[$slug]['title'] ??trim($a['cover']['line1'] . ' ' . $a['cover']['line2']);
    $a['home_text'] = $homeCards[$slug]['text'] ?? ($a['cover']['subtitle'] !== '' ? rtrim($a['cover']['subtitle'], '.') . '.' : '');

    return $a + [
        'status' => 'published',
        'publish_at' => '',
        'date_published' => (string) ($ld['datePublished'] ?? date('Y-m-d')),
        'date_modified' => (string) ($ld['dateModified'] ?? ($ld['datePublished'] ?? date('Y-m-d'))),
    ];
}

/** Imports every article listed on the Journal page, oldest first (ids follow the list order). */
function civilina_articles_import_all(): int
{
    $root = civilina_site_root();
    $index = civilina_html_dom((string) file_get_contents($root . '/journal/index.html'));
    $slugs = [];
    $cards = [];
    foreach ($index->query('//div[@id="list"]/a[contains(@class,"j-card")]') as $card) {
        $slug = trim($card->getAttribute('href'), '/');
        $slugs[] = $slug;
        $p = $index->query('.//p', $card)->item(0);
        $cards[$slug] = $p ? trim((string) preg_replace('~\s+~u', ' ', $p->textContent)) : '';
    }
    $home = civilina_html_dom((string) file_get_contents(civilina_home_file()));
    $homeCards = [];
    foreach ($home->query('//div[@class="posts"]/a') as $post) {
        $slug = trim(str_replace('journal/', '', $post->getAttribute('href')), '/');
        $homeCards[$slug] = [
            'title' => trim((string) $home->query('.//h3', $post)->item(0)?->textContent),
            'text' => trim((string) $home->query('.//p', $post)->item(0)?->textContent),
            'cat' => trim((string) $home->query('.//span[@class="cat"]', $post)->item(0)?->textContent),
        ];
    }

    $count = 0;
    foreach (array_reverse($slugs) as $slug) {
        if (civilina_article_find_by_slug($slug)) {
            continue;
        }
        $article = civilina_article_import_page($slug, $cards, $homeCards);
        if ($article) {
            civilina_article_save($article);
            $count++;
        }
    }
    return $count;
}

/* ---------- Files ---------- */

function civilina_write_file(string $path, string $content): void
{
    $dir = dirname($path);
    if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
        throw new RuntimeException('Dossier impossible à créer : ' . $dir);
    }
    $tmp = $path . '.tmp-' . bin2hex(random_bytes(4));
    if (file_put_contents($tmp, $content, LOCK_EX) === false) {
        @unlink($tmp);
        throw new RuntimeException('Écriture impossible : ' . $path);
    }
    // Atomic replace; if the system refuses it (file in use on Windows), plain overwrite.
    if (!@rename($tmp, $path)) {
        $ok = file_put_contents($path, $content, LOCK_EX) !== false;
        @unlink($tmp);
        if (!$ok) {
            throw new RuntimeException('Écriture impossible : ' . $path);
        }
    }
    @chmod($path, 0644);
}

/** Copies the files about to be overwritten into civilina_private/backups/journal/<stamp>/. */
function civilina_backup_files(array $paths, string $label): void
{
    $base = civilina_db_private_dir() . '/backups/journal';
    $dir = $base . '/' . date('Ymd-His') . '-' . preg_replace('~[^a-z0-9-]~', '', $label);
    $root = civilina_site_root();
    foreach ($paths as $path) {
        if (is_file($path)) {
            $target = $dir . '/' . str_replace('/', '__', ltrim(substr($path, strlen($root)), '/\\'));
            if (!is_dir($dir)) {
                mkdir($dir, 0700, true);
            }
            copy($path, $target);
        }
    }
    // Keep the most recent backups only (inode quota of the hosting account).
    $all = glob($base . '/*', GLOB_ONLYDIR) ?: [];
    sort($all);
    foreach (array_slice($all, 0, max(0, count($all) - CIVILINA_BACKUP_KEEP)) as $old) {
        array_map('unlink', glob($old . '/*') ?: []);
        @rmdir($old);
    }
}

function civilina_with_publish_lock(callable $work): mixed
{
    $lock = fopen(civilina_db_private_dir() . '/storage/publish.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        throw new RuntimeException('Publication déjà en cours, réessayez dans un instant.');
    }
    try {
        return $work();
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/* ---------- Lists: Journal page, homepage cards, sitemap ---------- */

function civilina_article_card_date(array $a): string
{
    return $a['date_modified'] !== '' && $a['date_modified'] !== $a['date_published']
        ? 'Mis à jour le ' . civilina_fr_date($a['date_modified'])
        : civilina_fr_date($a['date_published']);
}

function civilina_journal_list_html(array $articles): string
{
    $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    $out = '';
    foreach ($articles as $a) {
        $axis = CIVILINA_ARTICLE_AXES[$a['axis']];
        $out .= "\n      <a class=\"j-card\" data-axis=\"{$axis[1]}\" href=\"{$a['slug']}/\">"
            . "\n        <img src=\"../assets/journal/" . htmlspecialchars(civilina_article_cover_file($a), ENT_QUOTES, 'UTF-8') . '" alt="" loading="lazy">'
            . "\n        <div class=\"in\">"
            . "\n          <span class=\"pill pill-{$axis[1]}\">" . $t(civilina_article_pill($a)) . '</span>'
            . "\n          <h3>" . $t($a['title']) . '</h3>'
            . "\n          <p>" . $t($a['card_text']) . '</p>'
            . "\n          <span class=\"meta\">" . CIVILINA_AUTHOR . ' · ' . $t(civilina_article_card_date($a)) . ' · ' . civilina_article_minutes($a) . ' min</span>'
            . "\n        </div>"
            . "\n      </a>";
    }
    return $out;
}

function civilina_home_posts_html(array $articles): string
{
    $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    $out = '<div class="posts">';
    // The latest article of each axis, newest first: the homepage always shows the three axes.
    $latest = [];
    foreach ($articles as $a) {
        $latest[$a['axis']] ??= $a;
    }
    foreach (array_slice(array_values($latest), 0, 3) as $a) {
        $out .= "\n      <a class=\"post\" href=\"journal/{$a['slug']}/\"><span class=\"cat\">" . $t($a['home_cat'] !== '' ? $a['home_cat'] : civilina_article_pill($a)) . '</span><h3>' . $t($a['home_title'] !== '' ? $a['home_title'] : $a['title']) . '</h3><p>' . $t($a['home_text']) . '</p><span class="more">Lire →</span></a>';
    }
    return $out . "\n    </div>";
}

function civilina_sitemap_journal(string $sitemap, array $articles): string
{
    $sitemap = str_replace("\r\n", "\n", $sitemap);
    $sitemap = (string) preg_replace('~  <url>\n    <loc>https://civilina\.com/journal/[a-z0-9-]+/</loc>\n    <lastmod>[0-9-]+</lastmod>\n  </url>\n~', '', $sitemap);
    $entries = '';
    foreach ($articles as $a) {
        $entries .= "  <url>\n    <loc>https://civilina.com/journal/{$a['slug']}/</loc>\n    <lastmod>" . ($a['date_modified'] ?: $a['date_published']) . "</lastmod>\n  </url>\n";
    }
    $anchor = "  <url>\n    <loc>https://civilina.com/journal/</loc>\n";
    $pos = strpos($sitemap, $anchor);
    if ($pos === false) {
        return (string) preg_replace('~</urlset>~', $entries . '</urlset>', $sitemap);
    }
    $end = strpos($sitemap, "  </url>\n", $pos) + strlen("  </url>\n");
    return substr($sitemap, 0, $end) . $entries . substr($sitemap, $end);
}

/** Rebuilds the three listings from the published articles. @return list<string> written files */
function civilina_articles_rebuild_lists(): array
{
    $root = civilina_site_root();
    $published = civilina_articles_published();
    $written = [];

    $indexFile = $root . '/journal/index.html';
    $index = str_replace("\r\n", "\n", (string) file_get_contents($indexFile));
    $newIndex = (string) preg_replace_callback('~(<div class="j-list" id="list">)(.*?)(\n    </div>\n    <p id="empty")~s', static fn (array $m): string => $m[1] . civilina_journal_list_html($published) . $m[3], $index, 1, $hits);
    if ($hits !== 1) {
        throw new RuntimeException('Liste des articles introuvable dans journal/index.html.');
    }

    $homeFile = civilina_home_file();
    $home = str_replace("\r\n", "\n", (string) file_get_contents($homeFile));
    $newHome = (string) preg_replace_callback('~<div class="posts">.*?\n    </div>~s', static fn (): string => civilina_home_posts_html($published), $home, 1, $hits);
    if ($hits !== 1) {
        throw new RuntimeException('Bloc des articles introuvable sur la page d\'accueil.');
    }

    $sitemapFile = $root . '/sitemap.xml';
    $newSitemap = civilina_sitemap_journal((string) file_get_contents($sitemapFile), $published);

    $changed = [];
    foreach ([[$indexFile, $index, $newIndex], [$homeFile, $home, $newHome], [$sitemapFile, str_replace("\r\n", "\n", (string) file_get_contents($sitemapFile)), $newSitemap]] as [$file, $old, $new]) {
        if ($old !== $new) {
            $changed[$file] = $new;
        }
    }
    if ($changed !== []) {
        civilina_backup_files(array_keys($changed), 'listes');
        foreach ($changed as $file => $content) {
            civilina_write_file($file, $content);
            $written[] = substr($file, strlen($root));
        }
    }
    return $written;
}

/* ---------- Publish / unpublish ---------- */

/** @return array{ok: bool, messages: list<string>} */
function civilina_article_publish(int $id, string $userId, ?string $date = null): array
{
    return civilina_with_publish_lock(static function () use ($id, $userId, $date): array {
        $a = civilina_article_find($id);
        if (!$a) {
            return ['ok' => false, 'messages' => ['Article introuvable.']];
        }
        $problems = civilina_article_problems($a);
        if ($problems !== []) {
            return ['ok' => false, 'messages' => $problems];
        }
        $today = date('Y-m-d');
        $wasPublished = $a['status'] === 'published';
        if (!$wasPublished) {
            $a['date_published'] = $date ?? $today;
            $a['date_modified'] = $a['date_published'];
        } else {
            $a['date_modified'] = $today;
        }
        $a['status'] = 'published';
        $a['publish_at'] = '';
        $a['pending'] = false;
        if ($a['cover']['file'] === '') {
            $a['cover']['file'] = $a['slug'] . '.svg';
        }
        if ($a['related'] === []) {
            $a['related'] = civilina_article_auto_related($a);
        }

        $root = civilina_site_root();
        $page = $root . '/journal/' . $a['slug'] . '/index.html';
        $cover = $root . '/assets/journal/' . $a['cover']['file'];
        $share = $root . '/assets/journal/' . civilina_cover_png_file($a);
        civilina_backup_files([$page, $cover, $share], $a['slug']);
        civilina_write_file($cover, civilina_cover_svg($a));
        // Share image for LinkedIn and the other networks (written before the page, which links it).
        $png = civilina_cover_png($a);
        if ($png !== null) {
            civilina_write_file($share, $png);
        }
        civilina_write_file($page, civilina_article_page($a));
        civilina_article_save($a);
        $lists = civilina_articles_rebuild_lists();
        civilina_audit($wasPublished ? 'article.updated' : 'article.published', $a['slug'], $userId);

        return ['ok' => true, 'messages' => array_merge(['/journal/' . $a['slug'] . '/', '/assets/journal/' . $a['cover']['file']], $lists)];
    });
}

function civilina_article_unpublish(int $id, string $userId): array
{
    return civilina_with_publish_lock(static function () use ($id, $userId): array {
        $a = civilina_article_find($id);
        if (!$a || $a['status'] !== 'published') {
            return ['ok' => false, 'messages' => ['Cet article n\'est pas en ligne.']];
        }
        $root = civilina_site_root();
        $page = $root . '/journal/' . $a['slug'] . '/index.html';
        civilina_backup_files([$page], 'retrait-' . $a['slug']);
        if (is_file($page)) {
            unlink($page);
            @rmdir(dirname($page));
        }
        $a['status'] = 'draft';
        civilina_article_save($a);
        $lists = civilina_articles_rebuild_lists();
        civilina_audit('article.unpublished', $a['slug'], $userId);
        return ['ok' => true, 'messages' => $lists];
    });
}

/** Publishes the scheduled articles whose time has come. Cheap when nothing is due. */
function civilina_articles_publish_due(): int
{
    $statement = civilina_db()->prepare("SELECT id, publish_at FROM articles WHERE status = 'scheduled' AND publish_at != '' AND publish_at <= ?");
    $statement->execute([date('Y-m-d H:i')]);
    $done = 0;
    foreach ($statement->fetchAll() as $row) {
        $result = civilina_article_publish((int) $row['id'], 'programmation', substr((string) $row['publish_at'], 0, 10));
        $done += $result['ok'] ? 1 : 0;
        if (!$result['ok']) {
            civilina_audit('article.schedule_failed', (string) $row['id'] . ' : ' . implode(' ', $result['messages']));
            civilina_db()->prepare("UPDATE articles SET status = 'draft' WHERE id = ?")->execute([$row['id']]);
        }
    }
    return $done;
}

/** Two most recent published articles of the same axis (then any axis). */
function civilina_article_auto_related(array $a): array
{
    $same = [];
    $other = [];
    foreach (civilina_articles_published() as $p) {
        if ($p['slug'] === $a['slug']) {
            continue;
        }
        $pair = [$p['slug'], $p['crumb'] !== '' ? $p['crumb'] : $p['title']];
        if ($p['axis'] === $a['axis']) {
            $same[] = $pair;
        } else {
            $other[] = $pair;
        }
    }
    return array_slice(array_merge($same, $other), 0, 2);
}

/** LinkedIn post draft, in the format of the share kit. */
function civilina_article_linkedin_post(array $a): string
{
    $tags = [
        'engineering' => '#GénieCivil #BTP #QAQC #GestionDeProjet',
        'entreprise' => '#GestionDeProjet #ISO9001 #Organisation #PMP',
        'digital' => '#Digitalisation #BTP #TableauDeBord #GestionDeProjet',
    ];
    $sections = array_map(static fn (array $s): string => '→ ' . html_entity_decode(strip_tags($s['html']), ENT_QUOTES, 'UTF-8'), array_slice(civilina_md_render($a['body'])['toc'], 0, 3));
    $chapo = html_entity_decode(strip_tags(civilina_md_inline($a['chapo'])), ENT_QUOTES, 'UTF-8');
    // First sentence, keeping a closing quotation mark (« … validait. » Cette …).
    $first = preg_split('~(?:(?<=[.!?])|(?<=[.!?] »)|(?<=[.!?]\x{A0}»))\s+(?!»)~u', $chapo)[0] ?? $chapo;
    return $first . "\n\n" . 'Dans l\'article :' . "\n" . implode("\n", $sections) . "\n\n"
        . 'Lien de l\'article en commentaire.' . "\n"
        . 'Lien de partage : https://www.linkedin.com/sharing/share-offsite/?url=' . rawurlencode(civilina_article_url($a)) . "\n"
        . ($tags[$a['axis']] ?? '');
}

/**
 * One-time step of M3 for the articles already online: inserts the newsletter
 * box (before the closing call to action) and the footer link, exactly where
 * the template now writes them. Nothing else in the pages changes; articles
 * published or republished later get them from the template. Idempotent.
 * @return list<string> modified pages
 */
function civilina_articles_insert_newsletter_box(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $cta = "\n\n    <div class=\"art-cta\">";
        $footer = 'Tous les articles</a></li></ul></div>';
        $changed = [];
        foreach (civilina_articles_published() as $a) {
            $page = $root . '/journal/' . $a['slug'] . '/index.html';
            $html = is_file($page) ? str_replace("\r\n", "\n", (string) file_get_contents($page)) : '';
            if ($html === '' || str_contains($html, 'class="nl-box"')) {
                continue;
            }
            if (substr_count($html, $cta) !== 1 || substr_count($html, $footer) !== 1) {
                throw new RuntimeException('Emplacement de la newsletter introuvable dans journal/' . $a['slug'] . '/');
            }
            $html = str_replace($cta, "\n\n" . civilina_newsletter_block('article:' . $a['slug'], '    ') . $cta, $html);
            $changed[$page] = str_replace($footer, 'Tous les articles</a></li><li><a href="/newsletter/">Newsletter</a></li></ul></div>', $html);
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'newsletter-articles');
            foreach ($changed as $page => $html) {
                civilina_write_file($page, $html);
            }
            civilina_audit('article.regenerated', count($changed) . ' pages (bloc newsletter)', $userId);
        }
        return array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed));
    });
}

/**
 * One-time step of M3: adds the newsletter box to the homepage (after the
 * Journal cards) and to the Journal page (after the article list). Idempotent;
 * outside the regions rewritten by civilina_articles_rebuild_lists().
 * @return list<string> modified files
 */
function civilina_newsletter_insert_boxes(): array
{
    return civilina_with_publish_lock(static function (): array {
        $root = civilina_site_root();
        $targets = [
            civilina_home_file() => ['    <p style="margin-top:24px"><a href="journal/" class="btn btn-ghost">Tous les articles →</a></p>', 'accueil'],
            $root . '/journal/index.html' => ['    <p id="empty" class="lead" style="display:none;margin-top:12px">Les premiers articles de cette rubrique sont en cours de rédaction.</p>', 'journal'],
        ];
        $changed = [];
        foreach ($targets as $file => [$anchor, $source]) {
            $html = str_replace("\r\n", "\n", (string) file_get_contents($file));
            if (str_contains($html, 'class="nl-box"')) {
                continue;
            }
            if (substr_count($html, $anchor) !== 1) {
                throw new RuntimeException('Emplacement de la newsletter introuvable dans ' . basename(dirname($file)) . '/' . basename($file));
            }
            $changed[$file] = str_replace($anchor, $anchor . "\n" . civilina_newsletter_block($source, '    '), $html);
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'newsletter');
            foreach ($changed as $file => $html) {
                civilina_write_file($file, $html);
            }
        }
        return array_map(static fn (string $f): string => substr($f, strlen($root)), array_keys($changed));
    });
}
