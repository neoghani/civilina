<?php

declare(strict_types=1);

/*
 * Sales and prospecting (milestone M4).
 *
 * - Offers: three per axis (entry offer, main offer, support), drafted and
 *   priced in the back-office. "Publish" writes the offers section of the
 *   Engineering, Entreprise and Digital pages between the markers
 *   <!-- civilina:offres --> and <!-- /civilina:offres -->. Prices: "from"
 *   amounts in DA and EUR when filled in, otherwise "on quotation".
 * - Quote requests go through the contact handler (/contact/, with offer_id),
 *   which stores the request and feeds the pipeline (civilina_lead_upsert).
 * - Resources: Excel templates given in exchange for an e-mail address. The
 *   files live in private/resources/ and are served through signed links.
 *   Newsletter registration is a separate, unticked box (double opt-in).
 * - Follow-ups are prepared as e-mails opened in the administrator's own mail
 *   client (mailto), and noted in the lead's history.
 */

require_once __DIR__ . '/civilina_newsletter.php';
require_once __DIR__ . '/civilina_publish.php';

const CIVILINA_OFFER_TIERS = [
    'appel' => 'Offre d\'appel',
    'principale' => 'Offre principale',
    'accompagnement' => 'Accompagnement',
];

/** Axes that sell offers, with the page that shows them. */
const CIVILINA_OFFER_PAGES = [
    'engineering' => 'engineering/index.html',
    'entreprise' => 'gestion-entreprise/index.html',
    'digital' => 'digital/index.html',
];

const CIVILINA_LEAD_SOURCES = [
    'contact' => 'Formulaire de contact',
    'devis' => 'Demande de devis',
    'ressource' => 'Ressource téléchargée',
    'pilote' => 'Candidature programme pilote',
    'manuel' => 'Saisie manuelle',
    'import' => 'Import CSV',
];

const CIVILINA_LEAD_EVENT_TYPES = [
    'note' => 'Note',
    'appel' => 'Appel',
    'rdv' => 'Rendez-vous',
    'email' => 'E-mail',
    'etape' => 'Étape',
    'creation' => 'Création',
    'contact' => 'Demande de contact',
    'devis' => 'Demande de devis',
    'ressource' => 'Ressource',
];

/* CIVILINA_RESOURCES (the gated templates) is defined in civilina_articles.php, whose template links them. */

const CIVILINA_RESOURCE_TTL = 30 * 86400;

/* ---------- Public page layout (/devis/, /ressources/) ---------- */

/**
 * $image: share image in assets/brand/share/; $bodyStyle: axis colours (--c, --c-soft);
 * $bare: full-width sections (the page writes its own <section> blocks).
 */
function civilina_public_start(string $title, string $description, string $canonical, bool $index, string $image = 'civilina-home.png', string $bodyStyle = '', bool $bare = false): void
{
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    $robots = $index ? '' : '<meta name="robots" content="noindex, nofollow">' . "\n";
    $GLOBALS['civilina_public_bare'] = $bare;
    echo '<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
' . $robots . '<title>' . $e($title) . '</title>
<meta name="description" content="' . $e($description) . '">
<link rel="canonical" href="' . $e($canonical) . '">
<meta property="og:type" content="website">
<meta property="og:title" content="' . $e($title) . '">
<meta property="og:description" content="' . $e($description) . '">
<meta property="og:url" content="' . $e($canonical) . '">
<meta property="og:image" content="https://civilina.com/assets/brand/share/' . $e($image) . '">
<meta name="twitter:card" content="summary_large_image">
<link rel="icon" type="image/png" href="/assets/brand/civilina-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css">
<script src="/assets/js/theme.js"></script>
</head>
<body' . ($bodyStyle !== '' ? ' style="' . $e($bodyStyle) . '"' : '') . '>
<header>
  <div class="shell nav">
    <a href="/" class="brand"><img src="/assets/brand/civilina-logo.png" alt="">CIVILINA</a>
    <nav class="menu" id="menu">
      <a href="/engineering/"><span class="dot" style="background:var(--eng)"></span>Engineering</a>
      <a href="/gestion-entreprise/"><span class="dot" style="background:var(--ent)"></span>Entreprise</a>
      <a href="/digital/"><span class="dot" style="background:var(--dig)"></span>Digital</a>
      <a href="/ia/"><span class="dot" style="background:var(--ia)"></span>CIVILINA IA</a>
      <a href="/journal/">Journal</a>
      <a href="/#apropos">À propos</a>
      <a href="/login/" class="menu-login">Connexion</a>
    </nav>
    <button type="button" class="theme-switch" data-theme-toggle aria-label="Mode jour ou nuit" aria-pressed="false"><span class="ts-track"><span class="ts-knob"></span></span><span class="ts-label"></span></button>
    <a href="/login/" class="nav-login">Connexion</a>
    <a href="/#contact" class="btn btn-primary">Parler de votre projet</a>
    <button class="burger" aria-label="Menu" onclick="document.getElementById(\'menu\').classList.toggle(\'open\')">☰</button>
  </div>
</header>
' . ($bare ? '<main>
' : '<main class="nl-page">
  <div class="shell">
');
}

