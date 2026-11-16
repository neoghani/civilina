<?php

declare(strict_types=1);

/*
 * Public newsletter page (milestone M3): civilina.com/newsletter/
 *
 *   POST action=subscribe   registration from the site forms (then redirect)
 *   GET  ?c=<token>         confirmation link of the double opt-in e-mail
 *   GET  ?u=<id>.<sig>      personal page: topics or unsubscribe
 *   POST ?u=…               the same, and the one-click unsubscribe of mail apps
 *   GET  ?etat=…            result of a registration
 *   GET                     registration form
 *
 * Personal links are signed (civilina_newsletter.php). Never cached: nginx would
 * otherwise keep one visitor's page for the next one.
 */

header('Cache-Control: no-store, max-age=0');
header('Referrer-Policy: no-referrer');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_newsletter.php';

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');
$manageToken = (string) ($_GET['u'] ?? '');

/* ---------- One-click unsubscribe (RFC 8058), sent by mail apps without a browser ---------- */
if ($method === 'POST' && $manageToken !== '' && (string) ($_POST['List-Unsubscribe'] ?? '') === 'One-Click') {
    $s = civilina_nl_from_manage_token($manageToken);
    if ($s) {
        civilina_nl_unsubscribe((int) $s['id'], 'un clic');
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo $s ? 'Désinscription enregistrée.' : 'Lien invalide.';
    exit;
}

/* ---------- Registration ---------- */
if ($method === 'POST' && (string) ($_POST['action'] ?? '') === 'subscribe') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $sameSite = $origin === '' || parse_url($origin, PHP_URL_HOST) === parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $state = 'invalide';
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $state = 'envoye';                       // honeypot filled: pretend it worked
    } elseif (!$sameSite) {
        $state = 'invalide';
    } elseif (!civilina_form_hit_allowed('newsletter', 8, 3600)) {
        $state = 'limite';
    } else {
        try {
            $result = civilina_nl_subscribe((string) ($_POST['email'] ?? ''), (string) ($_POST['first_name'] ?? ''), $_POST['axes'] ?? [], (string) ($_POST['source'] ?? ''));
            $state = ['sent' => 'envoye', 'queued' => 'attente', 'invalid' => 'invalide'][$result];
        } catch (Throwable $ex) {
            error_log('civilina newsletter: ' . $ex->getMessage());
            $state = 'erreur';
        }
    }
    header('Location: /newsletter/?etat=' . $state, true, 303);
    exit;
}

/* ---------- Personal page: topics or unsubscribe ---------- */
$notice = '';
$subscriber = null;
if ($manageToken !== '') {
    $subscriber = civilina_nl_from_manage_token($manageToken);
    if ($subscriber && $method === 'POST') {
        if (isset($_POST['unsubscribe'])) {
            civilina_nl_unsubscribe((int) $subscriber['id'], 'page personnelle');
            $notice = 'Vous êtes désinscrit. Vous ne recevrez plus la newsletter Civilina.';
        } elseif (isset($_POST['resubscribe']) && $subscriber['status'] === 'unsubscribed') {
            civilina_db()->prepare("UPDATE subscribers SET status = 'confirmed', unsubscribed_at = '', updated_at = ? WHERE id = ?")->execute([date('c'), $subscriber['id']]);
            civilina_nl_set_axes((int) $subscriber['id'], $_POST['axes'] ?? []);
            civilina_audit('newsletter.resubscribed', '#' . $subscriber['id']);
            $notice = 'C\'est noté : vous êtes de nouveau inscrit.';
        } elseif ($subscriber['status'] === 'confirmed') {
            civilina_nl_set_axes((int) $subscriber['id'], $_POST['axes'] ?? []);
            $notice = 'Vos thèmes sont enregistrés.';
        }
        $subscriber = civilina_nl_find((int) $subscriber['id']);
    }
}

/* ---------- Page ---------- */

function civilina_nl_page_start(string $title, bool $index): void
{
    $robots = $index ? '' : '<meta name="robots" content="noindex, nofollow">' . "\n";
    $t = htmlspecialchars($title, ENT_QUOTES, 'UTF-8');
    echo <<<HTML
<!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
{$robots}<title>{$t} | Civilina</title>
<meta name="description" content="La newsletter Civilina : les nouveaux guides, modèles et analyses du Journal, en Engineering, gestion d'entreprise et digital.">
<link rel="canonical" href="https://civilina.com/newsletter/">
<link rel="icon" type="image/png" href="/assets/brand/civilina-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/site.css">
<script src="/assets/js/theme.js"></script>
</head>
<body>
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
    <button class="burger" aria-label="Menu" onclick="document.getElementById('menu').classList.toggle('open')">☰</button>
  </div>
</header>
<main class="nl-page">
  <div class="shell">

HTML;
}

