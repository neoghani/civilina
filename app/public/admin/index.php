<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_admin.php';

$user = civilina_require_admin();
$isSuper = civilina_is_super_admin($user);
$db = civilina_db();

$view = (string) ($_GET['view'] ?? 'dashboard');
if (!array_key_exists($view, CIVILINA_ADMIN_NAV) && $view !== 'contact') {
    $view = 'dashboard';
}
if (in_array($view, CIVILINA_SUPER_ADMIN_VIEWS, true) && !$isSuper) {
    $view = 'dashboard';
}

/* ---------- Actions (POST, then redirect) ---------- */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    civilina_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'update_contact') {
        $id = (string) ($_POST['id'] ?? '');
        $status = (string) ($_POST['status'] ?? '');
        $axis = (string) ($_POST['axis'] ?? '');
        $note = mb_substr(trim((string) ($_POST['note'] ?? '')), 0, 4000);
        if (array_key_exists($status, CIVILINA_CONTACT_STATUSES) && array_key_exists($axis, CIVILINA_AXES)) {
            $db->prepare('UPDATE contacts SET status = ?, axis = ?, note = ?, updated_at = ? WHERE id = ?')
                ->execute([$status, $axis, $note, date('c'), $id]);
            civilina_audit('contact.updated', $id . ' → ' . $status . ', ' . $axis, (string) $user['id']);
            civilina_flash('Demande mise à jour.');
        }
        header('Location: ' . civilina_admin_url('contact', ['id' => $id]), true, 303);
        exit;
    }

    if ($action === 'set_role' && $isSuper) {
        $target = civilina_find_user_by_id((string) ($_POST['id'] ?? ''));
        $role = (string) ($_POST['role'] ?? '');
        $allowed = array_merge(['admin' => 'Administrateur'], civilina_allowed_account_types());
        if ($target && (string) $target['id'] !== (string) $user['id'] && array_key_exists($role, $allowed)) {
            $previous = (string) ($target['role'] ?? '');
            $target['role'] = $role;
            if ($role !== 'admin') {
                unset($target['super_admin']);
            }
            $target['updated_at'] = gmdate('c');
            civilina_save_user($target);
            civilina_audit('user.role', $target['id'] . ' : ' . $previous . ' → ' . $role, (string) $user['id']);
            civilina_flash('Rôle mis à jour.');
        } else {
            civilina_flash('Modification refusée : vous ne pouvez pas changer votre propre rôle.');
        }
        header('Location: ' . civilina_admin_url('users'), true, 303);
        exit;
    }

    header('Location: ' . civilina_admin_url($view), true, 303);
    exit;
}

/* ---------- Views ---------- */

$csrf = civilina_csrf_token();

