<?php

declare(strict_types=1);

/*
 * SEO pass of 16/11/2026 (audit after M3):
 * - search titles of 60 characters at most and descriptions of 155 at most;
 * - a 1200 × 630 PNG share image per article and per main page (LinkedIn,
 *   Facebook and X refuse SVG), with width, height, alt and twitter:card;
 * - structured data: image, author, logo and breadcrumb on the articles,
 *   Organization and WebSite on the homepage; a /favicon.ico.
 * Only the head of the pages changes: content, dates and lists stay as they are.
 * Idempotent; every page rewritten is backed up first (civilina_backup_files).
 */

require_once __DIR__ . '/civilina_publish.php';

/** Search title and description of the articles already online. */
const CIVILINA_SEO_ARTICLES = [
    'fiche-indicateur-kpi-modele-commente' => ['Fiche indicateur KPI : modèle commenté | Civilina', 'Construire une fiche indicateur (KPI) utile : rubriques, formule, cible et seuils, indicateurs avancés et de résultat, modèle commenté et checklist.'],
    'matrice-raci-modele-commente' => ['Matrice RACI : modèle commenté et exemple | Civilina', 'Construire une matrice RACI utile : sens de R, A, C et I, règles, étapes, modèle commenté pour un projet de construction, variante RASCI et checklist.'],
    'constituer-doe-dossier-ouvrages-executes' => ['DOE : constituer un dossier complet et utile | Civilina', 'Constituer un DOE (dossier des ouvrages exécutés) : contenu type, plans conformes à l\'exécution, organisation par la WBS, contrôle avant remise et checklist.'],
    'reception-reserves-levee' => ['Réception des travaux et levée des réserves | Civilina', 'Préparer la réception d\'un ouvrage, rédiger des réserves claires et les lever sans perdre la trace : PV, registre des réserves, exemple et checklist.'],
    'construire-wbs-decoupage-projet' => ['WBS : construire un découpage de projet utile | Civilina', 'Construire une WBS utile : règle des 100 %, lots de travaux, niveaux, codification, dictionnaire de la WBS, exemple de bâtiment et checklist.'],
    'changement-edition-norme-projet' => ['Nouvelle édition d\'une norme en cours de projet | Civilina', 'Une norme change d\'édition pendant votre projet : faut-il l\'appliquer ? Analyse d\'impact, décision, mise à jour des ITP et veille. Méthode et checklist.'],
    'decision-applicabilite-norme-projet' => ['Applicabilité d\'une norme : la décision écrite | Civilina', 'Décider si une exigence normative s\'applique à votre projet et le prouver : trois statuts, qui décide, critères, registre d\'applicabilité et checklist.'],
    'lire-une-norme-edition-clause-exigence' => ['Lire une norme : édition, clause et exigence | Civilina', 'Lire une norme technique sans se tromper : référence et édition, structure, exigence, recommandation et note, puis l\'exigence applicable au projet.'],
    'pv-inspection-tracabilite-audit' => ['PV d\'inspection : ce qu\'un auditeur vérifie | Civilina', 'Rédiger un procès-verbal d\'inspection qui tient à l\'audit : contenu type, mesures et critères, signatures, lien avec l\'ITP, exemple commenté et checklist.'],
    'cycle-non-conformite-ncr-constat-cloture' => ['Non-conformité (NCR) : du constat à la clôture | Civilina', 'Gérer une non-conformité de bout en bout : détection, traitement, analyse des causes, action corrective et clôture. Fiche NCR type, exemple et checklist.'],
    'passer-excel-plateforme-gestion' => ['Passer d\'Excel à une plateforme de gestion | Civilina', 'Les signaux qui montrent qu\'Excel ne suffit plus, comment choisir entre Excel, logiciel du marché et plateforme sur mesure, et réussir la transition.'],
    'iso-ou-pmi-structurer-gestion-entreprise' => ['ISO ou PMI : structurer la gestion d\'entreprise | Civilina', 'ISO 9001, ISO 10006, ISO 21502 ou PMI : ce que chaque approche apporte, certifier une organisation ou une personne, et comment choisir ou combiner.'],
    'rediger-plan-assurance-qualite-paq-iso-10005' => ['Plan d\'Assurance Qualité (PAQ) ISO 10005 | Civilina', 'Rédiger un Plan d\'Assurance Qualité (PAQ) selon l\'ISO 10005 : définition, contenu type, étapes, cas des bureaux d\'études, erreurs à éviter et checklist.'],
    'construire-itp-points-arret-temoin-revue' => ['ITP : points d\'arrêt, témoin et revue | Civilina', 'Construire un plan d\'inspection et d\'essais (ITP) : points d\'arrêt (H), de témoin (W) et de revue (R), critères, preuves, exemple commenté et checklist.'],
    'iso-10006-projets-epc-systeme-pilotable' => ['ISO 10006 : management de projet EPC | Civilina', 'Ce que l\'ISO 10006 apporte à un projet EPC : organisation, interfaces études-achats-construction, documents, jalons, risques et indicateurs. Checklist.'],
    'iso-9001-systeme-qualite-utile' => ['ISO 9001 : un système qualité utile | Civilina', 'Faire de l\'ISO 9001 un outil de pilotage : approche processus, fiche processus type, indicateurs, audit interne et revue de direction. Exemple et checklist.'],
    'iso-22000-haccp-premiere-lecture' => ['ISO 22000 et HACCP : première lecture | Civilina', 'Comprendre l\'HACCP et l\'ISO 22000 : dangers, 7 principes et 12 étapes, programmes prérequis, PRPo et CCP, et les leçons pour tout système de contrôle.'],
];

