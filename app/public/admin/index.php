<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_admin.php';

$user = civilina_require_admin();
$isSuper = civilina_is_super_admin($user);
$db = civilina_db();

$view = (string) ($_GET['view'] ?? 'dashboard');
if (!array_key_exists($view, CIVILINA_ADMIN_NAV) && !in_array($view, array_merge(['contact', 'article', 'preview'], CIVILINA_NL_VIEWS, CIVILINA_SALES_VIEWS), true)) {
    $view = 'dashboard';
}
if (in_array($view, CIVILINA_SUPER_ADMIN_VIEWS, true) && !$isSuper) {
    $view = 'dashboard';
}

// Scheduled articles whose time has come are published on the next request.
try {
    civilina_articles_publish_due();
} catch (Throwable $e) {
    error_log('civilina scheduled publishing: ' . $e->getMessage());
}

// Daily automatic backup (M5), on the first request after 24 h.
try {
    civilina_backup_due();
} catch (Throwable $e) {
    error_log('civilina backup: ' . $e->getMessage());
}

// Newsletter: due campaigns, queued e-mails, waiting confirmations (longer budget on the campaign page).
try {
    if ($view !== 'campaign_preview') {
        civilina_newsletter_process($view === 'campaign' && $_SERVER['REQUEST_METHOD'] === 'GET' ? 20.0 : 3.0);
    }
} catch (Throwable $e) {
    error_log('civilina newsletter: ' . $e->getMessage());
}

/* ---------- Actions (POST, then redirect) ---------- */

if ($view === 'preview' || $view === 'campaign_preview') {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        civilina_verify_csrf();
    }
    $view === 'preview' ? civilina_admin_article_preview() : civilina_admin_campaign_preview();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    civilina_verify_csrf();
    $action = (string) ($_POST['action'] ?? '');

    if ($view === 'article') {
        civilina_admin_journal_post($user);
    }
    if ($view === 'backups') {
        civilina_admin_backups_post($user);
    }
    if (in_array($view, CIVILINA_SALES_VIEWS, true)) {
        civilina_admin_sales_post($view, $user);
    }
    if (in_array($view, CIVILINA_NL_VIEWS, true)) {
        civilina_admin_newsletter_post($view, $user, $isSuper);
    }

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
    $newsletter = civilina_nl_counts();
    $pipeline = civilina_leads_summary();
    $waiting = $db->prepare("SELECT COUNT(*) FROM contacts WHERE status = 'new' AND created_at < ?");
    $waiting->execute([date('c', strtotime('-48 hours'))]);
    $oldNew = (int) $waiting->fetchColumn();
    $audience = civilina_stats_totals(...civilina_stats_range(7));
    $audiencePrev = civilina_stats_totals(...civilina_stats_range(7, 1));
    $audienceTrend = civilina_stats_trend($audience['visits'], $audiencePrev['visits']);

    civilina_admin_start('Tableau de bord', $view, $user);
    ?>
    <?php if ($pipeline['late'] > 0): ?>
      <p class="bo-alert"><?= $pipeline['late'] ?> relance(s) de prospect en retard. <a href="<?= h(civilina_admin_url('sales', ['stage' => 'relances'])) ?>">Les voir →</a></p>
    <?php endif; ?>
    <?php if ($oldNew > 0): ?>
      <p class="bo-alert"><?= $oldNew ?> demande<?= $oldNew > 1 ? 's' : '' ?> de contact attend<?= $oldNew > 1 ? 'ent' : '' ?> une réponse depuis plus de 48 h. <a href="<?= h(civilina_admin_url('contacts', ['status' => 'new'])) ?>">Les traiter →</a></p>
    <?php endif; ?>

    <section class="kpis">
      <article class="kpi"><span>Articles publiés</span><strong><?= count($articles) ?></strong><small><?= (int) ($articlesByAxis['engineering'] ?? 0) ?> Engineering · <?= (int) ($articlesByAxis['entreprise'] ?? 0) ?> Entreprise · <?= (int) ($articlesByAxis['digital'] ?? 0) ?> Digital</small></article>
      <article class="kpi"><span>Demandes de contact</span><strong><?= $contactTotal ?></strong><small><?= $contactNew ?> à traiter · <?= $contactLast30 ?> sur 30 jours</small></article>
      <article class="kpi"><span>Comptes inscrits</span><strong><?= $userTotal ?></strong><small><?= (int) ($usersByRole['admin'] ?? 0) ?> admin · <?= $userTotal - (int) ($usersByRole['admin'] ?? 0) ?> membres</small></article>
      <article class="kpi"><span>Tests MBTI</span><strong><?= $mbtiCount ?></strong><small>Résultats enregistrés</small></article>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('newsletter')) ?>"><span>Abonnés newsletter</span><strong><?= $newsletter['status']['confirmed'] ?></strong><small><?= $newsletter['status']['pending'] ?> en attente · +<?= $newsletter['confirmed_30d'] ?> sur 30 jours</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('sales')) ?>"><span>Prospects ouverts</span><strong><?= $pipeline['open'] ?></strong><small><?= $pipeline['due'] ?> relance(s) à faire · <?= $pipeline['stages']['proposition'] ?> proposition(s)</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('stats')) ?>"><span>Visites · 7 jours</span><strong><?= $audience['visits'] ?></strong><small><?= $audience['views'] ?> pages vues<?php if ($audienceTrend !== null): ?> · <?= $audienceTrend >= 0 ? '+' : '' ?><?= $audienceTrend ?> % vs 7 j précédents<?php endif; ?></small></a>
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
          <li class="done"><b>M1</b> Statistiques d'audience <small>19/11</small></li>
          <li class="done"><b>M2</b> Éditeur du Journal <small>24/11</small></li>
          <li class="done"><b>M3</b> Newsletter <small>29/11 · 1er envoi 01/12</small></li>
          <li class="done"><b>M4</b> Offres et prospection <small>07/12</small></li>
          <li class="done"><b>M5</b> Recette et sécurité <small>10/12</small></li>
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