function civilina_public_end(): void
{
    echo (empty($GLOBALS['civilina_public_bare']) ? '
  </div>
</main>' : '
</main>') . '
<footer>
  <div class="shell">
    <div class="foot">
      <div>
        <a href="/" class="brand"><img src="/assets/brand/civilina-logo.png" alt="">CIVILINA</a>
        <p style="margin-top:.8rem;max-width:34ch">Engineering, management et digitalisation pour les projets d\'ingénierie.</p>
      </div>
      <div><h5>Axes</h5><ul><li><a href="/engineering/">CIVILINA Engineering</a></li><li><a href="/gestion-entreprise/">CIVILINA Entreprise</a></li><li><a href="/digital/">CIVILINA Digital</a></li><li><a href="/ia/">CIVILINA IA</a></li></ul></div>
      <div><h5>Ressources</h5><ul><li><a href="/bureaux-etudes/">Espace bureaux d\'études</a></li><li><a href="/journal/">Journal</a></li><li><a href="/ressources/">Modèles gratuits</a></li><li><a href="/newsletter/">Newsletter</a></li></ul></div>
      <div><h5>Contact</h5><ul><li><a href="/devis/">Demander un devis</a></li><li><a href="/#contact">Demander un diagnostic</a></li><li><a href="https://www.linkedin.com/company/civilina" rel="noopener">LinkedIn</a></li></ul></div>
    </div>
    <div class="legal"><span>© 2026 Civilina</span><span><a href="/mentions-legales/">Mentions légales</a> · <a href="/confidentialite/">Confidentialité</a> · <a href="/cookies/">Cookies</a> · <a href="/conditions-utilisation/">Conditions</a></span></div>
  </div>
</footer>
</body>
</html>';
}

/* ---------- Offers ---------- */

function civilina_offer_defaults(): array
{
    return [
        'title' => '', 'tagline' => '', 'for_who' => '', 'deliverables' => '', 'duration' => '',
        'price_da' => '', 'price_eur' => '', 'price_note' => 'HT', 'cta_label' => 'Demander un devis',
    ];
}

function civilina_offer_from_row(array $row): array
{
    $data = json_decode((string) $row['data'], true);
    return array_replace(civilina_offer_defaults(), is_array($data) ? $data : []) + [
        'id' => (int) $row['id'], 'axis' => (string) $row['axis'], 'tier' => (string) $row['tier'],
        'position' => (int) $row['position'], 'status' => (string) $row['status'], 'updated_at' => (string) $row['updated_at'],
    ];
}

function civilina_offers_all(): array
{
    $rows = civilina_db()->query("SELECT * FROM offers ORDER BY CASE axis WHEN 'engineering' THEN 1 WHEN 'entreprise' THEN 2 ELSE 3 END, position, id")->fetchAll();
    return array_map('civilina_offer_from_row', $rows);
}

function civilina_offer_find(int $id): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM offers WHERE id = ?');
    $statement->execute([$id]);
    $row = $statement->fetch();
    return $row ? civilina_offer_from_row($row) : null;
}

/** @return list<array> published offers of an axis (all axes when $axis is ''). */
function civilina_offers_published(string $axis = ''): array
{
    return array_values(array_filter(civilina_offers_all(), static fn (array $o): bool => $o['status'] === 'published' && ($axis === '' || $o['axis'] === $axis)));
}

function civilina_offer_save(array $o): int
{
    $db = civilina_db();
    $now = date('c');
    $data = json_encode(array_intersect_key($o, civilina_offer_defaults()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $position = array_search($o['tier'], array_keys(CIVILINA_OFFER_TIERS), true);
    if (!empty($o['id'])) {
        $db->prepare('UPDATE offers SET axis = ?, tier = ?, position = ?, status = ?, data = ?, updated_at = ? WHERE id = ?')
            ->execute([$o['axis'], $o['tier'], (int) $position, $o['status'], $data, $now, $o['id']]);
        return (int) $o['id'];
    }
    $db->prepare('INSERT INTO offers(axis, tier, position, status, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?)')
        ->execute([$o['axis'], $o['tier'], (int) $position, $o['status'] ?? 'draft', $data, $now, $now]);
    return (int) $db->lastInsertId();
}

function civilina_money(int $amount): string
{
    return number_format($amount, 0, ',', "\u{202F}");
}

/** "À partir de 150 000 DA · 1 000 € HT", or "Sur devis". */
function civilina_offer_price(array $o): string
{
    $parts = [];
    if ((int) $o['price_da'] > 0) {
        $parts[] = civilina_money((int) $o['price_da']) . "\u{00A0}DA";
    }
    if ((int) $o['price_eur'] > 0) {
        $parts[] = civilina_money((int) $o['price_eur']) . "\u{00A0}€";
    }
    if ($parts === []) {
        return 'Sur devis';
    }
    return 'À partir de ' . implode(' · ', $parts) . (trim($o['price_note']) !== '' ? ' ' . trim($o['price_note']) : '');
}

function civilina_offer_problems(array $o): array
{
    $problems = [];
    if (trim($o['title']) === '') {
        $problems[] = 'Renseignez le nom de l\'offre.';
    }
    if (trim($o['tagline']) === '') {
        $problems[] = 'Renseignez la phrase de présentation.';
    }
    if (trim($o['deliverables']) === '') {
        $problems[] = 'Listez au moins un livrable.';
    }
    return $problems;
}

/** Offers section of an axis page (empty when the axis has no published offer). */
function civilina_offers_section_html(string $axis): string
{
    $offers = civilina_offers_published($axis);
    if ($offers === []) {
        return '';
    }
    $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    $e = static fn (string $s): string => htmlspecialchars($s, ENT_COMPAT, 'UTF-8');
    $axisName = CIVILINA_ARTICLE_AXES[$axis][3] ?? 'Civilina';
    $cards = '';
    foreach ($offers as $o) {
        $items = '';
        foreach (preg_split('~\R~u', trim($o['deliverables'])) ?: [] as $line) {
            $line = trim((string) preg_replace('~^[-•*]\s*~u', '', $line));
            if ($line !== '') {
                $items .= "\n          <li>" . $t($line) . '</li>';
            }
        }
        $meta = trim($o['duration']) !== '' ? "\n        <p class=\"price-meta\">Durée indicative : " . $t($o['duration']) . '</p>' : '';
        $forWho = trim($o['for_who']) !== '' ? "\n        <p class=\"price-for\">" . $t($o['for_who']) . '</p>' : '';
        $cards .= "\n      <article class=\"price-card price-" . $e($o['tier']) . '">'
            . "\n        <span class=\"price-tier\">" . $t(CIVILINA_OFFER_TIERS[$o['tier']] ?? '') . '</span>'
            . "\n        <h3>" . $t($o['title']) . '</h3>'
            . "\n        <p class=\"price-tagline\">" . $t($o['tagline']) . '</p>' . $forWho
            . "\n        <ul class=\"list\">" . $items . "\n        </ul>" . $meta
            . "\n        <p class=\"price-amount\">" . $t(civilina_offer_price($o)) . '</p>'
            . "\n        <a class=\"btn btn-primary\" href=\"/devis/?offre=" . (int) $o['id'] . '">' . $t($o['cta_label'] !== '' ? $o['cta_label'] : 'Demander un devis') . ' →</a>'
            . "\n      </article>";
    }
    return "<section id=\"offres\" class=\"alt\">\n  <div class=\"shell\">\n    <div class=\"section-head\">\n      <p class=\"eyebrow\">Offres " . $t($axisName) . "</p>\n      <h2>Choisissez votre point de départ.</h2>\n      <p class=\"lead\">Une première étape courte pour cadrer, une mission complète, ou un accompagnement dans la durée. Chaque devis est établi après un échange sur votre contexte.</p>\n    </div>\n    <div class=\"pricing\">"
        . $cards . "\n    </div>\n  </div>\n</section>";
}

/** Writes the offers section of the three axis pages. @return list<string> modified pages */
function civilina_offers_publish(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $changed = [];
        foreach (CIVILINA_OFFER_PAGES as $axis => $file) {
            $path = $root . '/' . $file;
            $html = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $start = '<!-- civilina:offres -->';
            $end = '<!-- /civilina:offres -->';
            $block = $start . "\n" . civilina_offers_section_html($axis) . "\n" . $end;
            if (str_contains($html, $start)) {
                $new = (string) preg_replace_callback('~<!-- civilina:offres -->.*?<!-- /civilina:offres -->~s', static fn (): string => $block, $html, 1);
            } else {
                if (substr_count($html, '<section id="contact">') !== 1) {
                    throw new RuntimeException('Section contact introuvable dans ' . $file);
                }
                $new = str_replace('<section id="contact">', $block . "\n\n<section id=\"contact\">", $html);
            }
            if ($new !== $html) {
                $changed[$path] = $new;
            }
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'offres');
            foreach ($changed as $path => $html) {
                civilina_write_file($path, $html);
            }
        }
        civilina_audit('offers.published', count(civilina_offers_published()) . ' offres', $userId);
        return array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed));
    });
}