/** Main pages: file (relative to the site root), search title, description, share image. */
const CIVILINA_SEO_PAGES = [
    'home' => ['', 'Civilina — Ingénierie, gestion de projet et digital', 'Civilina accompagne bureaux d\'études et entreprises : gestion de projet ISO 10006, plans qualité, tableaux de bord ISO/PMI et plateformes sur mesure.',
        ['kicker' => 'ENGINEERING · ENTREPRISE · DIGITAL', 'line1' => 'Des projets d\'ingénierie', 'line2' => 'structurés et pilotés', 'subtitle' => 'Méthode ISO et PMI, base normative, plateformes', 'color' => '#003b8c', 'brand' => '']],
    'engineering' => ['engineering/index.html', 'Gestion de projet ISO 10006 et PAQ | CIVILINA Engineering', 'Gestion de projet selon l\'ISO 10006, Plans d\'Assurance Qualité ISO 10005, ITP et base normative : CIVILINA Engineering accompagne les bureaux d\'études.',
        ['kicker' => 'CIVILINA ENGINEERING', 'line1' => 'Gestion de projet', 'line2' => 'ISO 10006 et PAQ', 'subtitle' => 'Pour les bureaux d\'études et les projets EPC', 'color' => '#003b8c', 'brand' => '']],
    'entreprise' => ['gestion-entreprise/index.html', 'Gestion ISO ou PMI et tableaux de bord | CIVILINA Entreprise', 'Structurez la gestion de votre PME selon l\'ISO ou le PMI : diagnostic, processus, RACI, indicateurs et tableaux de bord web et Excel.',
        ['kicker' => 'CIVILINA ENTREPRISE', 'line1' => 'Gestion ISO ou PMI', 'line2' => 'et tableaux de bord', 'subtitle' => 'Processus, RACI, indicateurs, web et Excel', 'color' => '#1d6b4a', 'brand' => '']],
    'digital' => ['digital/index.html', 'Plateformes de gestion de chantiers | CIVILINA Digital', 'Plateformes de gestion sur mesure pour startups et entreprises : suivi des projets et des chantiers, documents, photos, rôles et tableau de bord direction.',
        ['kicker' => 'CIVILINA DIGITAL', 'line1' => 'Plateformes de gestion', 'line2' => 'sur mesure', 'subtitle' => 'Projets, chantiers, documents et tableaux de bord', 'color' => '#006c9e', 'brand' => '']],
    'ia' => ['ia/index.html', 'ITP, WBS, RACI préparés par l\'IA | CIVILINA IA', 'CIVILINA IA prépare des brouillons d\'ITP, de WBS, de RACI et de registres de risques à partir de règles métier, toujours validés par un professionnel.',
        ['kicker' => 'CIVILINA IA', 'line1' => 'ITP, WBS, RACI', 'line2' => 'préparés par l\'IA', 'subtitle' => 'Toujours validés par un professionnel', 'color' => '#4b3a86', 'brand' => '']],
    'journal' => ['journal/index.html', 'Journal : gestion de projet, qualité et QHSE | Civilina', 'Guides pratiques et modèles commentés : management de projet ISO 10006, qualité ISO 9001, QA/QC, ITP, QHSE et tableaux de bord.',
        ['kicker' => 'JOURNAL CIVILINA', 'line1' => 'Guides pratiques', 'line2' => 'projet, qualité, QHSE', 'subtitle' => 'Modèles commentés et checklists', 'color' => '#123f86', 'brand' => 'Journal']],
];