function civilina_nl_page_end(): void
{
    echo <<<HTML

  </div>
</main>
<footer>
  <div class="shell">
    <div class="foot">
      <div>
        <a href="/" class="brand"><img src="/assets/brand/civilina-logo.png" alt="">CIVILINA</a>
        <p style="margin-top:.8rem;max-width:34ch">Engineering, management et digitalisation pour les projets d'ingénierie.</p>
      </div>
      <div><h5>Axes</h5><ul><li><a href="/engineering/">CIVILINA Engineering</a></li><li><a href="/gestion-entreprise/">CIVILINA Entreprise</a></li><li><a href="/digital/">CIVILINA Digital</a></li><li><a href="/ia/">CIVILINA IA</a></li></ul></div>
      <div><h5>Ressources</h5><ul><li><a href="/journal/">Journal</a></li><li><a href="/newsletter/">Newsletter</a></li><li><a href="/#apropos">À propos</a></li></ul></div>
      <div><h5>Contact</h5><ul><li><a href="/#contact">Demander un diagnostic</a></li><li><a href="https://www.linkedin.com/company/civilina" rel="noopener">LinkedIn</a></li></ul></div>
    </div>
    <div class="legal"><span>© 2026 Civilina</span><span><a href="/mentions-legales/">Mentions légales</a> · <a href="/confidentialite/">Confidentialité</a> · <a href="/cookies/">Cookies</a> · <a href="/conditions-utilisation/">Conditions</a></span></div>
  </div>
</footer>
</body>
</html>
HTML;
}

function civilina_nl_axes_inputs(array $checked): string
{
    $out = '<fieldset class="nl-axes"><legend>Thèmes reçus</legend>';
    foreach (CIVILINA_NL_AXES as $key => $label) {
        $out .= '<label><input type="checkbox" name="axes[]" value="' . $key . '"' . (in_array($key, $checked, true) ? ' checked' : '') . '> ' . $label . '</label>';
    }
    return $out . '</fieldset>';
}

/* Confirmation link. The GET only shows a button: mail security scanners open
   links automatically, and must not confirm an address in the visitor's place. */
if (isset($_GET['c'])) {
    $token = (string) $_GET['c'];
    $confirmation = $method === 'POST' ? civilina_nl_confirm($token) : civilina_nl_confirm_check($token);
    civilina_nl_page_start(in_array($confirmation['state'], ['confirmed', 'already'], true) ? 'Inscription confirmée' : 'Confirmer l\'inscription', false);
    $s = $confirmation['subscriber'];
    if ($confirmation['state'] === 'ready') {
        echo '<section class="nl-card"><p class="eyebrow">Newsletter</p><h1>Dernière étape : confirmez votre inscription.</h1>'
            . '<p class="lead">Thèmes choisis : <b>' . $e(civilina_nl_axes_label((string) $s['axes'])) . '</b>.</p>'
            . '<form method="post" action="/newsletter/?c=' . $e($token) . '"><p class="nl-actions"><button class="btn btn-primary" type="submit">Confirmer mon inscription</button></p></form></section>';
    } elseif (in_array($confirmation['state'], ['confirmed', 'already'], true)) {
        echo '<section class="nl-card nl-ok"><p class="eyebrow">Newsletter</p><h1>' . ($confirmation['state'] === 'confirmed' ? 'Inscription confirmée, merci !' : 'Votre inscription était déjà confirmée.') . '</h1>'
            . '<p class="lead">Vous recevrez les nouveaux articles du Journal Civilina pour : <b>' . $e(civilina_nl_axes_label((string) $s['axes'])) . '</b>. Chaque e-mail contient un lien pour changer de thèmes ou vous désinscrire.</p>'
            . '<p class="nl-actions"><a class="btn btn-primary" href="/journal/">Lire le Journal →</a> <a class="btn btn-ghost" href="' . $e(civilina_nl_manage_url((int) $s['id'])) . '">Choisir mes thèmes</a></p></section>';
    } else {
        echo '<section class="nl-card"><p class="eyebrow">Newsletter</p><h1>' . ($confirmation['state'] === 'expired' ? 'Ce lien a expiré.' : 'Ce lien n\'est pas valide.') . '</h1>'
            . '<p class="lead">Les liens de confirmation sont valables 7 jours et ne servent qu\'une fois. Inscrivez-vous de nouveau : un nouveau lien vous sera envoyé.</p>'
            . '<p class="nl-actions"><a class="btn btn-primary" href="/newsletter/">Recommencer l\'inscription</a></p></section>';
    }
    civilina_nl_page_end();
    exit;
}

