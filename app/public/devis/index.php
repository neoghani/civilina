<?php

declare(strict_types=1);

/*
 * Quote request page (milestone M4): civilina.com/devis/?offre=<id>
 * The form posts to /contact/ with offer_id; the contact handler stores the
 * request, notifies Civilina, feeds the prospecting pipeline and redirects
 * back here with ?ok=1.
 */

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_sales.php';

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

if (isset($_GET['ok'])) {
    civilina_public_start('Demande de devis envoyée | Civilina', 'Votre demande de devis a été transmise à Civilina.', 'https://civilina.com/devis/', false);
    echo '<section class="nl-card nl-ok"><p class="eyebrow">Demande de devis</p><h1>Merci, votre demande est bien arrivée.</h1>'
        . '<p class="lead">Nous revenons vers vous rapidement pour échanger sur votre contexte, puis vous adresser une proposition.</p>'
        . '<p class="nl-actions"><a class="btn btn-primary" href="/ressources/">Découvrir nos modèles gratuits →</a> <a class="btn btn-ghost" href="/journal/">Lire le Journal</a></p></section>';
    civilina_public_end();
    exit;
}

$offers = civilina_offers_published();
$selected = (int) ($_GET['offre'] ?? 0);
civilina_public_start('Demander un devis | Civilina', 'Demandez un devis pour une offre CIVILINA Engineering, Entreprise ou Digital : cadrage, mission complète ou accompagnement.', 'https://civilina.com/devis/', true);
?>
<section class="nl-card q-card">
  <p class="eyebrow">Demande de devis</p>
  <h1>Parlons de votre besoin.</h1>
  <p class="lead">Choisissez l'offre qui vous intéresse et décrivez votre contexte. Le devis est établi après un premier échange, sans engagement.</p>
  <?php if (!$offers): ?>
    <p>Nos offres détaillées arrivent très bientôt. En attendant, décrivez votre besoin via le <a href="/#contact">formulaire de contact</a>.</p>
  <?php else: ?>
  <form class="nl-form nl-form-page q-form" method="post" action="/contact/">
    <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
    <label class="q-wide">Offre
      <select name="offer_id" required>
        <?php foreach (CIVILINA_OFFER_PAGES as $axis => $page): $group = array_filter($offers, static fn (array $o): bool => $o['axis'] === $axis); if (!$group) { continue; } ?>
          <optgroup label="<?= $e(CIVILINA_ARTICLE_AXES[$axis][3]) ?>">
            <?php foreach ($group as $o): ?>
              <option value="<?= (int) $o['id'] ?>"<?= $o['id'] === $selected ? ' selected' : '' ?>><?= $e($o['title'] . ' — ' . civilina_offer_price($o)) ?></option>
            <?php endforeach; ?>
          </optgroup>
        <?php endforeach; ?>
      </select>
    </label>
    <div class="nl-row">
      <label>Nom<input type="text" name="name" required autocomplete="name" maxlength="120"></label>
      <label>Entreprise<input type="text" name="company" autocomplete="organization" maxlength="160"></label>
    </div>
    <div class="nl-row">
      <label>Fonction<input type="text" name="position" autocomplete="organization-title" maxlength="160"></label>
      <label>Téléphone<input type="tel" name="phone" autocomplete="tel" maxlength="80"></label>
    </div>
    <label class="q-wide">E-mail<input type="email" name="email" required autocomplete="email" maxlength="180"></label>
    <label class="q-wide">Votre contexte<textarea name="message" rows="5" required maxlength="2500" placeholder="Votre projet ou votre organisation, la taille de l'équipe, vos délais…"></textarea></label>
    <div class="nl-actions"><button class="btn btn-primary" type="submit">Envoyer ma demande</button></div>
    <p class="nl-legal">Vos coordonnées servent uniquement à traiter votre demande et à vous recontacter à ce sujet. Voir la page <a href="/confidentialite/">Confidentialité</a>.</p>
  </form>
  <?php endif; ?>
</section>
<?php
civilina_public_end();