/* ---------- Leads ---------- */

function civilina_lead_find(int $id): ?array
{
    $statement = civilina_db()->prepare('SELECT * FROM leads WHERE id = ?');
    $statement->execute([$id]);
    return $statement->fetch() ?: null;
}

function civilina_lead_events(int $id): array
{
    $statement = civilina_db()->prepare('SELECT e.*, u.email AS user_email FROM lead_events e LEFT JOIN users u ON u.id = e.user_id WHERE e.lead_id = ? ORDER BY e.id DESC');
    $statement->execute([$id]);
    return $statement->fetchAll();
}

function civilina_lead_event(int $leadId, string $type, string $detail, string $userId): void
{
    $db = civilina_db();
    $db->prepare('INSERT INTO lead_events(lead_id, created_at, user_id, type, detail) VALUES (?, ?, ?, ?, ?)')
        ->execute([$leadId, date('c'), $userId, $type, mb_substr($detail, 0, 2000)]);
    $db->prepare('UPDATE leads SET updated_at = ? WHERE id = ?')->execute([date('c'), $leadId]);
}

/** Pipeline figures for the dashboard and the prospecting screen. */
function civilina_leads_summary(): array
{
    $db = civilina_db();
    $byStage = $db->query('SELECT stage, COUNT(*) FROM leads GROUP BY stage')->fetchAll(PDO::FETCH_KEY_PAIR);
    $today = date('Y-m-d');
    $due = $db->prepare("SELECT COUNT(*) FROM leads WHERE stage NOT IN ('gagne', 'perdu') AND next_action_at != '' AND next_action_at <= ?");
    $due->execute([$today]);
    $late = $db->prepare("SELECT COUNT(*) FROM leads WHERE stage NOT IN ('gagne', 'perdu') AND next_action_at != '' AND next_action_at < ?");
    $late->execute([$today]);
    $value = (int) $db->query("SELECT COALESCE(SUM(value_da), 0) FROM leads WHERE stage = 'proposition'")->fetchColumn();
    $won = $db->prepare("SELECT COUNT(*), COALESCE(SUM(value_da), 0) FROM leads WHERE stage = 'gagne' AND updated_at >= ?");
    $won->execute([date('c', strtotime('-90 days'))]);
    [$wonCount, $wonValue] = $won->fetch(PDO::FETCH_NUM);
    $open = 0;
    foreach (['nouveau', 'qualifie', 'proposition'] as $stage) {
        $open += (int) ($byStage[$stage] ?? 0);
    }
    return [
        'stages' => array_map('intval', $byStage) + array_fill_keys(array_keys(CIVILINA_LEAD_STAGES), 0),
        'open' => $open, 'due' => (int) $due->fetchColumn(), 'late' => (int) $late->fetchColumn(),
        'proposal_value' => $value, 'won_90d' => (int) $wonCount, 'won_value_90d' => (int) $wonValue,
    ];
}

/** E-mail templates for follow-ups (opened in the administrator's own mail client). */
function civilina_lead_templates(array $lead): array
{
    $first = trim((string) strtok(trim((string) $lead['name']), ' '));
    $hello = $first !== '' ? 'Bonjour ' . $first . ',' : 'Bonjour,';
    $offer = (int) $lead['offer_id'] > 0 ? civilina_offer_find((int) $lead['offer_id']) : null;
    $subject = $offer ? $offer['title'] : (CIVILINA_AXES[$lead['axis']] ?? 'Civilina');
    $sign = "\n\nBien cordialement,\nAbdelghani Bouhouche\nIngénieur civil, PMP — Civilina\nhttps://civilina.com";
    return [
        'premier' => ['Premier contact', 'Votre demande Civilina — ' . $subject,
            $hello . "\n\nMerci pour votre demande concernant « " . $subject . " ». Pour vous proposer une démarche adaptée, je vous propose un échange de 30 minutes afin de comprendre votre contexte, vos délais et vos priorités.\n\nQuelles sont vos disponibilités cette semaine ?" . $sign],
        'proposition' => ['Relance après proposition', 'Suite à notre proposition — ' . $subject,
            $hello . "\n\nJe reviens vers vous au sujet de la proposition transmise pour « " . $subject . " ». Avez-vous pu la consulter ? Je reste disponible pour l'ajuster à votre calendrier ou à votre budget, ou pour en parler de vive voix." . $sign],
        'ressource' => ['Suite à un téléchargement', 'Votre modèle Civilina — une question ?',
            $hello . "\n\nVous avez téléchargé l'un de nos modèles sur civilina.com. Est-il adapté à votre usage ? Si vous le souhaitez, nous pouvons regarder ensemble comment l'appliquer à vos projets ou à votre organisation, lors d'un court échange sans engagement." . $sign],
    ];
}