/** Replaces exactly one match, or fails: never a silent partial rewrite. */
function civilina_seo_replace_one(string $pattern, string $replacement, string $html, string $where): string
{
    $out = preg_replace_callback($pattern, static fn (): string => $replacement, $html, 1, $count);
    if ($count !== 1 || $out === null) {
        throw new RuntimeException('Balise introuvable (' . $pattern . ') dans ' . $where);
    }
    return $out;
}

/** Head of a main page: title, description, Open Graph and X tags. */
function civilina_seo_page_head(string $html, string $title, string $description, string $imageUrl, string $alt, string $where): string
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
    $html = civilina_seo_replace_one('~<title>[^<]*</title>~', '<title>' . htmlspecialchars($title, ENT_NOQUOTES, 'UTF-8') . '</title>', $html, $where);
    $html = civilina_seo_replace_one('~<meta name="description" content="[^"]*">~', '<meta name="description" content="' . $e($description) . '">', $html, $where);
    $html = civilina_seo_replace_one('~<meta property="og:title" content="[^"]*">~', '<meta property="og:title" content="' . $e($title) . '">', $html, $where);
    $html = civilina_seo_replace_one('~<meta property="og:description" content="[^"]*">~', '<meta property="og:description" content="' . $e($description) . '">', $html, $where);
    $html = (string) preg_replace('~\n<meta property="og:image:(width|height|alt)" content="[^"]*">|\n<meta name="twitter:card" content="[^"]*">~', '', $html);
    return civilina_seo_replace_one('~<meta property="og:image" content="[^"]*">~', '<meta property="og:image" content="' . $e($imageUrl) . '">'
        . "\n" . '<meta property="og:image:width" content="1200">' . "\n" . '<meta property="og:image:height" content="630">'
        . "\n" . '<meta property="og:image:alt" content="' . $e($alt) . '">' . "\n" . '<meta name="twitter:card" content="summary_large_image">', $html, $where);
}

function civilina_seo_home_jsonld(): string
{
    $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
    return '<script type="application/ld+json">' . "\n" . json_encode(['@context' => 'https://schema.org', '@graph' => [
        ['@type' => 'Organization', '@id' => 'https://civilina.com/#organization', 'name' => 'Civilina', 'url' => 'https://civilina.com/',
            'logo' => 'https://civilina.com/assets/brand/civilina-logo.png', 'sameAs' => ['https://www.linkedin.com/company/civilina'],
            'founder' => ['@type' => 'Person', 'name' => CIVILINA_AUTHOR, 'jobTitle' => 'Ingénieur civil, PMP']],
        ['@type' => 'WebSite', '@id' => 'https://civilina.com/#website', 'url' => 'https://civilina.com/', 'name' => 'Civilina', 'inLanguage' => 'fr', 'publisher' => ['@id' => 'https://civilina.com/#organization']],
    ]], $flags) . "\n" . '</script>';
}