/* Personal page */
if ($manageToken !== '') {
    civilina_nl_page_start('Vos préférences', false);
    if (!$subscriber) {
        echo '<section class="nl-card"><p class="eyebrow">Newsletter</p><h1>Ce lien n\'est pas valide.</h1><p class="lead">Utilisez le lien présent en bas de votre dernière newsletter, ou écrivez-nous via la page <a href="/#contact">Contact</a>.</p></section>';
    } else {
        [$local, $domain] = explode('@', (string) $subscriber['email']) + [1 => ''];
        $masked = mb_substr($local, 0, 2) . str_repeat('•', max(1, min(6, mb_strlen($local) - 2))) . '@' . $domain;
        $action = '/newsletter/?u=' . $e($manageToken);
        $current = civilina_nl_clean_axes((string) $subscriber['axes']);
        echo '<section class="nl-card"><p class="eyebrow">Newsletter</p><h1>Vos préférences</h1><p class="lead">Adresse : <b>' . $e($masked) . '</b></p>';
        if ($notice !== '') {
            echo '<p class="cform-ok">' . $e($notice) . '</p>';
        }
        if ($subscriber['status'] === 'confirmed') {
            echo '<form class="nl-form nl-form-page" method="post" action="' . $action . '">' . civilina_nl_axes_inputs($current)
                . '<div class="nl-actions"><button class="btn btn-primary" type="submit" name="save" value="1">Enregistrer mes thèmes</button>'
                . '<button class="btn btn-ghost" type="submit" name="unsubscribe" value="1">Me désinscrire</button></div></form>';
        } elseif ($subscriber['status'] === 'unsubscribed') {
            echo '<p>Vous n\'êtes plus inscrit à la newsletter. Vous pouvez vous réinscrire à tout moment.</p>'
                . '<form class="nl-form nl-form-page" method="post" action="' . $action . '">' . civilina_nl_axes_inputs($current)
                . '<div class="nl-actions"><button class="btn btn-primary" type="submit" name="resubscribe" value="1">Me réinscrire</button></div></form>';
        } elseif ($subscriber['status'] === 'pending') {
            echo '<p>Votre inscription n\'est pas encore confirmée : ouvrez le lien de l\'e-mail de confirmation.</p>'
                . '<form method="post" action="' . $action . '"><button class="btn btn-ghost" type="submit" name="unsubscribe" value="1">Annuler mon inscription</button></form>';
        } else {
            echo '<p>Cette adresse ne reçoit plus d\'e-mails de Civilina.</p>';
        }
        echo '</section>';
    }
    civilina_nl_page_end();
    exit;
}

/* Registration form and its result */
$states = [
    'envoye' => ['Vérifiez votre boîte mail', 'Si cette adresse n\'était pas encore inscrite, un e-mail de confirmation vient de lui être envoyé. Cliquez sur le lien qu\'il contient pour valider votre inscription (pensez à regarder dans les courriers indésirables).', true],
    'attente' => ['Inscription enregistrée', 'Vous allez recevoir un e-mail de confirmation très prochainement. Cliquez sur le lien qu\'il contient pour valider votre inscription.', true],
    'invalide' => ['Adresse non valide', 'Vérifiez l\'adresse e-mail saisie et réessayez.', false],
    'limite' => ['Trop de tentatives', 'Merci de réessayer dans une heure.', false],
    'erreur' => ['Inscription impossible pour le moment', 'Un incident technique empêche l\'inscription. Merci de réessayer plus tard.', false],
];
$state = (string) ($_GET['etat'] ?? '');
civilina_nl_page_start('Newsletter', !isset($states[$state]));
if (isset($states[$state])) {
    [$title, $text, $ok] = $states[$state];
    echo '<section class="nl-card' . ($ok ? ' nl-ok' : '') . '"><p class="eyebrow">Newsletter</p><h1>' . $e($title) . '</h1><p class="lead">' . $e($text) . '</p>'
        . '<p class="nl-actions"><a class="btn btn-primary" href="/journal/">Lire le Journal →</a>' . ($ok ? '' : ' <a class="btn btn-ghost" href="/newsletter/">Réessayer</a>') . '</p></section>';
} else {
    ?>
<section class="nl-card">
  <p class="eyebrow">Newsletter Civilina</p>
  <h1>Les nouveaux guides du Journal, dans votre boîte mail.</h1>
  <p class="lead">Modèles commentés, checklists et retours d'expérience en management de projet, qualité et digitalisation. Choisissez vos thèmes : vous ne recevez que ce qui vous concerne.</p>
  <form class="nl-form nl-form-page" method="post" action="/newsletter/">
    <input type="hidden" name="action" value="subscribe">
    <input type="hidden" name="source" value="page-newsletter">
    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <div class="nl-row">
      <label>Prénom <small>(facultatif)</small><input type="text" name="first_name" autocomplete="given-name" maxlength="80"></label>
      <label>E-mail<input type="email" name="email" required autocomplete="email" maxlength="180" placeholder="nom@entreprise.com"></label>
    </div>
    <?= civilina_nl_axes_inputs(array_keys(CIVILINA_NL_AXES)) ?>
    <div class="nl-actions"><button class="btn btn-primary" type="submit">S'inscrire</button></div>
    <p class="nl-legal">Vous recevrez un e-mail pour confirmer votre adresse. Chaque newsletter contient un lien de désinscription. Vos données servent uniquement à l'envoi de la newsletter : voir la page <a href="/confidentialite/">Confidentialité</a>.</p>
  </form>
</section>
<section class="nl-why">
  <div><span class="pill pill-eng">Engineering</span><p>Plans qualité, ITP, réception, DOE : la qualité sur le terrain.</p></div>
  <div><span class="pill pill-ent">Entreprise</span><p>Processus, RACI, indicateurs, ISO 9001 et QHSE.</p></div>
  <div><span class="pill pill-dig">Digital</span><p>Tableaux de bord, plateformes de gestion et usage de l'IA.</p></div>
</section>
    <?php
}
civilina_nl_page_end();