/** mailto: URL (RFC 6068, %20 for spaces). */
function civilina_mailto(string $email, string $subject, string $body): string
{
    return 'mailto:' . rawurlencode($email) . '?subject=' . rawurlencode($subject) . '&body=' . rawurlencode(str_replace("\n", "\r\n", $body));
}

/**
 * CSV import (separator ; or ,): nom, entreprise, fonction, email, telephone, axe, notes.
 * The first line is a header. Addresses already in an open lead are skipped.
 * @return array{added: int, skipped: int, errors: list<string>}
 */
function civilina_leads_import_csv(string $csv, string $userId): array
{
    $csv = (string) preg_replace('~^\xEF\xBB\xBF~', '', $csv);
    $lines = array_values(array_filter(preg_split('~\r\n|\r|\n~', $csv) ?: [], static fn (string $l): bool => trim($l) !== ''));
    $result = ['added' => 0, 'skipped' => 0, 'errors' => []];
    if (count($lines) < 2) {
        $result['errors'][] = 'Le fichier doit contenir une ligne d\'en-tête et au moins une ligne.';
        return $result;
    }
    $sep = substr_count($lines[0], ';') >= substr_count($lines[0], ',') ? ';' : ',';
    $header = array_map(static fn (string $h): string => mb_strtolower(trim($h)), str_getcsv($lines[0], $sep, '"', ''));
    $col = static function (array $row, array $names) use ($header): string {
        foreach ($names as $name) {
            $i = array_search($name, $header, true);
            if ($i !== false && isset($row[$i])) {
                return trim((string) $row[$i]);
            }
        }
        return '';
    };
    $db = civilina_db();
    $axisAliases = ['engineering' => 'engineering', 'entreprise' => 'entreprise', 'digital' => 'digital', 'ia' => 'ia'];
    foreach (array_slice($lines, 1, 2000) as $n => $line) {
        $row = str_getcsv($line, $sep, '"', '');
        $email = mb_strtolower($col($row, ['email', 'e-mail', 'mail', 'courriel']));
        $name = $col($row, ['nom', 'name', 'contact']);
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $result['errors'][] = 'Ligne ' . ($n + 2) . ' : e-mail non valide (' . $email . ').';
            continue;
        }
        if ($email === '' && $name === '') {
            $result['errors'][] = 'Ligne ' . ($n + 2) . ' : ni nom ni e-mail.';
            continue;
        }
        if ($email !== '') {
            $exists = $db->prepare("SELECT COUNT(*) FROM leads WHERE email = ? AND stage NOT IN ('gagne', 'perdu')");
            $exists->execute([$email]);
            if ((int) $exists->fetchColumn() > 0) {
                $result['skipped']++;
                continue;
            }
        }
        $axis = $axisAliases[mb_strtolower($col($row, ['axe', 'axis']))] ?? 'a_qualifier';
        civilina_lead_upsert([
            'source' => 'import', 'axis' => $axis, 'name' => $name, 'company' => $col($row, ['entreprise', 'société', 'societe', 'company']),
            'position' => $col($row, ['fonction', 'poste', 'position']), 'email' => $email, 'phone' => $col($row, ['telephone', 'téléphone', 'tel', 'phone']),
            'notes' => $col($row, ['notes', 'note', 'commentaire']),
        ], 'creation', 'Import CSV', $db, $userId);
        $result['added']++;
    }
    civilina_audit('leads.imported', $result['added'] . ' ajoutés, ' . $result['skipped'] . ' doublons', $userId);
    return $result;
}

/* ---------- Resources ---------- */

function civilina_resource_path(string $slug): ?string
{
    $file = CIVILINA_RESOURCES[$slug]['file'] ?? '';
    $path = __DIR__ . '/resources/' . $file;
    return $file !== '' && is_file($path) ? $path : null;
}

function civilina_resource_token(int $id): string
{
    $secret = civilina_setting('newsletter_secret');
    if (strlen($secret) < 32) {
        throw new RuntimeException('Secret manquant.');
    }
    return $id . '.' . substr(hash_hmac('sha256', 'resource|' . $id, $secret), 0, 32);
}

function civilina_resource_url(int $id): string
{
    return civilina_site_url() . '/ressources/?t=' . civilina_resource_token($id);
}

/**
 * Registers a request for a resource: the download link, the lead, and the
 * newsletter registration when the visitor ticked the box (double opt-in).
 * @return array{state: string, id: int} state: ok | invalid
 */