if ($view === 'dashboard') {
    $articles = civilina_journal_articles();
    $articlesByAxis = array_count_values(array_column($articles, 'axis'));
    $contactTotal = (int) $db->query('SELECT COUNT(*) FROM contacts')->fetchColumn();
    $contactNew = (int) $db->query("SELECT COUNT(*) FROM contacts WHERE status = 'new'")->fetchColumn();
    $since = date('c', strtotime('-30 days'));
    $last30 = $db->prepare('SELECT COUNT(*) FROM contacts WHERE created_at >= ?');
    $last30->execute([$since]);
    $contactLast30 = (int) $last30->fetchColumn();
    $contactsByAxis = $db->query('SELECT axis, COUNT(*) AS n FROM contacts GROUP BY axis')->fetchAll(PDO::FETCH_KEY_PAIR);
    $recentContacts = $db->query('SELECT id, created_at, status, axis, name, company, need FROM contacts ORDER BY created_at DESC LIMIT 5')->fetchAll();
    $usersByRole = $db->query('SELECT role, COUNT(*) AS n FROM users GROUP BY role')->fetchAll(PDO::FETCH_KEY_PAIR);
    $userTotal = array_sum($usersByRole);
    $mbtiCount = civilina_mbti_results_count();
    $waiting = $db->prepare("SELECT COUNT(*) FROM contacts WHERE status = 'new' AND created_at < ?");
    $waiting->execute([date('c', strtotime('-48 hours'))]);
    $oldNew = (int) $waiting->fetchColumn();

    civilina_admin_start('Tableau de bord', $view, $user);
    ?>
    <?php if ($oldNew > 0): ?>
      <p class="bo-alert"><?= $oldNew ?> demande<?= $oldNew > 1 ? 's' : '' ?> de contact attend<?= $oldNew > 1 ? 'ent' : '' ?> une réponse depuis plus de 48 h. <a href="<?= h(civilina_admin_url('contacts', ['status' => 'new'])) ?>">Les traiter →</a></p>
    <?php endif; ?>

    <section class="kpis">
      <article class="kpi"><span>Articles publiés</span><strong><?= count($articles) ?></strong><small><?= (int) ($articlesByAxis['engineering'] ?? 0) ?> Engineering · <?= (int) ($articlesByAxis['entreprise'] ?? 0) ?> Entreprise · <?= (int) ($articlesByAxis['digital'] ?? 0) ?> Digital</small></article>
      <article class="kpi"><span>Demandes de contact</span><strong><?= $contactTotal ?></strong><small><?= $contactNew ?> à traiter · <?= $contactLast30 ?> sur 30 jours</small></article>
      <article class="kpi"><span>Comptes inscrits</span><strong><?= $userTotal ?></strong><small><?= (int) ($usersByRole['admin'] ?? 0) ?> admin · <?= $userTotal - (int) ($usersByRole['admin'] ?? 0) ?> membres</small></article>
      <article class="kpi"><span>Tests MBTI</span><strong><?= $mbtiCount ?></strong><small>Résultats enregistrés</small></article>
      <article class="kpi kpi-soon"><span>Audience du site</span><strong>—</strong><small>Mesure des visites activée au jalon M1, jeudi 19/11</small></article>
    </section>

    <div class="bo-grid">
      <section class="panel panel-wide">
        <div class="panel-head"><h2>Dernières demandes</h2><a href="<?= h(civilina_admin_url('contacts')) ?>">Toutes les demandes →</a></div>
        <?php if (!$recentContacts): ?>
          <p class="empty">Aucune demande pour le moment.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Date</th><th>Nom</th><th>Besoin</th><th>Axe</th><th>Statut</th></tr></thead>
          <tbody>
          <?php foreach ($recentContacts as $c): ?>
            <tr>
              <td><?= h(civilina_admin_datetime($c['created_at'])) ?></td>
              <td><a href="<?= h(civilina_admin_url('contact', ['id' => $c['id']])) ?>"><?= h($c['name']) ?></a><?php if ($c['company'] !== ''): ?><small><?= h($c['company']) ?></small><?php endif; ?></td>
              <td><?= h($c['need']) ?></td>
              <td><?= civilina_axis_badge($c['axis']) ?></td>
              <td><?= civilina_status_badge($c['status']) ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Demandes par axe</h2></div>
        <ul class="bars">
          <?php foreach (CIVILINA_AXES as $key => $label): $n = (int) ($contactsByAxis[$key] ?? 0); ?>
            <li><span><?= h($label) ?></span><meter class="m-<?= h($key) ?>" min="0" max="<?= max(1, $contactTotal) ?>" value="<?= $n ?>"><?= $n ?></meter><b><?= $n ?></b></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Derniers articles</h2><a href="<?= h(civilina_admin_url('journal')) ?>">Journal →</a></div>
        <ul class="list">
          <?php foreach (array_slice($articles, 0, 5) as $a): ?>
            <li><a href="<?= h($a['url']) ?>" target="_blank" rel="noopener"><?= h($a['title']) ?></a><small><?= civilina_axis_badge($a['axis']) ?> <?= h(civilina_format_date($a['date'])) ?></small></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Feuille de route</h2></div>
        <ol class="roadmap">
          <li class="done"><b>M0</b> Socle, nettoyage, sécurité <small>17/11</small></li>
          <li><b>M1</b> Statistiques d'audience <small>19/11</small></li>
          <li><b>M2</b> Éditeur du Journal <small>24/11</small></li>
          <li><b>M3</b> Newsletter <small>29/11 · 1er envoi 01/12</small></li>
          <li><b>M4</b> Offres et prospection <small>07/12</small></li>
          <li><b>M5</b> Recette et sécurité <small>10/12</small></li>
        </ol>
      </section>
    </div>
    <?php
    civilina_admin_end();
    exit;
}