if (in_array($view, CIVILINA_NL_VIEWS, true)) {
    civilina_admin_newsletter_view($view, $user, $isSuper);
    exit;
}

if ($view === 'journal') {
    civilina_admin_journal_list($user);
    exit;
}

if ($view === 'article') {
    civilina_admin_article_form($user);
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

if ($view === 'backups') {
    civilina_admin_backups_view($user);
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
        'newsletter.confirmed' => 'Inscription newsletter confirmée',
        'newsletter.unsubscribed' => 'Désinscription newsletter',
        'newsletter.resubscribed' => 'Réinscription newsletter',
        'newsletter.blocked' => 'Abonné bloqué par Brevo',
        'newsletter.invited' => 'Invitation newsletter envoyée',
        'newsletter.deleted' => 'Abonné supprimé',
        'newsletter.exported' => 'Export des abonnés',
        'newsletter.settings' => 'Réglages newsletter modifiés',
        'newsletter.key_saved' => 'Clé Brevo enregistrée',
        'newsletter.key_removed' => 'Clé Brevo supprimée',
        'newsletter.campaign_created' => 'Campagne créée',
        'newsletter.campaign_deleted' => 'Campagne supprimée',
        'newsletter.campaign_scheduled' => 'Campagne programmée',
        'newsletter.campaign_started' => 'Envoi de campagne lancé',
        'newsletter.campaign_sent' => 'Campagne envoyée',
        'newsletter.schedule_failed' => "Échec d'une campagne programmée",
        'article.regenerated' => "Pages d'articles régénérées",
        'lead.saved' => 'Prospect enregistré',
        'lead.deleted' => 'Prospect supprimé',
        'leads.imported' => 'Import de prospects',
        'offers.published' => 'Offres publiées',
        'seo.applied' => 'Référencement appliqué',
        'backup.created' => 'Sauvegarde créée',
        'backup.failed' => 'Échec de sauvegarde',
        'backup.verified' => 'Sauvegarde vérifiée',
        'backup.downloaded' => 'Sauvegarde téléchargée',
        'security.hardened' => 'Durcissement appliqué',
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

if ($view === 'stats') {
    $periods = [7 => '7 jours', 30 => '30 jours', 90 => '90 jours'];
    $days = (int) ($_GET['days'] ?? 30);
    if (!isset($periods[$days])) {
        $days = 30;
    }
    [$from, $to] = civilina_stats_range($days);
    [$prevFrom, $prevTo] = civilina_stats_range($days, 1);
    $totals = civilina_stats_totals($from, $to);
    $previous = civilina_stats_totals($prevFrom, $prevTo);
    $daily = civilina_stats_daily($from, $to);
    $pages = civilina_stats_pages($from, $to, 15);
    $sources = civilina_stats_sources($from, $to);
    $referrers = civilina_stats_referrers($from, $to);
    $devices = civilina_stats_devices($from, $to);
    $axes = civilina_stats_axes($from, $to);
    $contacts = civilina_stats_contacts($from, $to);
    $since = (string) $db->query('SELECT MIN(day) FROM page_views')->fetchColumn();
    $conversion = $totals['visits'] > 0 ? round($contacts * 100 / $totals['visits'], 1) : null;
    $perVisit = $totals['visits'] > 0 ? round($totals['views'] / $totals['visits'], 1) : null;
    $trend = static function (int $now, int $before): string {
        $t = civilina_stats_trend($now, $before);
        return $t === null ? 'pas de période précédente' : ($t >= 0 ? '+' : '') . $t . ' % vs période précédente';
    };
    $shortDate = static fn (string $day): string => date('d/m', strtotime($day));

    // Daily visits: inline SVG bars (rounded tops on a flat baseline, 2px gaps, native tooltip per day).
    $chartW = 720;
    $chartH = 220;
    $padL = 34;
    $padB = 26;
    $padT = 12;
    $plotW = $chartW - $padL - 6;
    $plotH = $chartH - $padB - $padT;
    $max = max(1, ...array_column($daily, 'visits'));
    $step = $max <= 5 ? 1 : ($max <= 20 ? 5 : ($max <= 100 ? 20 : (int) (10 ** floor(log10($max)))));
    $yMax = (int) (ceil($max / $step) * $step);
    $count = count($daily);
    $gap = 2;
    $barW = max(1.0, ($plotW - $gap * ($count - 1)) / $count);
    $bar = static function (float $x, float $y, float $w, float $hgt): string {
        $r = min(4.0, $w / 2, $hgt);
        $base = $y + $hgt;
        return sprintf('M%.1f %.1fV%.1fQ%.1f %.1f %.1f %.1fH%.1fQ%.1f %.1f %.1f %.1fV%.1fZ', $x, $base, $y + $r, $x, $y, $x + $r, $y, $x + $w - $r, $x + $w, $y, $x + $w, $y + $r, $base);
    };

    civilina_admin_start('Statistiques', $view, $user);
    ?>
    <div class="filters">
      <nav class="seg" aria-label="Période">
        <?php foreach ($periods as $value => $label): ?>
          <a class="<?= $value === $days ? 'on' : '' ?>" href="<?= h(civilina_admin_url('stats', ['days' => $value])) ?>"><?= h($label) ?></a>
        <?php endforeach; ?>
      </nav>
      <span class="count">Du <?= h($shortDate($from)) ?> au <?= h($shortDate($to)) ?><?= $since !== '' ? ' · mesure active depuis le ' . h(date('d/m/Y', strtotime($since))) : '' ?></span>
    </div>

    <?php if ($since === ''): ?>
      <p class="bo-note">Aucune visite enregistrée pour l'instant. Les chiffres apparaîtront dès les premières visites du site public (vos propres visites d'administrateur connecté ne sont pas comptées).</p>
    <?php endif; ?>

    <section class="kpis">
      <article class="kpi"><span>Visites</span><strong><?= $totals['visits'] ?></strong><small><?= h($trend($totals['visits'], $previous['visits'])) ?></small></article>
      <article class="kpi"><span>Pages vues</span><strong><?= $totals['views'] ?></strong><small><?= h($trend($totals['views'], $previous['views'])) ?></small></article>
      <article class="kpi"><span>Pages par visite</span><strong><?= $perVisit === null ? '—' : h(number_format($perVisit, 1, ',', ' ')) ?></strong><small>Profondeur de lecture</small></article>
      <article class="kpi"><span>Demandes de contact</span><strong><?= $contacts ?></strong><small>Sur la même période</small></article>
      <article class="kpi"><span>Taux de conversion</span><strong><?= $conversion === null ? '—' : h(number_format($conversion, 1, ',', ' ')) . ' %' ?></strong><small>Demandes ÷ visites</small></article>
    </section>

    <div class="bo-grid">
      <section class="panel panel-wide">
        <div class="panel-head"><h2>Visites par jour</h2><small>Survolez une barre pour le détail</small></div>
        <svg class="chart" viewBox="0 0 <?= $chartW ?> <?= $chartH ?>" role="img" aria-label="Visites par jour, du <?= h($shortDate($from)) ?> au <?= h($shortDate($to)) ?>">
          <?php for ($v = 0; $v <= $yMax; $v += $step):
              $y = $padT + $plotH - $plotH * $v / $yMax; ?>
            <line class="<?= $v === 0 ? 'axis' : 'grid' ?>" x1="<?= $padL ?>" x2="<?= $chartW - 6 ?>" y1="<?= round($y, 1) ?>" y2="<?= round($y, 1) ?>"/>
            <text x="<?= $padL - 6 ?>" y="<?= round($y + 4, 1) ?>" text-anchor="end"><?= $v ?></text>
          <?php endfor; ?>
          <?php foreach ($daily as $i => $d):
              $x = $padL + $i * ($barW + $gap);
              $hgt = $plotH * $d['visits'] / $yMax; ?>
            <g class="day">
              <title><?= h(date('d/m', strtotime($d['day']))) ?> : <?= $d['visits'] ?> visite<?= $d['visits'] > 1 ? 's' : '' ?>, <?= $d['views'] ?> page<?= $d['views'] > 1 ? 's' : '' ?> vue<?= $d['views'] > 1 ? 's' : '' ?></title>
              <rect class="hit" x="<?= round($x, 1) ?>" y="<?= $padT ?>" width="<?= round($barW + $gap, 1) ?>" height="<?= $plotH ?>"/>
              <?php if ($d['visits'] > 0): ?><path class="bar" d="<?= $bar($x, $padT + $plotH - $hgt, $barW, $hgt) ?>"/><?php endif; ?>
            </g>
          <?php endforeach; ?>
          <?php foreach (array_unique([0, intdiv($count - 1, 2), $count - 1]) as $i):
              $x = $padL + $i * ($barW + $gap) + $barW / 2; ?>
            <text x="<?= round($x, 1) ?>" y="<?= $chartH - 6 ?>" text-anchor="<?= $i === 0 ? 'start' : ($i === $count - 1 ? 'end' : 'middle') ?>"><?= h($shortDate($daily[$i]['day'])) ?></text>
          <?php endforeach; ?>
        </svg>
        <details class="data-table">
          <summary>Voir le tableau des données</summary>
          <div class="table-wrap"><table>
            <thead><tr><th>Jour</th><th>Visites</th><th>Pages vues</th></tr></thead>
            <tbody><?php foreach (array_reverse($daily) as $d): ?><tr><td><?= h(date('d/m/Y', strtotime($d['day']))) ?></td><td><?= $d['visits'] ?></td><td><?= $d['views'] ?></td></tr><?php endforeach; ?></tbody>
          </table></div>
        </details>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Pages vues par axe</h2></div>
        <?php $axisTotal = max(1, array_sum($axes)); $axisLabels = ['engineering' => 'Engineering', 'entreprise' => 'Entreprise', 'digital' => 'Digital', 'other' => 'Accueil, Journal, IA']; ?>
        <ul class="bars">
          <?php foreach ($axisLabels as $key => $label): ?>
            <li><span><?= h($label) ?></span><meter class="m-<?= h($key) ?>" min="0" max="<?= $axisTotal ?>" value="<?= (int) $axes[$key] ?>"><?= (int) $axes[$key] ?></meter><b><?= (int) $axes[$key] ?></b></li>
          <?php endforeach; ?>
        </ul>
        <p class="hint">Page de l'axe et articles du Journal rattachés à cet axe.</p>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>D'où viennent les visites</h2></div>
        <?php $sourceTotal = max(1, array_sum($sources)); ?>
        <ul class="bars">
          <?php foreach (CIVILINA_STATS_SOURCES as $key => $label): $n = (int) ($sources[$key] ?? 0); ?>
            <li><span><?= h($label) ?></span><meter min="0" max="<?= $sourceTotal ?>" value="<?= $n ?>"><?= $n ?></meter><b><?= $n ?></b></li>
          <?php endforeach; ?>
        </ul>
        <?php if ($referrers): ?>
          <h3>Sites d'origine</h3>
          <ul class="list compact"><?php foreach ($referrers as $hostName => $n): ?><li><?= h($hostName) ?> <small><?= (int) $n ?> visite<?= $n > 1 ? 's' : '' ?></small></li><?php endforeach; ?></ul>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Appareils</h2></div>
        <?php $deviceTotal = max(1, array_sum($devices)); ?>
        <ul class="bars">
          <?php foreach (CIVILINA_STATS_DEVICES as $key => $label): $n = (int) ($devices[$key] ?? 0); ?>
            <li><span><?= h($label) ?></span><meter min="0" max="<?= $deviceTotal ?>" value="<?= $n ?>"><?= $n ?></meter><b><?= round($n * 100 / $deviceTotal) ?> %</b></li>
          <?php endforeach; ?>
        </ul>
      </section>

      <section class="panel panel-wide">
        <div class="panel-head"><h2>Pages les plus vues</h2></div>
        <?php if (!$pages): ?>
          <p class="empty">Aucune page vue sur cette période.</p>
        <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>Page</th><th>Axe</th><th>Pages vues</th><th>Visites</th></tr></thead>
          <tbody>
          <?php foreach ($pages as $row): $info = civilina_stats_page_info($row['path']); ?>
            <tr>
              <td><a href="<?= h($row['path']) ?>" target="_blank" rel="noopener"><?= h($info['label']) ?></a><?php if ($info['article']): ?><small>Article du Journal</small><?php endif; ?></td>
              <td><?= $info['axis'] === 'other' ? '<span class="axis axis-a_qualifier">Général</span>' : civilina_axis_badge($info['axis']) ?></td>
              <td><?= (int) $row['views'] ?></td>
              <td><?= (int) $row['visits'] ?></td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </section>
    </div>
    <p class="hint">Mesure sans cookie : une visite = un visiteur sur une journée (empreinte anonyme renouvelée chaque jour, adresse IP jamais conservée). Robots et administrateurs connectés exclus. Données gardées <?= CIVILINA_STATS_RETENTION_DAYS ?> jours.</p>
    <?php
    civilina_admin_end();
    exit;
}

/* Offers and prospecting (M4) */
civilina_admin_sales_view($view, $user);