function civilina_resource_request(string $slug, string $email, string $firstName, string $company, bool $newsletter): array
{
    $email = civilina_nl_normalize_email($email);
    if (!isset(CIVILINA_RESOURCES[$slug]) || !civilina_nl_valid_email($email)) {
        return ['state' => 'invalid', 'id' => 0];
    }
    $resource = CIVILINA_RESOURCES[$slug];
    $firstName = mb_substr(trim((string) preg_replace('~\s+~u', ' ', $firstName)), 0, 80);
    $company = mb_substr(trim((string) preg_replace('~\s+~u', ' ', $company)), 0, 160);
    $db = civilina_db();
    $leadId = civilina_lead_upsert(['source' => 'ressource', 'source_ref' => $slug, 'axis' => $resource['axis'], 'name' => $firstName, 'company' => $company, 'email' => $email], 'ressource', 'Ressource demandée : ' . $resource['title'], $db);
    $db->prepare('INSERT INTO resource_downloads(created_at, resource, email, first_name, company, lead_id, newsletter, ip_hash) VALUES (?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([date('c'), $slug, $email, $firstName, $company, $leadId, $newsletter ? 1 : 0, civilina_hash(civilina_client_ip())]);
    $id = (int) $db->lastInsertId();
    if ($newsletter) {
        civilina_nl_subscribe($email, $firstName, [$resource['axis']], 'ressource:' . $slug);
    }
    // The link is shown on the page; it is also e-mailed when sending is configured.
    if (civilina_nl_ready() && civilina_mail_quota_left() > 0) {
        $url = civilina_resource_url($id);
        $hello = $firstName !== '' ? 'Bonjour ' . $firstName . ',' : 'Bonjour,';
        $inner = '<h1 style="margin:8px 0 14px;font-size:22px;line-height:1.3;color:#102033">' . civilina_mail_e($resource['title']) . '</h1>'
            . '<p style="margin:0 0 14px;font-size:16px;line-height:1.6;color:#252a31">' . civilina_mail_e($hello) . ' voici votre modèle. Le lien reste valable 30 jours.</p>'
            . '<p style="margin:22px 0"><a href="' . civilina_mail_e($url) . '" style="display:inline-block;background:#003b8c;color:#ffffff;text-decoration:none;font-weight:700;font-size:16px;padding:13px 22px;border-radius:999px">Télécharger le modèle</a></p>'
            . '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#5d6673">Le guide qui l\'accompagne : <a style="color:#003b8c" href="' . civilina_mail_e(civilina_site_url() . '/journal/' . $resource['article'] . '/') . '">lire l\'article</a>.</p>';
        $text = $hello . "\n\nVoici votre modèle (lien valable 30 jours) :\n" . $url . "\n\n" . civilina_nl_config()['legal'];
        civilina_mail_send($email, $firstName, $resource['title'] . ' — votre modèle Civilina', civilina_mail_shell('Votre modèle Civilina est prêt.', $inner, civilina_mail_e(civilina_nl_config()['legal'])), $text, ['kind' => 'ressource', 'tags' => ['civilina-ressource']]);
    }
    return ['state' => 'ok', 'id' => $id];
}

/** @return array{row: ?array, state: string} state: ok | expired | unknown */
function civilina_resource_from_token(string $token): array
{
    if (!preg_match('~^(\d{1,10})\.([a-f0-9]{32})$~', $token, $m) || !hash_equals(civilina_resource_token((int) $m[1]), $token)) {
        return ['row' => null, 'state' => 'unknown'];
    }
    $statement = civilina_db()->prepare('SELECT * FROM resource_downloads WHERE id = ?');
    $statement->execute([(int) $m[1]]);
    $row = $statement->fetch() ?: null;
    if (!$row) {
        return ['row' => null, 'state' => 'unknown'];
    }
    if (strtotime($row['created_at']) < time() - CIVILINA_RESOURCE_TTL) {
        return ['row' => $row, 'state' => 'expired'];
    }
    return ['row' => $row, 'state' => 'ok'];
}

function civilina_resource_mark_downloaded(int $id): void
{
    civilina_db()->prepare('UPDATE resource_downloads SET downloads = downloads + 1, last_download_at = ? WHERE id = ?')->execute([date('c'), $id]);
}

function civilina_resources_stats(): array
{
    $rows = civilina_db()->query('SELECT resource, COUNT(*) AS requests, SUM(CASE WHEN downloads > 0 THEN 1 ELSE 0 END) AS downloaded, SUM(newsletter) AS optins FROM resource_downloads GROUP BY resource')->fetchAll();
    $out = [];
    foreach (CIVILINA_RESOURCES as $slug => $r) {
        $out[$slug] = ['requests' => 0, 'downloaded' => 0, 'optins' => 0];
    }
    foreach ($rows as $row) {
        $out[$row['resource']] = ['requests' => (int) $row['requests'], 'downloaded' => (int) $row['downloaded'], 'optins' => (int) $row['optins']];
    }
    return $out;
}

/* ---------- One-time setup of M4 ---------- */

/** Adds the "free template" box to the articles already online that have one. @return list<string> */
function civilina_articles_insert_resource_boxes(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $changed = [];
        foreach (civilina_articles_published() as $a) {
            $box = civilina_resource_box($a['slug'], '    ');
            $page = $root . '/journal/' . $a['slug'] . '/index.html';
            if ($box === '' || !is_file($page)) {
                continue;
            }
            $html = str_replace("\r\n", "\n", (string) file_get_contents($page));
            $anchor = "\n\n    <aside class=\"nl-box\"";
            if (str_contains($html, 'class="res-box"')) {
                continue;
            }
            if (substr_count($html, $anchor) !== 1) {
                throw new RuntimeException('Bloc newsletter introuvable dans journal/' . $a['slug'] . '/');
            }
            $changed[$page] = str_replace($anchor, "\n\n" . rtrim($box, "\n") . $anchor, $html);
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'modeles');
            foreach ($changed as $page => $html) {
                civilina_write_file($page, $html);
            }
            civilina_audit('article.regenerated', count($changed) . ' pages (encart modèle)', $userId);
        }
        return array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed));
    });
}

