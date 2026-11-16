<?php

declare(strict_types=1);

/*
 * Free templates (milestone M4): civilina.com/ressources/
 *
 *   GET                 list of the resources, each with its request form
 *   POST action=request request (e-mail, first name, company, newsletter box)
 *   GET  ?pret=<token>  "your template is ready" page with the download button
 *   GET  ?t=<token>     the file itself (signed link, valid 30 days)
 *
 * Never cached (personal links). The files live outside public_html.
 */

header('Cache-Control: no-store, max-age=0');
// same-origin: personal links never leak to other sites, and form posts keep their Origin header.
header('Referrer-Policy: same-origin');
header('X-Content-Type-Options: nosniff');

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_sales.php';

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
$method = (string) ($_SERVER['REQUEST_METHOD'] ?? 'GET');

/* Download */
if (isset($_GET['t'])) {
    $found = civilina_resource_from_token((string) $_GET['t']);
    $path = $found['row'] ? civilina_resource_path((string) $found['row']['resource']) : null;
    if ($found['state'] === 'ok' && $path !== null) {
        civilina_resource_mark_downloaded((int) $found['row']['id']);
        header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        header('Content-Disposition: attachment; filename="' . basename($path) . '"');
        header('Content-Length: ' . filesize($path));
        header('X-Robots-Tag: noindex');
        readfile($path);
        exit;
    }
    http_response_code($found['state'] === 'expired' ? 410 : 404);
    civilina_public_start('Lien de téléchargement | Civilina', 'Modèles Civilina.', 'https://civilina.com/ressources/', false);
    echo '<section class="nl-card"><p class="eyebrow">Modèles gratuits</p><h1>' . ($found['state'] === 'expired' ? 'Ce lien a expiré.' : 'Ce lien n\'est pas valide.') . '</h1>'
        . '<p class="lead">Les liens de téléchargement sont valables 30 jours. Demandez de nouveau le modèle : un nouveau lien vous sera donné immédiatement.</p>'
        . '<p class="nl-actions"><a class="btn btn-primary" href="/ressources/">Voir les modèles</a></p></section>';
    civilina_public_end();
    exit;
}

/* Request */
if ($method === 'POST' && (string) ($_POST['action'] ?? '') === 'request') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $sameSite = $origin === '' || parse_url($origin, PHP_URL_HOST) === parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $slug = (string) ($_POST['resource'] ?? '');
    $target = '/ressources/?erreur=invalide#' . rawurlencode($slug);
    if (trim((string) ($_POST['website'] ?? '')) !== '' || !$sameSite) {
        $target = '/ressources/';
    } elseif (!civilina_form_hit_allowed('ressource', 10, 3600)) {
        $target = '/ressources/?erreur=limite';
    } else {
        try {
            $result = civilina_resource_request($slug, (string) ($_POST['email'] ?? ''), (string) ($_POST['first_name'] ?? ''), (string) ($_POST['company'] ?? ''), !empty($_POST['newsletter']));
            if ($result['state'] === 'ok') {
                $target = '/ressources/?pret=' . civilina_resource_token($result['id']);
            }
        } catch (Throwable $ex) {
            error_log('civilina ressources: ' . $ex->getMessage());
            $target = '/ressources/?erreur=technique';
        }
    }
    header('Location: ' . $target, true, 303);
    exit;
}

