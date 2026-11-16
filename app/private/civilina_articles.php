<?php

declare(strict_types=1);

/*
 * Journal articles (milestone M2): storage, page template, cover, publishing.
 *
 * - Articles live in the articles table (schema v3); the content is a JSON
 *   document whose body uses the Civilina writing syntax (civilina_markup.php).
 * - Publishing writes static files, exactly like the hand-made pages:
 *   journal/<slug>/index.html, assets/journal/<cover>.svg, and rebuilds the
 *   article list of journal/index.html, the 3 cards of the homepage and the
 *   Journal entries of sitemap.xml. Overwritten files are backed up first.
 * - Statuses: draft, scheduled (published automatically once due), published.
 */

require_once __DIR__ . '/civilina_db.php';
require_once __DIR__ . '/civilina_markup.php';
require_once __DIR__ . '/civilina_journal.php';

const CIVILINA_ARTICLE_AXES = [
    'engineering' => ['Engineering', 'eng', '/engineering/', 'CIVILINA Engineering'],
    'entreprise' => ['Entreprise', 'ent', '/gestion-entreprise/', 'CIVILINA Entreprise'],
    'digital' => ['Digital', 'dig', '/digital/', 'CIVILINA Digital'],
];

/** Call to action used when the article does not define one. */
const CIVILINA_ARTICLE_CTA = [
    'engineering' => ['Un projet à structurer ?', 'CIVILINA Engineering met en place avec vos équipes le plan qualité, les ITP et la base normative de vos projets.'],
    'entreprise' => ['Structurer votre gestion ?', 'CIVILINA Entreprise organise vos processus, vos responsabilités et vos indicateurs, avec des tableaux de bord web et Excel.'],
    'digital' => ['Une plateforme pour vos projets ?', 'CIVILINA Digital développe des plateformes de gestion sur mesure pour les startups et les entreprises.'],
];

const CIVILINA_ARTICLE_STATUSES = [
    'draft' => 'Brouillon',
    'scheduled' => 'Programmé',
    'published' => 'En ligne',
];

/** Cover gradient colours offered in the editor (second stop of the gradient). */
const CIVILINA_COVER_COLORS = [
    '#003b8c' => 'Bleu Engineering', '#123f86' => 'Bleu nuit', '#1f6a8a' => 'Bleu canard', '#3b4a8f' => 'Indigo',
    '#4b3a86' => 'Violet', '#7a2b3a' => 'Bordeaux', '#6b5a2a' => 'Bronze', '#2d6b52' => 'Vert sapin',
    '#1d6b4a' => 'Vert Entreprise', '#0f5c43' => 'Vert profond', '#0d4f6b' => 'Pétrole', '#006c9e' => 'Bleu Digital',
];

const CIVILINA_AUTHOR = 'Abdelghani Bouhouche';
const CIVILINA_BACKUP_KEEP = 25;

/* ---------- Storage ---------- */

function civilina_article_defaults(): array
{
    return [
        'title' => '', 'seo_title' => '', 'description' => '', 'axis' => 'engineering', 'rubric' => '', 'crumb' => '',
        'headline' => '', 'chapo' => '', 'body' => '', 'sources_title' => '', 'sources' => '', 'sources_note' => '', 'minutes' => 0,
        'cta' => ['title' => '', 'text' => '', 'url' => '', 'label' => ''],
        'cover' => ['file' => '', 'alt' => '', 'kicker' => '', 'line1' => '', 'line2' => '', 'subtitle' => '', 'color' => '#003b8c'],
        'card_text' => '', 'home_cat' => '', 'home_title' => '', 'home_text' => '', 'related' => [], 'pending' => false,
    ];
}

function civilina_article_from_row(array $row): array
{
    $data = json_decode((string) $row['data'], true);
    $article = array_replace_recursive(civilina_article_defaults(), is_array($data) ? $data : []);
    return $article + [
        'id' => (int) $row['id'], 'slug' => (string) $row['slug'], 'status' => (string) $row['status'],
        'publish_at' => (string) $row['publish_at'], 'date_published' => (string) $row['date_published'],
        'date_modified' => (string) $row['date_modified'], 'updated_at' => (string) $row['updated_at'],
    ];
}