/** Draft offers (three per axis), written once; prices are left to the administrator. */
function civilina_offers_seed(): int
{
    if ((int) civilina_db()->query('SELECT COUNT(*) FROM offers')->fetchColumn() > 0) {
        return 0;
    }
    $drafts = [
        ['engineering', 'appel', 'Diagnostic qualité projet', 'Un regard extérieur sur l\'organisation qualité d\'un projet ou d\'un bureau d\'études, avec un plan d\'actions priorisé.', 'Bureaux d\'études et entreprises qui préparent un projet ou un audit client.', "Entretien de cadrage et revue documentaire (PAQ, ITP, procédures)\nAnalyse des écarts par rapport à l'ISO 10005, à l'ISO 10006 et au marché\nRapport de diagnostic et plan d'actions priorisé\nRestitution d'une heure avec votre équipe", '1 à 2 semaines'],
        ['engineering', 'principale', 'Plan d\'Assurance Qualité de projet', 'Le PAQ de votre projet et les ITP de ses principaux lots, rédigés avec vos équipes et prêts à être présentés au client.', 'Projets de construction, d\'infrastructure et projets EPC.', "PAQ rédigé selon les lignes directrices de l'ISO 10005, adapté au marché\nITP des principaux lots : points H, W et R, critères et preuves\nModèles de PV d'inspection, de fiche de non-conformité et de registres\nPrésentation du PAQ à l'équipe projet et au client", '3 à 6 semaines'],
        ['engineering', 'accompagnement', 'Accompagnement qualité de projet', 'Un responsable qualité à temps partagé pour faire vivre le PAQ jusqu\'à la réception.', '', "Revue mensuelle des indicateurs qualité et des non-conformités\nMise à jour du PAQ et des ITP\nAudit interne de projet chaque trimestre\nPréparation de la réception et du DOE", 'Mensuel, 3 mois minimum'],
        ['entreprise', 'appel', 'Diagnostic organisationnel', 'Une photographie claire de votre organisation : ce qui fonctionne, ce qui bloque, et par où commencer.', 'Dirigeants de PME et responsables d\'organisation.', "Entretiens avec la direction et les responsables\nCartographie des processus actuels\nPoints forts, écarts et risques\nFeuille de route priorisée, selon l'ISO ou le PMI", '1 à 2 semaines'],
        ['entreprise', 'principale', 'Processus, responsabilités et indicateurs', 'Des processus écrits, des responsabilités claires et un tableau de bord qui permet enfin de décider sur des chiffres fiables.', '', "Cartographie et fiches processus\nMatrice RACI des activités clés\n5 à 8 indicateurs avec leur fiche (définition, formule, cible)\nTableau de bord de direction, Excel ou web", '4 à 8 semaines'],
        ['entreprise', 'accompagnement', 'Pilotage mensuel', 'Une revue mensuelle avec la direction pour que le système reste utilisé, et pas seulement documenté.', '', "Revue mensuelle du tableau de bord avec la direction\nSuivi du plan d'actions\nPréparation des audits, dont l'ISO 9001 le cas échéant\nAjustement des indicateurs", 'Mensuel, 3 mois minimum'],
        ['digital', 'appel', 'Cadrage et maquette', 'Vous voyez votre outil avant qu\'il soit construit : besoins, écrans principaux et budget par lots.', 'Startups et entreprises qui veulent sortir d\'Excel et de la messagerie.', "Atelier de cadrage des besoins et des utilisateurs\nMaquette cliquable des parcours et écrans principaux\nDécoupage en lots, estimation et planning\nRecommandation technique et d'hébergement", '2 à 3 semaines'],
        ['digital', 'principale', 'Plateforme de gestion sur mesure', 'Votre plateforme de suivi des projets et des chantiers, construite par lots et testée avec vos utilisateurs.', '', "Développement par lots : projets, journal de chantier, documents, actions\nAccès par rôle et tableau de bord de direction\nRecette avec vos utilisateurs\nMise en ligne, formation et documentation", 'Par lots de 4 à 6 semaines'],
        ['digital', 'accompagnement', 'Maintenance et évolutions', 'Une plateforme hébergée, sauvegardée et mise à jour, qui évolue avec votre organisation.', '', "Hébergement, sauvegardes et mises à jour de sécurité\nCorrections et petites évolutions\nAssistance à vos utilisateurs\nPoint trimestriel sur la feuille de route", 'Mensuel'],
    ];
    foreach ($drafts as [$axis, $tier, $title, $tagline, $forWho, $deliverables, $duration]) {
        civilina_offer_save(['axis' => $axis, 'tier' => $tier, 'status' => 'draft', 'title' => $title, 'tagline' => $tagline, 'for_who' => $forWho,
            'deliverables' => $deliverables, 'duration' => $duration, 'price_da' => '', 'price_eur' => '', 'price_note' => 'HT', 'cta_label' => 'Demander un devis']);
    }
    return count($drafts);
}

/* ---------- Links to the free templates across the site ---------- */

/** "Free templates" band, between <!-- civilina:modeles --> markers (homepage, Journal, axis pages). */
function civilina_resources_band_html(array $slugs, string $title, string $lead): string
{
    $t = static fn (string $s): string => htmlspecialchars($s, ENT_NOQUOTES, 'UTF-8');
    $cards = '';
    foreach ($slugs as $slug) {
        $r = CIVILINA_RESOURCES[$slug];
        $axis = CIVILINA_ARTICLE_AXES[$r['axis']];
        $cards .= "\n      <a class=\"res-mini\" href=\"/ressources/#" . $slug . '">'
            . '<span class="pill pill-' . $axis[1] . '">' . $t($axis[0]) . ' · Excel</span>'
            . '<strong>' . $t((string) preg_replace('~\s+—\s+modèle Excel$~u', '', $r['title'])) . '</strong>'
            . '<span class="res-desc">' . $t($r['summary']) . '</span>'
            . '<span class="more">Télécharger gratuitement →</span></a>';
    }
    return "<!-- civilina:modeles -->\n<section class=\"res-band\" id=\"modeles\">\n  <div class=\"shell\">\n    <div class=\"section-head\">\n      <p class=\"eyebrow\">Modèles gratuits</p>\n      <h2>" . $t($title) . "</h2>\n      <p class=\"lead\">" . $t($lead) . "</p>\n    </div>\n    <div class=\"res-mini-grid\">"
        . $cards . "\n    </div>\n    <p style=\"margin-top:22px\"><a href=\"/ressources/\" class=\"btn btn-ghost\">Tous les modèles gratuits →</a></p>\n  </div>\n</section>\n<!-- /civilina:modeles -->";
}

/**
 * Links the free templates from the site: a band on the homepage, the Journal
 * page and the axis pages (templates of the axis), and a footer link on these
 * pages and on every article. Idempotent (the band is rewritten between its
 * markers); pages are backed up before any change.
 * @return array{changed: list<string>, skipped: list<string>}
 */