/* Ready page */
if (isset($_GET['pret'])) {
    $token = (string) $_GET['pret'];
    $found = civilina_resource_from_token($token);
    civilina_public_start('Votre modèle est prêt | Civilina', 'Modèles Civilina.', 'https://civilina.com/ressources/', false);
    if ($found['state'] !== 'ok') {
        echo '<section class="nl-card"><p class="eyebrow">Modèles gratuits</p><h1>Ce lien n\'est plus valide.</h1><p class="nl-actions"><a class="btn btn-primary" href="/ressources/">Voir les modèles</a></p></section>';
    } else {
        $r = CIVILINA_RESOURCES[$found['row']['resource']];
        echo '<section class="nl-card nl-ok"><p class="eyebrow">Modèles gratuits</p><h1>Votre modèle est prêt.</h1>'
            . '<p class="lead"><b>' . $e($r['title']) . '</b></p>'
            . '<p class="nl-actions"><a class="btn btn-primary" href="/ressources/?t=' . $e($token) . '">Télécharger le fichier Excel</a> <a class="btn btn-ghost" href="/journal/' . $e($r['article']) . '/">Lire le guide associé</a></p>'
            . '<p class="nl-legal">Ce lien reste valable 30 jours' . (civilina_nl_ready() ? ' ; il vous a aussi été envoyé par e-mail' : '') . '.'
            . ((int) $found['row']['newsletter'] === 1 ? ' Pour la newsletter, confirmez votre inscription avec le lien reçu par e-mail.' : '') . '</p></section>';
    }
    civilina_public_end();
    exit;
}

/* List */
$errors = [
    'invalide' => 'Vérifiez l\'adresse e-mail saisie.',
    'limite' => 'Trop de demandes depuis votre connexion : merci de réessayer dans une heure.',
    'technique' => 'Un incident technique a empêché l\'envoi. Merci de réessayer plus tard.',
];
$error = $errors[(string) ($_GET['erreur'] ?? '')] ?? '';
civilina_public_start('Modèles Excel gratuits : RACI, ITP, KPI | Civilina', 'Téléchargez gratuitement nos modèles Excel commentés : matrice RACI, plan d\'inspection et d\'essais (ITP) et fiche indicateur KPI.', 'https://civilina.com/ressources/', $error === '');
?>
<section class="res-head">
  <p class="eyebrow">Modèles gratuits</p>
  <h1>Des modèles Excel prêts à l'emploi.</h1>
  <p class="lead">Les trames que nous utilisons sur le terrain, avec un exemple rempli et des contrôles automatiques. Indiquez votre e-mail pour recevoir le lien de téléchargement.</p>
  <?php if ($error !== ''): ?><p class="res-error"><?= $e($error) ?></p><?php endif; ?>
</section>
<section class="res-grid">
  <?php foreach (CIVILINA_RESOURCES as $slug => $r): $axis = CIVILINA_ARTICLE_AXES[$r['axis']]; ?>
  <article class="res-card" id="<?= $e($slug) ?>">
    <span class="pill pill-<?= $e($axis[1]) ?>"><?= $e($axis[0]) ?> · Excel</span>
    <h2><?= $e($r['title']) ?></h2>
    <p><?= $e($r['summary']) ?></p>
    <p class="res-guide"><a href="/journal/<?= $e($r['article']) ?>/">Lire le guide associé →</a></p>
    <form class="nl-form" method="post" action="/ressources/">
      <input type="hidden" name="action" value="request"><input type="hidden" name="resource" value="<?= $e($slug) ?>">
      <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
      <input type="email" name="email" required autocomplete="email" maxlength="180" placeholder="Votre e-mail professionnel" aria-label="Votre e-mail">
      <div class="res-row">
        <input type="text" name="first_name" autocomplete="given-name" maxlength="80" placeholder="Prénom (facultatif)" aria-label="Prénom">
        <input type="text" name="company" autocomplete="organization" maxlength="160" placeholder="Entreprise (facultatif)" aria-label="Entreprise">
      </div>
      <label class="res-optin"><input type="checkbox" name="newsletter" value="1"> M'inscrire aussi à la newsletter (confirmation par e-mail)</label>
      <button class="btn btn-primary" type="submit">Recevoir le modèle</button>
    </form>
  </article>
  <?php endforeach; ?>
</section>
<p class="nl-legal res-legal">Vos coordonnées servent à vous donner accès au modèle et, le cas échéant, à vous recontacter au sujet de nos services ; vous pouvez vous y opposer à tout moment en nous écrivant. Voir la page <a href="/confidentialite/">Confidentialité</a>.</p>
<?php
civilina_public_end();