function civilina_articles_all(): array
{
    $rows = civilina_db()->query('SELECT * FROM articles ORDER BY CASE status WHEN \'published\' THEN 1 ELSE 0 END, date_published DESC, id DESC')->fetchAll();
    return array_map('civilina_article_from_row', $rows);
}

function civilina_articles_published(): array
{
    $rows = civilina_db()->query("SELECT * FROM articles WHERE status = 'published' ORDER BY date_published DESC, id DESC")->fetchAll();
    return array_map('civilina_article_from_row', $rows);
}

function civilina_article_find(int $id): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM articles WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    return $row ? civilina_article_from_row($row) : null;
}

function civilina_article_find_by_slug(string $slug): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM articles WHERE slug = ?');
    $statement->execute([$slug]);
    $row = $statement->fetch();
    return $row ? civilina_article_from_row($row) : null;
}

function civilina_article_content(array $article): array
{
    return array_intersect_key($article, civilina_article_defaults());
}

function civilina_article_save(array $article): int
{
    $db = civilina_db();
    $now = date('c');
    $json = json_encode(civilina_article_content($article), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (!empty($article['id'])) {
        $db->prepare('UPDATE articles SET slug = ?, status = ?, publish_at = ?, date_published = ?, date_modified = ?, data = ?, updated_at = ? WHERE id = ?')
            ->execute([$article['slug'], $article['status'], $article['publish_at'], $article['date_published'], $article['date_modified'], $json, $now, $article['id']]);
        return (int) $article['id'];
    }
    $db->prepare('INSERT INTO articles(slug, status, publish_at, date_published, date_modified, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([$article['slug'], $article['status'] ?? 'draft', $article['publish_at'] ?? '', $article['date_published'] ?? '', $article['date_modified'] ?? '', $json, $now, $now]);
    return (int) $db->lastInsertId();
}

/* ---------- Derived values ---------- */

function civilina_article_pill(array $a): string
{
    $axis = CIVILINA_ARTICLE_AXES[$a['axis']][0] ?? 'Engineering';
    return trim($a['rubric']) !== '' ? $axis . ' · ' . trim($a['rubric']) : $axis;
}

function civilina_article_minutes(array $a): int
{
    if ((int) $a['minutes'] > 0) {
        return (int) $a['minutes'];
    }
    $words = civilina_md_render($a['body'])['words'] + str_word_count(strip_tags($a['chapo']));
    return max(3, (int) round($words / 155));
}

function civilina_article_cover_file(array $a): string
{
    return $a['cover']['file'] !== '' ? $a['cover']['file'] : $a['slug'] . '.svg';
}

function civilina_fr_date(string $iso): string
{
    return function_exists('civilina_format_date') ? civilina_format_date($iso) : $iso;
}

function civilina_article_url(array $a): string
{
    return 'https://civilina.com/journal/' . $a['slug'] . '/';
}

/** Checks before publishing. @return list<string> */
function civilina_article_problems(array $a): array
{
    $problems = [];
    foreach (['title' => 'le titre', 'description' => 'la description (référencement)', 'chapo' => 'le chapeau', 'body' => 'le corps de l\'article', 'card_text' => 'le texte de la carte du Journal'] as $key => $label) {
        if (trim((string) $a[$key]) === '') {
            $problems[] = 'Renseignez ' . $label . '.';
        }
    }
    if (!preg_match('~^[a-z0-9]+(-[a-z0-9]+)*$~', $a['slug'])) {
        $problems[] = 'L\'adresse (slug) ne doit contenir que des minuscules, des chiffres et des tirets.';
    }
    if (trim($a['cover']['line1']) === '') {
        $problems[] = 'Renseignez au moins la première ligne du titre de la couverture.';
    }
    $render = civilina_md_render($a['body']);
    if (count($render['toc']) < 2) {
        $problems[] = 'Le corps doit contenir au moins deux sections (lignes commençant par « ## »).';
    }
    foreach (['line1', 'line2'] as $line) {
        if (mb_strlen($a['cover'][$line]) > 26) {
            $problems[] = 'Couverture : une ligne de titre dépasse 26 caractères et sortirait de l\'image.';
            break;
        }
    }
    return $problems;
}

/* ---------- Cover ---------- */

function civilina_cover_svg(array $a): string
{
    $x = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES | ENT_XML1, 'UTF-8');
    $c = $a['cover'];
    $color = preg_match('~^#[0-9a-f]{6}$~i', $c['color']) ? $c['color'] : '#003b8c';
    $kicker = $c['kicker'] !== '' ? $c['kicker'] : mb_strtoupper(civilina_article_pill($a));
    $lines = '<tspan x="88" y="262">' . $x($c['line1']) . '</tspan>' . ($c['line2'] !== '' ? '<tspan x="88" y="338">' . $x($c['line2']) . '</tspan>' : '');
    return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 1200 630" width="1200" height="630">' . "\n"
        . '<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#0b1a33"/><stop offset="1" stop-color="' . $color . '"/></linearGradient></defs>' . "\n"
        . '<rect width="1200" height="630" fill="url(#g)"/>' . "\n"
        . '<circle cx="1060" cy="70" r="230" fill="#ffffff" opacity=".06"/>' . "\n"
        . '<circle cx="80" cy="610" r="170" fill="#ffffff" opacity=".05"/>' . "\n"
        . '<g fill="none" stroke="#ffffff" stroke-opacity=".08" stroke-width="2"><path d="M760 630 L1200 190"/><path d="M860 630 L1200 290"/><path d="M960 630 L1200 390"/></g>' . "\n"
        . '<rect x="88" y="112" width="56" height="5" rx="2" fill="#f0b44c"/>' . "\n"
        . '<text x="88" y="172" font-family="Inter,Arial,sans-serif" font-size="30" font-weight="700" fill="#7fd3ff" letter-spacing="1">' . $x($kicker) . '</text>' . "\n"
        . '<text font-family="Inter,Arial,sans-serif" font-size="62" font-weight="800" fill="#ffffff">' . $lines . '</text>' . "\n"
        . '<text x="88" y="418" font-family="Inter,Arial,sans-serif" font-size="30" fill="#c9d6ea">' . $x($c['subtitle']) . '</text>' . "\n"
        . '<text x="88" y="552" font-family="Inter,Arial,sans-serif" font-size="28" font-weight="800" fill="#ffffff" letter-spacing="3">CIVILINA <tspan font-weight="500" letter-spacing="0" fill="#9fb0c8">Journal</tspan></text>' . "\n"
        . '</svg>' . "\n";
}

/* ---------- Share image (PNG for LinkedIn, Facebook, X) ---------- */

/** Social networks do not accept SVG: the same cover is drawn as a 1200 × 630 PNG. */
function civilina_cover_png_file(array $a): string
{
    return (string) preg_replace('~\.svg$~', '', civilina_article_cover_file($a)) . '.png';
}

function civilina_cover_png(array $a): ?string
{
    $c = $a['cover'];
    return civilina_share_png([
        'kicker' => $c['kicker'] !== '' ? $c['kicker'] : mb_strtoupper(civilina_article_pill($a)),
        'line1' => $c['line1'], 'line2' => $c['line2'], 'subtitle' => $c['subtitle'], 'color' => $c['color'], 'brand' => 'Journal',
    ]);
}

/**
 * Draws the Civilina cover layout (see civilina_cover_svg) with GD and the Inter
 * fonts of private/fonts. Returns the PNG bytes, or null when GD or the fonts
 * are missing (publishing then keeps the logo as share image).
 */
function civilina_share_png(array $c): ?string
{
    $fonts = __DIR__ . '/fonts';
    $regular = $fonts . '/Inter-Regular.ttf';
    $bold = $fonts . '/Inter-Bold.ttf';
    $black = $fonts . '/Inter-ExtraBold.ttf';
    if (!function_exists('imagettftext') || !is_file($regular) || !is_file($bold) || !is_file($black)) {
        return null;
    }
    $w = 1200;
    $h = 630;
    $img = imagecreatetruecolor($w, $h);
    imagealphablending($img, true);
    $hex = static fn (string $c): array => sscanf(preg_match('~^#[0-9a-f]{6}$~i', $c) ? $c : '#003b8c', '#%02x%02x%02x');
    $color = static fn (string $c, float $opacity = 1.0): int => imagecolorallocatealpha($img, ...[...$hex($c), (int) round(127 * (1 - $opacity))]);

    // Diagonal gradient #0b1a33 → cover colour (objectBoundingBox: t = (x/w + y/h) / 2).
    [$r1, $g1, $b1] = $hex('#0b1a33');
    [$r2, $g2, $b2] = $hex((string) ($c['color'] ?? '#003b8c'));
    for ($y = 0; $y < $h; $y++) {
        for ($x = 0; $x < $w; $x += 2) {
            $t = ($x / $w + $y / $h) / 2;
            $px = imagecolorallocate($img, (int) ($r1 + ($r2 - $r1) * $t), (int) ($g1 + ($g2 - $g1) * $t), (int) ($b1 + ($b2 - $b1) * $t));
            imagesetpixel($img, $x, $y, $px);
            imagesetpixel($img, $x + 1, $y, $px);
        }
    }
    imagefilledellipse($img, 1060, 70, 460, 460, $color('#ffffff', .06));
    imagefilledellipse($img, 80, 610, 340, 340, $color('#ffffff', .05));
    imagesetthickness($img, 2);
    foreach ([[760, 190], [860, 290], [960, 390]] as [$x0, $y1]) {
        imageline($img, $x0, 630, 1200, $y1, $color('#ffffff', .08));
    }
    imagesetthickness($img, 1);
    imagefilledrectangle($img, 88, 112, 143, 116, $color('#f0b44c'));

    // Sizes in pixels as in the SVG (GD works in points at 96 dpi: pt = px × 0.75).
    $text = static function (float $px, int $x, int $y, int $ink, string $font, string $s, float $spacing = 0) use ($img): int {
        $pt = $px * 0.75;
        if ($spacing == 0) {
            $box = imagettftext($img, $pt, 0, $x, $y, $ink, $font, $s);
            return $box[2];
        }
        foreach (mb_str_split($s) as $ch) {
            $box = imagettftext($img, $pt, 0, $x, $y, $ink, $font, $ch);
            $x = (int) round($box[2] + $spacing);
        }
        return $x;
    };
    $text(30, 88, 172, $color('#7fd3ff'), $bold, (string) $c['kicker'], 1);
    $text(62, 88, 262, $color('#ffffff'), $black, (string) $c['line1']);
    if ((string) $c['line2'] !== '') {
        $text(62, 88, 338, $color('#ffffff'), $black, (string) $c['line2']);
    }
    $text(30, 88, 418, $color('#c9d6ea'), $regular, (string) $c['subtitle']);
    $end = $text(28, 88, 552, $color('#ffffff'), $black, 'CIVILINA', 3);
    if ((string) ($c['brand'] ?? '') !== '') {
        $text(28, $end + 8, 552, $color('#9fb0c8'), $regular, (string) $c['brand']);
    }

    ob_start();
    imagepng($img, null, 9);
    imagedestroy($img);
    return (string) ob_get_clean();
}

/* ---------- Page ---------- */

/** Gated resources: slug => title, axis, file (private/resources), summary, related article. */
const CIVILINA_RESOURCES = [
    'matrice-raci' => [
        'title' => 'Matrice RACI — modèle Excel',
        'axis' => 'entreprise',
        'file' => 'civilina-matrice-raci.xlsx',
        'summary' => 'Activités × rôles, listes R, A, C, I, contrôles automatiques (un seul A par ligne, au moins un R) et exemple commenté pour un projet de construction.',
        'article' => 'matrice-raci-modele-commente',
    ],
    'modele-itp' => [
        'title' => 'Plan d\'inspection et d\'essais (ITP) — modèle Excel',
        'axis' => 'engineering',
        'file' => 'civilina-modele-itp.xlsx',
        'summary' => 'Trame d\'ITP prête à remplir : contrôles, références, critères d\'acceptation, points d\'arrêt (H), de témoin (W) et de revue (R) par intervenant, preuves attendues.',
        'article' => 'construire-itp-points-arret-temoin-revue',
    ],
    'fiche-kpi' => [
        'title' => 'Fiche indicateur (KPI) — modèle Excel',
        'axis' => 'entreprise',
        'file' => 'civilina-fiche-kpi.xlsx',
        'summary' => 'Fiche indicateur complète (définition, formule, source, cible, seuils, responsable) et suivi mensuel avec calcul automatique de l\'état vert, orange ou rouge.',
        'article' => 'fiche-indicateur-kpi-modele-commente',
    ],
];

/** Box promoting the template of an article, before the newsletter box (empty when none). */
function civilina_resource_box(string $articleSlug, string $indent = ''): string
{
    foreach (CIVILINA_RESOURCES as $slug => $r) {
        if ($r['article'] === $articleSlug) {
            $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
            $lines = [
                '<aside class="res-box" aria-label="Modèle gratuit">',
                '  <div><p class="eyebrow">Modèle gratuit</p><h3>' . $t($r['title']) . '</h3><p>' . $t($r['summary']) . '</p></div>',
                '  <a class="btn btn-primary" href="/ressources/#' . $slug . '">Télécharger le modèle →</a>',
                '</aside>',
            ];
            return $indent . implode("
" . $indent, $lines) . "

";
        }
    }
    return '';
}


/** Absolute URL of the PNG share image when it exists on the site, else null. */
function civilina_article_share_image(array $a): ?string
{
    $file = civilina_cover_png_file($a);
    $root = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/\\');
    return $root !== '' && is_file($root . '/assets/journal/' . $file) ? 'https://civilina.com/assets/journal/' . $file : null;
}

/** Title, description, canonical, Open Graph and X (Twitter) tags of an article. */
function civilina_article_meta_html(array $a, bool $preview = false): string
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
    $url = civilina_article_url($a);
    $seoTitle = trim($a['seo_title']) !== '' ? $a['seo_title'] : $a['title'] . ' | Civilina';
    $image = $preview ? null : civilina_article_share_image($a);
    $lines = [
        '<title>' . htmlspecialchars($seoTitle, ENT_NOQUOTES, 'UTF-8') . '</title>',
        '<meta name="description" content="' . $e($a['description']) . '">',
        '<link rel="canonical" href="' . $e($url) . '">',
        '<meta property="og:type" content="article">',
        '<meta property="og:site_name" content="Civilina">',
        '<meta property="og:title" content="' . $e($a['title']) . '">',
        '<meta property="og:description" content="' . $e($a['description']) . '">',
        '<meta property="og:url" content="' . $e($url) . '">',
    ];
    if ($image !== null) {
        $lines[] = '<meta property="og:image" content="' . $e($image) . '">';
        $lines[] = '<meta property="og:image:width" content="1200">';
        $lines[] = '<meta property="og:image:height" content="630">';
        $lines[] = '<meta property="og:image:alt" content="' . $e($a['cover']['alt'] !== '' ? $a['cover']['alt'] : $a['title']) . '">';
        $lines[] = '<meta name="twitter:card" content="summary_large_image">';
    } else {
        $lines[] = '<meta property="og:image" content="https://civilina.com/assets/brand/civilina-logo.png">';
        $lines[] = '<meta name="twitter:card" content="summary">';
    }
    $lines[] = '<meta property="og:locale" content="fr_FR">';
    return implode("\n", $lines);
}

/** Structured data: Article (first, read back by the importer) and BreadcrumbList. */
function civilina_article_jsonld(array $a): string
{
    $url = civilina_article_url($a);
    $published = $a['date_published'] !== '' ? $a['date_published'] : date('Y-m-d');
    $modified = $a['date_modified'] !== '' ? $a['date_modified'] : $published;
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    $article = json_encode([
        '@context' => 'https://schema.org', '@type' => 'Article', 'headline' => trim($a['headline']) !== '' ? $a['headline'] : $a['title'], 'inLanguage' => 'fr',
        'description' => $a['description'],
        'image' => [civilina_article_share_image($a) ?? 'https://civilina.com/assets/journal/' . civilina_article_cover_file($a)],
        'datePublished' => $published, 'dateModified' => $modified,
        'author' => ['@type' => 'Person', 'name' => CIVILINA_AUTHOR, 'jobTitle' => 'Ingénieur civil, PMP', 'url' => 'https://civilina.com/#apropos'],
        'publisher' => ['@type' => 'Organization', 'name' => 'Civilina', 'url' => 'https://civilina.com/', 'logo' => ['@type' => 'ImageObject', 'url' => 'https://civilina.com/assets/brand/civilina-logo.png']],
        'mainEntityOfPage' => $url,
    ], $flags);
    $crumbs = json_encode([
        '@context' => 'https://schema.org', '@type' => 'BreadcrumbList',
        'itemListElement' => [
            ['@type' => 'ListItem', 'position' => 1, 'name' => 'Accueil', 'item' => 'https://civilina.com/'],
            ['@type' => 'ListItem', 'position' => 2, 'name' => 'Journal', 'item' => 'https://civilina.com/journal/'],
            ['@type' => 'ListItem', 'position' => 3, 'name' => $a['crumb'] !== '' ? $a['crumb'] : $a['title'], 'item' => $url],
        ],
    ], $flags);
    return "<script type=\"application/ld+json\">\n" . $article . "\n</script>\n<script type=\"application/ld+json\">\n" . $crumbs . "\n</script>";
}

/**
 * Newsletter sign-up box (milestone M3), posted to /newsletter/. The same HTML is
 * used on the homepage and the Journal page; $source records where people signed up.
 */
function civilina_newsletter_block(string $source, string $indent = ''): string
{
    $source = htmlspecialchars($source, ENT_QUOTES, 'UTF-8');
    $lines = [
        '<aside class="nl-box" id="newsletter" aria-label="Newsletter">',
        '  <div class="nl-intro"><p class="eyebrow">Newsletter</p><h3>Recevez les prochains guides du Journal.</h3><p>Modèles commentés, checklists et retours d\'expérience, selon les thèmes que vous choisissez.</p></div>',
        '  <form class="nl-form" method="post" action="/newsletter/">',
        '    <input type="hidden" name="action" value="subscribe"><input type="hidden" name="source" value="' . $source . '">',
        '    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>',
        '    <div class="nl-line"><input type="email" name="email" required autocomplete="email" maxlength="180" placeholder="Votre e-mail professionnel" aria-label="Votre e-mail"><button class="btn btn-primary" type="submit">S\'inscrire</button></div>',
        '    <fieldset class="nl-axes"><legend>Thèmes</legend><label><input type="checkbox" name="axes[]" value="engineering" checked> Engineering</label><label><input type="checkbox" name="axes[]" value="entreprise" checked> Entreprise</label><label><input type="checkbox" name="axes[]" value="digital" checked> Digital</label></fieldset>',
        '    <p class="nl-legal">Confirmation par e-mail, désinscription en un clic. <a href="/confidentialite/">Confidentialité</a></p>',
        '  </form>',
        '</aside>',
    ];
    return $indent . implode("\n" . $indent, $lines);
}

function civilina_article_page(array $a, bool $preview = false): string
{
    // Attributes are always double-quoted: apostrophes stay literal, as in the hand-made pages.
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
    $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    $axis = CIVILINA_ARTICLE_AXES[$a['axis']] ?? CIVILINA_ARTICLE_AXES['engineering'];
    $url = civilina_article_url($a);
    $published = $a['date_published'] !== '' ? $a['date_published'] : date('Y-m-d');
    $modified = $a['date_modified'] !== '' ? $a['date_modified'] : $published;
    $render = civilina_md_render($a['body']);
    $minutes = civilina_article_minutes($a);
    $cover = '/assets/journal/' . civilina_article_cover_file($a);
    if ($preview) {
        $cover = 'data:image/svg+xml;base64,' . base64_encode(civilina_cover_svg($a));
    }

    $meta = civilina_article_meta_html($a, $preview);
    $jsonLd = civilina_article_jsonld($a);

    $dates = 'Publié le ' . civilina_fr_date($published) . ($modified !== $published ? ' · mis à jour le ' . civilina_fr_date($modified) : '');

    $toc = '';
    foreach ($render['toc'] as $item) {
        $toc .= '        <li><a href="#' . $item['id'] . '">' . $item['html'] . "</a></li>\n";
    }

    $sources = '';
    foreach (preg_split('~\r\n|\r|\n~', $a['sources']) ?: [] as $line) {
        $line = trim((string) preg_replace('~^-\s*~', '', trim($line)));
        if ($line !== '') {
            $sources .= '          <li>' . civilina_md_inline($line) . "</li>\n";
        }
    }
    $sourcesNote = trim($a['sources_note']) !== '' ? "\n        <p style=\"margin-top:.6rem\">" . civilina_md_inline($a['sources_note']) . '</p>' : '';

    $related = '';
    foreach (array_slice($a['related'], 0, 2) as [$slug, $label]) {
        $related .= '<li><a href="/journal/' . $e($slug) . '/">' . $t($label) . '</a></li>';
    }

    $cta = $a['cta'];
    $ctaDefaults = CIVILINA_ARTICLE_CTA[$a['axis']] ?? CIVILINA_ARTICLE_CTA['engineering'];
    $cta['title'] = $cta['title'] !== '' ? $cta['title'] : $ctaDefaults[0];
    $cta['text'] = $cta['text'] !== '' ? $cta['text'] : $ctaDefaults[1];
    $ctaUrl = $cta['url'] !== '' ? $cta['url'] : $axis[2];
    $ctaLabel = $cta['label'] !== '' ? $cta['label'] : 'Découvrir ' . $axis[3] . ' →';
    $share = rawurlencode($url);
    $ads = $preview ? '' : '<script async src="https://pagead2.googlesyndication.com/pagead/js/adsbygoogle.js?client=ca-pub-5595911673357804" crossorigin="anonymous"></script>' . "\n";
    $robots = $preview ? '<meta name="robots" content="noindex, nofollow">' . "\n" : '';
    $pillClass = 'pill-' . $axis[1];
    $inline = 'civilina_md_inline';
    $newsletter = civilina_resource_box($a['slug'], '    ') . civilina_newsletter_block('article:' . $a['slug'], '    ');

    return <<<HTML
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$robots}{$meta}
<meta name="google-adsense-account" content="ca-pub-5595911673357804">
<link rel="icon" type="image/png" href="/assets/brand/civilina-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css">
<script src="/assets/js/theme.js"></script>
{$jsonLd}
{$ads}</head>
<body>

<header>
  <div class="shell nav">
    <a href="/" class="brand"><img src="/assets/brand/civilina-logo.png" alt="">CIVILINA</a>
    <nav class="menu" id="menu">
      <a href="/engineering/"><span class="dot" style="background:var(--eng)"></span>Engineering</a>
      <a href="/gestion-entreprise/"><span class="dot" style="background:var(--ent)"></span>Entreprise</a>
      <a href="/digital/"><span class="dot" style="background:var(--dig)"></span>Digital</a>
      <a href="/ia/"><span class="dot" style="background:var(--ia)"></span>CIVILINA IA</a>
      <a href="/journal/" class="active">Journal</a>
      <a href="/#apropos">À propos</a>
      <a href="/login/" class="menu-login">Connexion</a>
    </nav>
    <button type="button" class="theme-switch" data-theme-toggle aria-label="Mode jour ou nuit" aria-pressed="false"><span class="ts-track"><span class="ts-knob"></span></span><span class="ts-label"></span></button>
    <a href="/login/" class="nav-login">Connexion</a>
    <a href="{$axis[2]}#contact" class="btn btn-primary">Parler de votre projet</a>
    <button class="burger" aria-label="Menu" onclick="document.getElementById('menu').classList.toggle('open')">☰</button>
  </div>
</header>

<main>
<article>
  <div class="art-wrap art-head">
    <p class="crumbs"><a href="/">Accueil</a> › <a href="/journal/">Journal</a> › {$t($a['crumb'] !== '' ? $a['crumb'] : $a['title'])}</p>
    <span class="pill {$pillClass}">{$t(civilina_article_pill($a))}</span>
    <h1>{$t($a['title'])}</h1>
    <div class="art-meta"><span>Par <b>Abdelghani Bouhouche</b>, ingénieur civil, PMP</span><span>{$dates}</span><span>Lecture : {$minutes} min</span></div>
  </div>
  <figure class="art-cover"><img src="{$e($cover)}" alt="{$e($a['cover']['alt'] !== '' ? $a['cover']['alt'] : $a['title'])}"></figure>

  <div class="art-wrap">
    <p class="chapo">{$inline($a['chapo'])}</p>

    <nav class="toc" aria-label="Sommaire">
      <strong>Sommaire</strong>
      <ol>
{$toc}      </ol>
    </nav>

    <div class="prose">
{$render['html']}

      <div class="sources"><strong>{$t(trim($a['sources_title']) !== '' ? $a['sources_title'] : 'Références citées')}</strong>
        <ul>
{$sources}        </ul>{$sourcesNote}
      </div>
    </div>

    <div class="share"><span>Cet article vous a été utile ? Partagez-le.</span><a class="share-li" href="https://www.linkedin.com/sharing/share-offsite/?url={$share}" target="_blank" rel="noopener">Partager sur LinkedIn</a><button type="button" class="share-copy" data-url="{$e($url)}" onclick="var b=this;navigator.clipboard&amp;&amp;navigator.clipboard.writeText(b.dataset.url).then(function(){b.textContent='Lien copié';})">Copier le lien</button></div>

    <div class="author">
      <img src="/assets/brand/abdelghani-bouhouche.jpg" alt="Abdelghani Bouhouche">
      <div><strong>Abdelghani Bouhouche</strong><p>Ingénieur civil et PMP, fondateur de Civilina. Expérience en management de projet, qualité, QHSE et coordination opérationnelle, en Algérie et à l'international.</p></div>
    </div>

{$newsletter}

    <div class="art-cta">
      <h3>{$t($cta['title'])}</h3>
      <p>{$t($cta['text'])}</p>
      <a href="{$e($ctaUrl)}" class="btn">{$t($ctaLabel)}</a>
    </div>
  </div>
</article>
</main>

<footer>
  <div class="shell">
    <div class="foot">
      <div>
        <a href="/" class="brand"><img src="/assets/brand/civilina-logo.png" alt="">CIVILINA</a>
        <p style="margin-top:.8rem;max-width:34ch">Engineering, management et digitalisation pour les projets d'ingénierie.</p>
      </div>
      <div><h5>Axes</h5><ul><li><a href="/engineering/">CIVILINA Engineering</a></li><li><a href="/gestion-entreprise/">CIVILINA Entreprise</a></li><li><a href="/digital/">CIVILINA Digital</a></li><li><a href="/ia/">CIVILINA IA</a></li></ul></div>
      <div><h5>Journal</h5><ul>{$related}<li><a href="/journal/">Tous les articles</a></li><li><a href="/newsletter/">Newsletter</a></li><li><a href="/ressources/">Modèles gratuits</a></li></ul></div>
      <div><h5>Contact</h5><ul><li><a href="/#contact">Demander un diagnostic</a></li><li><a href="https://www.linkedin.com/company/civilina" rel="noopener">LinkedIn</a></li></ul></div>
    </div>
    <div class="legal"><span>© 2026 Civilina</span><span><a href="/mentions-legales/">Mentions légales</a> · <a href="/confidentialite/">Confidentialité</a> · <a href="/cookies/">Cookies</a> · <a href="/conditions-utilisation/">Conditions</a></span></div>
  </div>
</footer>
</body>
</html>

HTML;
}