/** Favicon in ICO format (a 48 × 48 PNG inside an ICO container), from the logo. */
function civilina_seo_favicon(string $logo): ?string
{
    if (!function_exists('imagecreatefrompng') || !is_file($logo)) {
        return null;
    }
    $src = imagecreatefrompng($logo);
    $dst = imagecreatetruecolor(48, 48);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 0, 0, 0, 127));
    imagecopyresampled($dst, $src, 0, 0, 0, 0, 48, 48, imagesx($src), imagesy($src));
    ob_start();
    imagepng($dst, null, 9);
    $png = (string) ob_get_clean();
    return pack('vvv', 0, 1, 1) . pack('CCCCvvVV', 48, 48, 0, 0, 1, 32, strlen($png), 22) . $png;
}

/** @return array{files: list<string>, images: int} */
function civilina_seo_apply(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $db = civilina_db();
        $changed = [];
        $images = [];

        foreach (civilina_articles_published() as $a) {
            if (isset(CIVILINA_SEO_ARTICLES[$a['slug']])) {
                [$a['seo_title'], $a['description']] = CIVILINA_SEO_ARTICLES[$a['slug']];
                // Head fields only: no new modification date.
                $db->prepare('UPDATE articles SET data = ?, updated_at = ? WHERE id = ?')
                    ->execute([json_encode(civilina_article_content($a), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), date('c'), $a['id']]);
            }
            $png = $root . '/assets/journal/' . civilina_cover_png_file($a);
            if (!is_file($png) && ($bytes = civilina_cover_png($a)) !== null) {
                $images[$png] = $bytes;
                civilina_write_file($png, $bytes);
            }
            $page = $root . '/journal/' . $a['slug'] . '/index.html';
            $html = str_replace("\r\n", "\n", (string) file_get_contents($page));
            $new = civilina_seo_replace_one('~<title>.*?<meta property="og:locale" content="fr_FR">~s', civilina_article_meta_html($a), $html, 'journal/' . $a['slug']);
            $new = civilina_seo_replace_one('~<script type="application/ld\+json">.*?</script>(\n<script type="application/ld\+json">.*?</script>)?~s', civilina_article_jsonld($a), $new, 'journal/' . $a['slug']);
            if ($new !== $html) {
                $changed[$page] = $new;
            }
        }

        foreach (CIVILINA_SEO_PAGES as $key => [$file, $title, $description, $image]) {
            $path = $file === '' ? civilina_home_file() : $root . '/' . $file;
            $pngName = 'civilina-' . $key . '.png';
            $png = $root . '/assets/brand/share/' . $pngName;
            if (!is_file($png) && ($bytes = civilina_share_png($image)) !== null) {
                $images[$png] = $bytes;
                civilina_write_file($png, $bytes);
            }
            $html = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $alt = trim($image['line1'] . ' ' . $image['line2']) . ' — Civilina';
            $new = civilina_seo_page_head($html, $title, $description, is_file($png) ? 'https://civilina.com/assets/brand/share/' . $pngName : 'https://civilina.com/assets/brand/civilina-logo.png', $alt, $file === '' ? 'accueil' : $file);
            if ($key === 'home' && !str_contains($new, '"@type":"Organization"')) {
                $new = civilina_seo_replace_one('~\n</head>~', "\n" . civilina_seo_home_jsonld() . "\n</head>", $new, 'accueil');
            }
            if ($new !== $html) {
                $changed[$path] = $new;
            }
        }

        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'seo');
            foreach ($changed as $path => $html) {
                civilina_write_file($path, $html);
            }
        }
        $favicon = $root . '/favicon.ico';
        if (!is_file($favicon) && ($ico = civilina_seo_favicon($root . '/assets/brand/civilina-logo.png')) !== null) {
            civilina_write_file($favicon, $ico);
            $images[$favicon] = $ico;
        }
        civilina_audit('seo.applied', count($changed) . ' pages, ' . count($images) . ' images', $userId);
        return ['files' => array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed)), 'images' => count($images)];
    });
}
