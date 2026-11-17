<?php

declare(strict_types=1);

/*
 * Space for engineering firms, pilot programme (phase 1): civilina.com/bureaux-etudes/
 * Presents the CIVILINA Engineering platform and collects pilot applications
 * (contact request + lead "pilote", axis Engineering). No link to the
 * application itself: access is opened per firm once the pilot is ready.
 */

require dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private/civilina_sales.php';

header('Cache-Control: no-store, max-age=0');
header('X-Content-Type-Options: nosniff');

$e = static fn (string $s): string => htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && (string) ($_POST['action'] ?? '') === 'pilot') {
    $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
    $sameSite = $origin === '' || parse_url($origin, PHP_URL_HOST) === parse_url('//' . (string) ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $state = 'invalide';
    if (trim((string) ($_POST['website'] ?? '')) !== '') {
        $state = 'ok';
    } elseif ($sameSite) {
        try {
            if (!civilina_form_hit_allowed('pilote', 5, 3600)) {
                $state = 'limite';
            } else {
                $state = civilina_bet_apply($_POST) === 'ok' ? 'ok' : 'invalide';
            }
        } catch (Throwable $ex) {
            error_log('civilina pilote: ' . $ex->getMessage());
            $state = 'erreur';
        }
    }
    header('Location: /bureaux-etudes/?candidature=' . $state . '#pilote', true, 303);
    exit;
}

$messages = [
    'ok' => ['ok', 'Merci, votre candidature est bien arrivée. Nous revenons vers vous rapidement pour un premier échange de 30 minutes.'],
    'invalide' => ['ko', 'Merci de renseigner votre nom, votre bureau d\'études et une adresse e-mail valide.'],
    'limite' => ['ko', 'Trop d\'envois depuis votre connexion : merci de réessayer dans une heure.'],
    'erreur' => ['ko', 'Un incident technique a empêché l\'envoi. Merci de réessayer plus tard, ou de nous écrire sur LinkedIn.'],
];
$message = $messages[(string) ($_GET['candidature'] ?? '')] ?? null;

civilina_public_start(
    'Espace bureaux d\'études : normes, ITP, QA/QC | CIVILINA Engineering',
    'L\'espace de travail des bureaux d\'études : base normative, ITP, WBS, contrôles QA/QC et brouillons préparés par CIVILINA IA. Programme pilote : 5 bureaux, 3 mois offerts.',
    'https://civilina.com/bureaux-etudes/',
    $message === null,
    'civilina-bet.png',
    '--c:var(--eng);--c-soft:var(--eng-soft)',
    true
);
?>
<section class="page-hero">
  <div class="shell">
    <p class="crumbs"><a href="/">Accueil</a> › <a href="/engineering/">CIVILINA Engineering</a> › Espace bureaux d'études</p>
    <div class="hero-split">
      <div>
        <span class="tag">CIVILINA Engineering · Programme pilote</span>
        <h1>La plateforme qui structure <span>les missions de votre bureau d'études</span>.</h1>
        <p class="lead">Base normative, ITP, WBS, contrôles QA/QC et brouillons préparés par CIVILINA IA, dans un espace réservé à votre équipe. Vos ingénieurs gardent la décision ; la plateforme organise, trace et prépare.</p>
        <div class="hero-ctas">
          <a href="#pilote" class="btn btn-primary">Candidater au programme pilote</a>
          <a href="#modules" class="btn btn-ghost">Voir les modules</a>
        </div>
      </div>
      <div class="facts">
        <div class="fact"><strong>5 bureaux d'études pilotes</strong><span>Sélection sur candidature</span></div>
        <div class="fact"><strong>3 mois offerts</strong><span>En échange de vos retours</span></div>
        <div class="fact"><strong>Un projet réel</strong><span>Accompagné pas à pas</span></div>
        <div class="fact"><strong>Données cloisonnées</strong><span>Un espace par bureau d'études</span></div>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Le quotidien d'un bureau d'études</p>
      <h2>Le calcul est maîtrisé. C'est tout ce qui l'entoure qui prend du temps.</h2>
    </div>
    <div class="pains">
      <div class="pain"><h3>La bonne norme, la bonne édition</h3><p>Chacun cherche à sa façon dans ses dossiers de normes ; l'édition applicable et la clause exacte se retrouvent mal.</p></div>
      <div class="pain"><h3>Un ITP refait à chaque projet</h3><p>Les plans d'inspection repartent d'une page blanche ou d'un ancien projet mal adapté.</p></div>
      <div class="pain"><h3>Des preuves éparpillées</h3><p>PV, photos, fiches d'essai et non-conformités sont dans des e-mails et des dossiers partagés.</p></div>
      <div class="pain"><h3>Des versions difficiles à suivre</h3><p>Qui a modifié, qui a validé, quelle version a été transmise au client ? La réponse prend du temps.</p></div>
      <div class="pain"><h3>Des responsabilités implicites</h3><p>Sans circuit de revue clair, la vérification et l'approbation dépendent de la mémoire de chacun.</p></div>
      <div class="pain"><h3>Une réception qui s'improvise</h3><p>Réserves, levées et dossier des ouvrages exécutés se reconstituent en fin de chantier, dans l'urgence.</p></div>
    </div>
  </div>
</section>

<section id="modules" class="alt">
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Les modules</p>
      <h2>Un seul espace pour la mission, de la norme à la réception.</h2>
      <p class="lead">Nous préférons être précis : chaque module indique son état réel.</p>
    </div>
    <div class="bet-modules">
      <article class="bet-module"><span class="badge b-pilot">Accès pilote</span><h3>Projets et WBS</h3><p>Structure du projet, lots de travaux, ouvrages et hypothèses de conception (design basis), organisés en arborescence prête à planifier.</p></article>
      <article class="bet-module"><span class="badge b-pilot">Accès pilote</span><h3>ITP et contrôles QA/QC</h3><p>Plans d'inspection et d'essais par ouvrage, points d'arrêt, de témoin et de revue, inspections, PV, preuves jointes et registres.</p></article>
      <article class="bet-module"><span class="badge b-pilot">Accès pilote</span><h3>Brouillons CIVILINA IA</h3><p>ITP, WBS, RACI, registre des risques, workflows et tableaux de bord préparés à partir de règles métier, versionnés et soumis à revue.</p></article>
      <article class="bet-module"><span class="badge b-dev">En développement</span><h3>Base normative</h3><p>De l'ouvrage à l'exigence : domaine, norme, édition, clause. La plateforme indique les références ; le texte reste celui de vos documents officiels.</p></article>
      <article class="bet-module"><span class="badge b-pilot">Accès pilote</span><h3>Organisation et rôles</h3><p>Propriétaire, administrateur, chef de projet, membre ou lecteur : chacun voit et fait ce que son rôle permet, dans l'espace de votre bureau.</p></article>
      <article class="bet-module"><span class="badge b-dev">En développement</span><h3>Réception et DOE</h3><p>Réserves, levées et dossier de remise constitués au fil des contrôles, plutôt qu'en fin de chantier.</p></article>
    </div>
  </div>
</section>

<section>
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Une mission type</p>
      <h2>Ce que la plateforme change, étape par étape.</h2>
    </div>
    <div class="steps">
      <div class="step"><h3>Cadrer</h3><p>Le projet, ses ouvrages et ses lots sont créés une fois ; la WBS sert de colonne vertébrale à tout le reste.</p></div>
      <div class="step"><h3>Identifier les exigences</h3><p>Pour chaque ouvrage, les normes, éditions et clauses applicables sont retrouvées, puis décidées par l'ingénieur.</p></div>
      <div class="step"><h3>Préparer les contrôles</h3><p>CIVILINA IA propose un brouillon d'ITP ; l'équipe le complète, le revoit et l'approuve.</p></div>
      <div class="step"><h3>Contrôler et réceptionner</h3><p>Inspections, PV et preuves sont tracés au fil de l'eau ; la réception s'appuie sur un dossier déjà constitué.</p></div>
    </div>
  </div>
</section>

<section id="demo" class="alt">
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Exemple illustratif</p>
      <h2>Un brouillon d'ITP qui dit clairement ce qu'il ne sait pas.</h2>
      <p class="lead">Les éléments que l'outil ne peut pas déterminer seul sont signalés, jamais complétés au hasard.</p>
    </div>
    <div class="demo" role="img" aria-label="Exemple illustratif d'un brouillon d'ITP préparé par CIVILINA IA">
      <div class="demo-head"><strong>CIVILINA IA — Préparation d'un ITP</strong><small>Exemple illustratif</small></div>
      <div class="demo-body">
        <div class="ask"><small>Votre demande</small>Préparer un ITP pour le coulage d'une dalle en béton armé, bâtiment R+4.</div>
        <div class="answer">
          <p><strong>Brouillon d'ITP — 4 étapes proposées.</strong> Statut : <em>brouillon, non approuvé</em>.</p>
          <div class="table-wrap">
            <table class="draft">
              <thead><tr><th>#</th><th>Étape de contrôle</th><th>Point</th><th class="hide-sm">Critère d'acceptation</th><th>Preuve</th></tr></thead>
              <tbody>
                <tr><td>1</td><td>Réception des armatures</td><td class="pt">H</td><td class="hide-sm">Conformité aux plans d'exécution</td><td>PV de réception</td></tr>
                <tr><td>2</td><td>Contrôle du coffrage</td><td class="pt">W</td><td class="hide-sm">Géométrie et stabilité selon plans</td><td>Fiche de contrôle</td></tr>
                <tr><td>3</td><td>Essai de consistance</td><td class="pt">W</td><td class="hide-sm"><span class="miss">À renseigner depuis la spécification du projet</span></td><td>Fiche d'essai</td></tr>
                <tr><td>4</td><td>Prélèvement d'éprouvettes</td><td class="pt">W</td><td class="hide-sm"><span class="miss">Fréquence à confirmer</span></td><td>Bordereau de prélèvement</td></tr>
              </tbody>
            </table>
          </div>
          <div class="alerts">
            <div class="alert al-warn"><b>Donnée manquante</b>La tolérance de consistance n'est pas fournie : elle doit venir de la spécification du projet. Aucune valeur n'a été supposée.</div>
            <div class="alert al-ok"><b>Prochaine étape</b>Revue et validation par un ingénieur habilité avant toute utilisation sur chantier.</div>
          </div>
        </div>
      </div>
    </div>
    <p style="margin-top:18px"><a href="/ia/" style="color:var(--eng);font-weight:700">Comment fonctionne CIVILINA IA →</a></p>
  </div>
</section>

<section>
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Nos engagements</p>
      <h2>Un outil d'ingénieurs, avec des garde-fous d'ingénieurs.</h2>
    </div>
    <div class="guard">
      <div><b>Aucune valeur inventée</b>Pas de norme, de clause, de tolérance ni de chiffre supposé. Une absence de réponse est une réponse valable.</div>
      <div><b>L'ingénieur décide</b>Une correspondance n'est pas une applicabilité : chaque décision reste celle d'un professionnel habilité.</div>
      <div><b>Droits des normes respectés</b>La plateforme indique les références et leur structure ; votre bureau doit disposer des documents officiels.</div>
      <div><b>Données cloisonnées</b>Chaque bureau d'études dispose de son espace ; les accès entre organisations sont vérifiés et testés.</div>
      <div><b>Versions tracées</b>Auteur, date, modifications et statut de chaque document, du brouillon à l'approbation.</div>
      <div><b>Accompagnement</b>Une prise en main avec un ingénieur CIVILINA, sur un de vos projets réels.</div>
    </div>
  </div>
</section>

<section id="pilote" class="alt">
  <div class="shell">
    <div class="bet-pilot">
      <div>
        <p class="eyebrow">Programme pilote</p>
        <h2>Cinq bureaux d'études, trois mois offerts.</h2>
        <p class="lead">Nous ouvrons la plateforme à un petit nombre de bureaux d'études, pour la construire avec eux sur des projets réels.</p>
        <h3>Ce que vous recevez</h3>
        <ul class="list">
          <li>Un espace réservé à votre bureau, pour toute votre équipe</li>
          <li>3 mois d'utilisation offerts</li>
          <li>Une prise en main accompagnée sur un de vos projets</li>
          <li>Un tarif préférentiel garanti à la fin du pilote</li>
        </ul>
        <h3>Ce que nous vous demandons</h3>
        <ul class="list">
          <li>Un projet réel (ou récent) pour tester la plateforme</li>
          <li>Un retour de 30 minutes par semaine</li>
          <li>Vos remarques franches : ce qui manque, ce qui gêne</li>
        </ul>
        <p class="hint-line">Premières ouvertures prévues entre décembre 2026 et janvier 2027. Les candidatures sont examinées une par une.</p>
      </div>
      <div class="bet-form">
        <?php if ($message !== null): ?><p class="bet-msg bet-msg-<?= $e($message[0]) ?>"><?= $e($message[1]) ?></p><?php endif; ?>
        <form class="cform" method="post" action="/bureaux-etudes/">
          <input type="hidden" name="action" value="pilot">
          <div class="hp" aria-hidden="true"><label>Site web <input type="text" name="website" tabindex="-1" autocomplete="off"></label></div>
          <div class="row2">
            <label>Nom <input type="text" name="name" required autocomplete="name" maxlength="120"></label>
            <label>Fonction <input type="text" name="position" autocomplete="organization-title" maxlength="160"></label>
          </div>
          <div class="row2">
            <label>Bureau d'études <input type="text" name="company" required autocomplete="organization" maxlength="160"></label>
            <label>Ville / pays <input type="text" name="city" maxlength="120"></label>
          </div>
          <div class="row2">
            <label>E-mail <input type="email" name="email" required autocomplete="email" maxlength="180"></label>
            <label>Téléphone <input type="tel" name="phone" autocomplete="tel" maxlength="80"></label>
          </div>
          <div class="row2">
            <label>Taille de l'équipe
              <select name="size"><?php foreach (CIVILINA_BET_SIZES as $key => $label): ?><option value="<?= $e($key) ?>"><?= $e($label) ?></option><?php endforeach; ?></select>
            </label>
            <label>Outils actuels
              <select name="tools"><?php foreach (CIVILINA_BET_TOOLS as $key => $label): ?><option value="<?= $e($key) ?>"><?= $e($label) ?></option><?php endforeach; ?></select>
            </label>
          </div>
          <fieldset class="bet-domains"><legend>Vos domaines</legend>
            <?php foreach (CIVILINA_BET_DOMAINS as $key => $label): ?><label><input type="checkbox" name="domains[]" value="<?= $e($key) ?>"> <?= $e($label) ?></label><?php endforeach; ?>
          </fieldset>
          <label>Votre besoin principal <textarea name="message" rows="4" maxlength="2000" placeholder="Le type de projets, ce qui vous fait perdre le plus de temps aujourd'hui…"></textarea></label>
          <button type="submit" class="btn">Envoyer ma candidature</button>
          <p class="small">Vos coordonnées servent uniquement à étudier votre candidature et à vous recontacter. Voir la page <a href="/confidentialite/">Confidentialité</a>.</p>
        </form>
      </div>
    </div>
  </div>
</section>

<section>
  <div class="shell">
    <div class="section-head">
      <p class="eyebrow">Questions fréquentes</p>
      <h2>Ce que l'on nous demande souvent.</h2>
    </div>
    <div class="faq">
      <details open><summary>Le pilote est-il vraiment gratuit ?</summary><p>Oui : trois mois d'utilisation offerts, en échange d'un retour régulier. Le tarif de la version commerciale sera annoncé avant la fin du pilote, avec des conditions préférentielles pour les bureaux pilotes.</p></details>
      <details><summary>La plateforme donne-t-elle accès au texte des normes ?</summary><p>Non. Elle aide à identifier la norme, l'édition et la clause applicables. Le texte des normes reste soumis aux droits de leurs éditeurs : votre bureau doit disposer des documents officiels.</p></details>
      <details><summary>Mes données sont-elles visibles par d'autres bureaux ?</summary><p>Non. Chaque bureau d'études dispose de son propre espace ; les accès entre organisations sont contrôlés et couverts par des tests automatisés.</p></details>
      <details><summary>Faut-il installer un logiciel ?</summary><p>Non : la plateforme s'utilise dans le navigateur. La prise en main se fait avec un ingénieur CIVILINA, à distance ou sur place.</p></details>
      <details><summary>CIVILINA IA remplace-t-il l'ingénieur ?</summary><p>Non. Il prépare des brouillons structurés à partir de règles métier et signale ce qu'il ne sait pas. L'approbation, la signature et la décision de conformité restent humaines.</p></details>
      <details><summary>Quels bureaux d'études recherchez-vous ?</summary><p>Des bureaux de 2 à 50 ingénieurs en structures, routes, VRD, géotechnique ou bâtiment, prêts à tester la plateforme sur un projet et à nous dire franchement ce qui doit changer.</p></details>
    </div>
  </div>
</section>
<?php
civilina_public_end();