function civilina_site_link_resources(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $all = array_keys(CIVILINA_RESOURCES);
        $lead = 'Les trames que nous utilisons sur le terrain, avec un exemple rempli et des contrôles automatiques. Gratuites, en échange de votre adresse e-mail.';
        $contact = '<section id="contact">';
        $pages = [
            civilina_home_file() => [civilina_resources_band_html($all, 'Des modèles Excel prêts à l\'emploi.', $lead), "<!-- CONTACT -->\n" . $contact],
            $root . '/journal/index.html' => [civilina_resources_band_html($all, 'Passez de la lecture à la pratique.', $lead), "<section class=\"alt\">\n  <div class=\"shell\">\n    <div class=\"section-head\">\n      <p class=\"eyebrow\">Ligne éditoriale</p>"],
            $root . '/engineering/index.html' => [civilina_resources_band_html(['modele-itp'], 'Votre ITP en quelques minutes.', 'Notre trame de plan d\'inspection et d\'essais, avec un exemple commenté sur des voiles en béton armé.'), $contact],
            $root . '/gestion-entreprise/index.html' => [civilina_resources_band_html(['matrice-raci', 'fiche-kpi'], 'Deux outils de pilotage, prêts à l\'emploi.', 'La matrice RACI pour clarifier qui fait quoi, la fiche indicateur pour suivre ce qui compte.'), $contact],
            $root . '/digital/index.html' => [civilina_resources_band_html(['fiche-kpi'], 'Préparez vos indicateurs avant la plateforme.', 'Une fiche indicateur claire est la base d\'un bon tableau de bord, sous Excel comme sur une plateforme.'), $contact],
            $root . '/ia/index.html' => [null, null],
        ];
        $footerFrom = '<h5>Ressources</h5><ul>';
        $footerTo = '<h5>Ressources</h5><ul><li><a href="/ressources/">Modèles gratuits</a></li>';
        $changed = [];
        $skipped = [];
        foreach ($pages as $path => [$band, $anchor]) {
            if (!is_file($path)) {
                $skipped[] = basename(dirname($path)) . ' (absente)';
                continue;
            }
            $html = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $new = $html;
            if ($band !== null) {
                if (str_contains($new, '<!-- civilina:modeles -->')) {
                    $new = (string) preg_replace_callback('~<!-- civilina:modeles -->.*?<!-- /civilina:modeles -->~s', static fn (): string => $band, $new, 1);
                } elseif (substr_count($new, $anchor) === 1) {
                    $new = str_replace($anchor, $band . "\n\n" . $anchor, $new);
                } else {
                    $skipped[] = substr($path, strlen($root)) . ' (emplacement du bandeau introuvable)';
                }
            }
            if (!str_contains($new, 'href="/ressources/">Modèles gratuits') && substr_count($new, $footerFrom) === 1) {
                $new = str_replace($footerFrom, $footerTo, $new);
            }
            if ($new !== $html) {
                $changed[$path] = $new;
            }
        }
        $articleFrom = 'Tous les articles</a></li><li><a href="/newsletter/">Newsletter</a></li>';
        foreach (civilina_articles_published() as $a) {
            $path = $root . '/journal/' . $a['slug'] . '/index.html';
            $html = is_file($path) ? str_replace("\r\n", "\n", (string) file_get_contents($path)) : '';
            if ($html === '' || str_contains($html, 'href="/ressources/">Modèles gratuits')) {
                continue;
            }
            if (substr_count($html, $articleFrom) !== 1) {
                $skipped[] = 'journal/' . $a['slug'] . ' (pied de page)';
                continue;
            }
            $changed[$path] = str_replace($articleFrom, $articleFrom . '<li><a href="/ressources/">Modèles gratuits</a></li>', $html);
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'liens-modeles');
            foreach ($changed as $path => $html) {
                civilina_write_file($path, $html);
            }
        }
        civilina_audit('resources.linked', count($changed) . ' pages', $userId);
        return ['changed' => array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed)), 'skipped' => $skipped];
    });
}

/* ---------- Bureaux d'études: pilot programme (phase 1) ---------- */

const CIVILINA_BET_SIZES = ['1-5' => '1 à 5 ingénieurs', '6-20' => '6 à 20', '21-50' => '21 à 50', '50+' => 'Plus de 50'];
const CIVILINA_BET_DOMAINS = [
    'beton' => 'Structures en béton', 'metal' => 'Charpente métallique', 'routes' => 'Routes et chaussées', 'geotech' => 'Géotechnique',
    'vrd' => 'VRD', 'batiment' => 'Bâtiment', 'labo' => 'Laboratoire', 'autre' => 'Autre',
];
const CIVILINA_BET_TOOLS = ['excel' => 'Excel et Word', 'logiciel' => 'Un logiciel métier', 'papier' => 'Papier et e-mails', 'autre' => 'Autre'];

/** E-mail to Civilina (same mailbox and sender as the contact form). */
function civilina_notify_owner(string $subject, string $body, string $replyTo): bool
{
    $replyTo = filter_var($replyTo, FILTER_VALIDATE_EMAIL) ? $replyTo : 'contact@civilina.com';
    $headers = ['From: Civilina <contact@civilina.com>', 'Reply-To: ' . $replyTo, 'MIME-Version: 1.0', 'Content-Type: text/plain; charset=UTF-8', 'Content-Transfer-Encoding: 8bit'];
    return @mail('abdelghani.bouhouche@civilina.com', mb_encode_mimeheader($subject, 'UTF-8', 'B', "\r\n"), str_replace("\n", "\r\n", $body), implode("\r\n", $headers), '-fcontact@civilina.com');
}

/**
 * Pilot application: stored as a contact request (back-office "Demandes de
 * contact") and as a lead with the source "pilote" (axis Engineering).
 * @return string ok | invalid
 */
function civilina_bet_apply(array $in): string
{
    $line = static fn (string $key, int $max): string => mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) ($in[$key] ?? ''))), 0, $max);
    $name = $line('name', 120);
    $company = $line('company', 160);
    $email = mb_strtolower($line('email', 180));
    if ($name === '' || $company === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'invalid';
    }
    $size = CIVILINA_BET_SIZES[(string) ($in['size'] ?? '')] ?? 'Non précisée';
    $domains = array_values(array_intersect_key(CIVILINA_BET_DOMAINS, array_flip(array_map('strval', (array) ($in['domains'] ?? [])))));
    $tools = CIVILINA_BET_TOOLS[(string) ($in['tools'] ?? '')] ?? 'Non précisé';
    $message = mb_substr(trim(str_replace("\r\n", "\n", (string) ($in['message'] ?? ''))), 0, 2000);
    $summary = "Candidature au programme pilote bureaux d'études\n"
        . 'Taille : ' . $size . "\n"
        . 'Domaines : ' . ($domains ? implode(', ', $domains) : 'non précisés') . "\n"
        . 'Outils actuels : ' . $tools . "\n"
        . 'Ville / pays : ' . ($line('city', 120) !== '' ? $line('city', 120) : '—') . "\n\n"
        . ($message !== '' ? $message : '(pas de message)');
    $request = [
        'id' => bin2hex(random_bytes(8)), 'created_at' => date('c'), 'status' => 'new',
        'name' => $name, 'company' => $company, 'position' => $line('position', 160), 'email' => $email, 'phone' => $line('phone', 80),
        'need' => "Programme pilote bureaux d'études", 'message' => $summary, 'axis' => 'engineering', 'lead_source' => 'pilote',
        'ip' => civilina_client_ip(), 'user_agent' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 500),
    ];
    civilina_contact_insert($request);
    // The answers also go to the notes of the lead (the contact keeps the full request).
    civilina_db()->prepare("UPDATE leads SET notes = ? WHERE email = ? AND source = 'pilote' AND notes = ''")->execute([$summary, $email]);
    civilina_notify_owner('Candidature programme pilote — ' . $company, "Nouvelle candidature au programme pilote bureaux d'études.\n\n"
        . 'Nom : ' . $name . "\nBureau d'études : " . $company . "\nFonction : " . ($request['position'] !== '' ? $request['position'] : '—')
        . "\nE-mail : " . $email . "\nTéléphone : " . ($request['phone'] !== '' ? $request['phone'] : '—') . "\n\n" . $summary
        . "\n\n—\nSuivi : https://civilina.com/admin/?view=sales&source=pilote", $email);
    return 'ok';
}