if ($view === 'contacts') {
    $status = (string) ($_GET['status'] ?? '');
    $axis = (string) ($_GET['axis'] ?? '');
    $where = [];
    $params = [];
    if (array_key_exists($status, CIVILINA_CONTACT_STATUSES)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if (array_key_exists($axis, CIVILINA_AXES)) {
        $where[] = 'axis = ?';
        $params[] = $axis;
    }
    $statement = $db->prepare('SELECT id, created_at, status, axis, name, company, email, need FROM contacts' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC');
    $statement->execute($params);
    $contacts = $statement->fetchAll();

    civilina_admin_start('Demandes de contact', $view, $user);
    ?>
    <form class="filters" method="get" action="/admin/">
      <input type="hidden" name="view" value="contacts">
      <label>Statut
        <select name="status"><option value="">Tous</option>
          <?php foreach (CIVILINA_CONTACT_STATUSES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $status ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Axe
        <select name="axis"><option value="">Tous</option>
          <?php foreach (CIVILINA_AXES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $axis ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <button class="bo-btn" type="submit">Filtrer</button>
      <span class="count"><?= count($contacts) ?> demande<?= count($contacts) > 1 ? 's' : '' ?></span>
    </form>
    <section class="panel">
      <?php if (!$contacts): ?>
        <p class="empty">Aucune demande ne correspond à ces filtres.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Nom</th><th>E-mail</th><th>Besoin</th><th>Axe</th><th>Statut</th></tr></thead>
        <tbody>
        <?php foreach ($contacts as $c): ?>
          <tr>
            <td><?= h(civilina_admin_datetime($c['created_at'])) ?></td>
            <td><a href="<?= h(civilina_admin_url('contact', ['id' => $c['id']])) ?>"><?= h($c['name']) ?></a><?php if ($c['company'] !== ''): ?><small><?= h($c['company']) ?></small><?php endif; ?></td>
            <td><?= h($c['email']) ?></td>
            <td><?= h($c['need']) ?></td>
            <td><?= civilina_axis_badge($c['axis']) ?></td>
            <td><?= civilina_status_badge($c['status']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php
    civilina_admin_end();
    exit;
}

if ($view === 'contact') {
    $statement = $db->prepare('SELECT * FROM contacts WHERE id = ?');
    $statement->execute([(string) ($_GET['id'] ?? '')]);
    $c = $statement->fetch();
    if (!$c) {
        header('Location: ' . civilina_admin_url('contacts'));
        exit;
    }
    $subject = rawurlencode('Votre demande Civilina — ' . $c['need']);
    civilina_admin_start('Demande de ' . $c['name'], 'contacts', $user);
    ?>
    <p class="crumbs"><a href="<?= h(civilina_admin_url('contacts')) ?>">← Toutes les demandes</a></p>
    <div class="bo-grid">
      <section class="panel panel-wide">
        <div class="panel-head"><h2><?= h($c['need']) ?></h2><?= civilina_status_badge($c['status']) ?></div>
        <dl class="facts">
          <dt>Reçue le</dt><dd><?= h(civilina_admin_datetime($c['created_at'])) ?></dd>
          <dt>Nom</dt><dd><?= h($c['name']) ?></dd>
          <dt>Entreprise</dt><dd><?= h($c['company'] !== '' ? $c['company'] : '—') ?></dd>
          <dt>Fonction</dt><dd><?= h($c['position'] !== '' ? $c['position'] : '—') ?></dd>
          <dt>E-mail</dt><dd><a href="mailto:<?= h($c['email']) ?>?subject=<?= h($subject) ?>"><?= h($c['email']) ?></a></dd>
          <dt>Téléphone</dt><dd><?= h($c['phone'] !== '' ? $c['phone'] : '—') ?></dd>
        </dl>
        <h3>Message</h3>
        <div class="message"><?= nl2br(h($c['message'])) ?></div>
        <div class="actions"><a class="bo-btn" href="mailto:<?= h($c['email']) ?>?subject=<?= h($subject) ?>">Répondre par e-mail</a></div>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Suivi</h2></div>
        <form method="post" action="<?= h(civilina_admin_url('contact', ['id' => $c['id']])) ?>" class="stack">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>">
          <input type="hidden" name="action" value="update_contact">
          <input type="hidden" name="id" value="<?= h($c['id']) ?>">
          <label>Statut
            <select name="status"><?php foreach (CIVILINA_CONTACT_STATUSES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $c['status'] ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
          </label>
          <label>Axe
            <select name="axis"><?php foreach (CIVILINA_AXES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $c['axis'] ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
          </label>
          <label>Note interne
            <textarea name="note" rows="6" placeholder="Prochaine action, date de relance, montant estimé…"><?= h($c['note']) ?></textarea>
          </label>
          <button class="bo-btn" type="submit">Enregistrer</button>
        </form>
        <p class="hint">Dernière mise à jour : <?= h(civilina_admin_datetime($c['updated_at'])) ?></p>
      </section>
    </div>
    <?php
    civilina_admin_end();
    exit;
}

if ($view === 'journal') {
    $articles = civilina_journal_articles();
    civilina_admin_start('Journal', $view, $user);
    ?>
    <p class="bo-note">L'éditeur d'articles arrive au jalon M2 (mardi 24/11) : rédaction, aperçu et publication directement depuis cette page. En attendant, voici l'inventaire des articles en ligne.</p>
    <section class="panel">
      <div class="table-wrap"><table>
        <thead><tr><th>Titre</th><th>Axe</th><th>Publié le</th><th>Mots</th><th>Partage</th></tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><a href="<?= h($a['url']) ?>" target="_blank" rel="noopener"><?= h($a['title']) ?></a><small><?= h($a['category']) ?></small></td>
            <td><?= civilina_axis_badge($a['axis']) ?></td>
            <td><?= h(civilina_format_date($a['date'])) ?></td>
            <td><?= (int) $a['words'] ?></td>
            <td><a href="https://www.linkedin.com/sharing/share-offsite/?url=<?= h(rawurlencode('https://civilina.com' . $a['url'])) ?>" target="_blank" rel="noopener">LinkedIn ↗</a></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    <?php
    civilina_admin_end();
    exit;
}

if ($view === 'users') {
    $users = civilina_read_users();
    usort($users, static fn (array $a, array $b): int => strcmp((string) ($b['created_at'] ?? ''), (string) ($a['created_at'] ?? '')));
    $roles = array_merge(['admin' => 'Administrateur'], civilina_allowed_account_types());
    civilina_admin_start('Utilisateurs', $view, $user);
    ?>
    <p class="bo-note">Un administrateur a accès au back-office. Vérifiez régulièrement cette liste : retirez le rôle administrateur aux comptes qui n'en ont plus besoin.</p>
    <section class="panel">
      <div class="table-wrap"><table>
        <thead><tr><th>Nom</th><th>E-mail</th><th>Inscrit le</th><th>Rôle</th></tr></thead>
        <tbody>
        <?php foreach ($users as $u): $self = (string) $u['id'] === (string) $user['id']; ?>
          <tr>
            <td><?= h((string) ($u['name'] ?? '')) ?></td>
            <td><?= h((string) ($u['email'] ?? '')) ?></td>
            <td><?= h(civilina_admin_datetime((string) ($u['created_at'] ?? ''))) ?></td>
            <td>
              <?php if ($self || civilina_is_super_admin($u)): ?>
                <b><?= h(civilina_user_label($u)) ?></b><?= $self ? ' <small>(vous)</small>' : '' ?>
              <?php else: ?>
                <form method="post" action="<?= h(civilina_admin_url('users')) ?>" class="inline">
                  <input type="hidden" name="_token" value="<?= h($csrf) ?>">
                  <input type="hidden" name="action" value="set_role">
                  <input type="hidden" name="id" value="<?= h((string) $u['id']) ?>">
                  <select name="role" aria-label="Rôle"><?php foreach ($roles as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === ($u['role'] ?? '') ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
                  <button class="bo-btn bo-btn-small" type="submit">Appliquer</button>
                </form>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    <?php
    civilina_admin_end();
    exit;
}

if ($view === 'security') {
    $events = $db->query('SELECT a.created_at, a.action, a.detail, a.user_id, u.email FROM audit_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 100')->fetchAll();
    $failures24h = $db->prepare('SELECT COUNT(*) FROM login_attempts WHERE success = 0 AND created_at > ?');
    $failures24h->execute([time() - 86400]);
    $labels = [
        'login.success' => 'Connexion réussie',
        'login.failed' => 'Échec de connexion',
        'login.blocked' => 'Connexion bloquée (trop de tentatives)',
        'admin.denied' => 'Accès back-office refusé',
        'contact.updated' => 'Demande mise à jour',
        'user.role' => 'Rôle modifié',
        'db.migrated' => 'Migration des données',
    ];
    civilina_admin_start('Sécurité', $view, $user);
    ?>
    <section class="kpis">
      <article class="kpi"><span>Échecs de connexion (24 h)</span><strong><?= (int) $failures24h->fetchColumn() ?></strong><small>Blocage après 5 échecs par compte ou 20 par adresse, pendant 15 min</small></article>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Journal des actions</h2><small>100 derniers événements · adresses IP non conservées</small></div>
      <?php if (!$events): ?>
        <p class="empty">Aucun événement enregistré.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Événement</th><th>Compte</th><th>Détail</th></tr></thead>
        <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><?= h(civilina_admin_datetime($e['created_at'])) ?></td>
            <td><?= h($labels[$e['action']] ?? $e['action']) ?></td>
            <td><?= h((string) ($e['email'] ?? ($e['user_id'] !== '' ? $e['user_id'] : '—'))) ?></td>
            <td><?= h($e['detail']) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php
    civilina_admin_end();
    exit;
}

/* Modules of later milestones */
$upcoming = [
    'stats' => ['Statistiques', 'Jalon M1 — jeudi 19 novembre 2026', ['Visites par jour et par page, sans cookie', 'Articles les plus lus', 'Sources : Google, LinkedIn, accès direct', 'Taux de conversion visite → demande de contact']],
    'newsletter' => ['Newsletter', 'Jalon M3 — dimanche 29 novembre 2026, 1er envoi mardi 1er décembre', ['Formulaire d\'inscription avec confirmation par e-mail', 'Listes par axe : Engineering, Entreprise, Digital', 'Campagne pré-remplie avec les derniers articles', 'Envoi via Brevo, statistiques d\'ouverture et de clic']],
    'sales' => ['Offres et prospection', 'Jalon M4 — lundi 7 décembre 2026', ['Offres des 3 axes affichées sur le site', 'Demandes de devis rattachées à un axe', 'Ressources téléchargeables contre e-mail', 'Pipeline de prospection et relances datées']],
];
[$title, $milestone, $items] = $upcoming[$view];
civilina_admin_start($title, $view, $user);
?>
<section class="panel soon-panel">
  <span class="soon-tag"><?= h($milestone) ?></span>
  <h2>Ce module est en construction.</h2>
  <ul class="checklist">
    <?php foreach ($items as $item): ?><li><?= h($item) ?></li><?php endforeach; ?>
  </ul>
</section>
<?php
civilina_admin_end();