/** Band promoting the space for engineering firms, between <!-- civilina:bet --> markers. */
function civilina_bet_band_html(): string
{
    return "<!-- civilina:bet -->\n<section class=\"bet-band\">\n  <div class=\"shell\">\n    <div class=\"bet-inner\">\n      <div>\n        <p class=\"eyebrow\">Nouveau · Programme pilote</p>\n        <h2>Un espace de travail dédié aux bureaux d'études.</h2>\n        <p>Base normative, ITP, WBS, contrôles QA/QC et brouillons préparés par CIVILINA IA, dans un espace réservé à votre équipe. 5 bureaux d'études pilotes, 3 mois offerts.</p>\n      </div>\n      <div class=\"bet-actions\"><a class=\"btn btn-primary\" href=\"/bureaux-etudes/\">Découvrir l'espace</a><a class=\"btn bet-ghost\" href=\"/bureaux-etudes/#pilote\">Candidater</a></div>\n    </div>\n  </div>\n</section>\n<!-- /civilina:bet -->";
}

/**
 * Promotes /bureaux-etudes/ from the site: band on the homepage (after the
 * hero), the Engineering page (before the normative base) and the Journal page;
 * pilot calls to action of the IA and Engineering pages pointed to the
 * application form. Idempotent; pages backed up first.
 * @return array{changed: list<string>, skipped: list<string>}
 */
function civilina_site_promote_bet(string $userId): array
{
    return civilina_with_publish_lock(static function () use ($userId): array {
        $root = civilina_site_root();
        $band = civilina_bet_band_html();
        $pages = [
            civilina_home_file() => ["<!-- 3 AXES -->\n<section id=\"axes\" class=\"alt\">", []],
            $root . '/engineering/index.html' => ['<section id="base-normative">', [
                '<a href="/?besoin=Autre#contact">Demander l\'accès pilote à la base normative <span>→</span></a>' => '<a href="/bureaux-etudes/#pilote">Rejoindre le programme pilote bureaux d\'études <span>→</span></a>',
            ]],
            $root . '/journal/index.html' => ['<!-- civilina:modeles -->', []],
            $root . '/ia/index.html' => [null, [
                '<a href="#contact" class="btn btn-primary">Demander l\'accès pilote</a>' => '<a href="/bureaux-etudes/#pilote" class="btn btn-primary">Demander l\'accès pilote</a>',
                '<a href="/?besoin=Autre#contact">Demander l\'accès pilote <span>→</span></a>' => '<a href="/bureaux-etudes/#pilote">Candidater au programme pilote bureaux d\'études <span>→</span></a>',
                'Présentez-nous votre besoin via le formulaire de contact.</p>' => 'Les bureaux d\'études peuvent <a href="/bureaux-etudes/#pilote" style="color:var(--ia);text-decoration:underline">candidater au programme pilote</a>.</p>',
            ]],
        ];
        $changed = [];
        $skipped = [];
        foreach ($pages as $path => [$anchor, $replacements]) {
            if (!is_file($path)) {
                $skipped[] = substr($path, strlen($root)) . ' (absente)';
                continue;
            }
            $html = str_replace("\r\n", "\n", (string) file_get_contents($path));
            $new = $html;
            if ($anchor !== null) {
                if (str_contains($new, '<!-- civilina:bet -->')) {
                    $new = (string) preg_replace_callback('~<!-- civilina:bet -->.*?<!-- /civilina:bet -->~s', static fn (): string => $band, $new, 1);
                } elseif (substr_count($new, $anchor) === 1) {
                    $new = str_replace($anchor, $band . "\n\n" . $anchor, $new);
                } else {
                    $skipped[] = substr($path, strlen($root)) . ' (emplacement du bandeau introuvable)';
                }
            }
            foreach ($replacements as $from => $to) {
                if (substr_count($new, $from) === 1) {
                    $new = str_replace($from, $to, $new);
                } elseif (!str_contains($new, $to)) {
                    $skipped[] = substr($path, strlen($root)) . ' (lien introuvable : ' . mb_substr(strip_tags($from), 0, 40) . ')';
                }
            }
            if ($new !== $html) {
                $changed[$path] = $new;
            }
        }
        $sitemapFile = $root . '/sitemap.xml';
        $sitemap = str_replace("\r\n", "\n", (string) file_get_contents($sitemapFile));
        if (!str_contains($sitemap, '<loc>https://civilina.com/bureaux-etudes/</loc>')) {
            $changed[$sitemapFile] = str_replace('</urlset>', "  <url>\n    <loc>https://civilina.com/bureaux-etudes/</loc>\n    <lastmod>" . date('Y-m-d') . "</lastmod>\n  </url>\n</urlset>", $sitemap);
        }
        $share = $root . '/assets/brand/share/civilina-bet.png';
        if (!is_file($share) && ($png = civilina_share_png(['kicker' => 'CIVILINA ENGINEERING · PILOTE', 'line1' => 'L\'espace de travail', 'line2' => 'des bureaux d\'études', 'subtitle' => 'Normes, ITP, WBS, QA/QC et CIVILINA IA', 'color' => '#003b8c', 'brand' => ''])) !== null) {
            civilina_write_file($share, $png);
        }
        if ($changed !== []) {
            civilina_backup_files(array_keys($changed), 'bureaux-etudes');
            foreach ($changed as $path => $html) {
                civilina_write_file($path, $html);
            }
        }
        civilina_audit('bet.promoted', count($changed) . ' fichiers', $userId);
        return ['changed' => array_map(static fn (string $p): string => substr($p, strlen($root)), array_keys($changed)), 'skipped' => $skipped];
    });
}
