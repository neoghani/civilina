<?php

declare(strict_types=1);

/*
 * Back-office: offers and prospecting (milestone M4).
 * Pipeline of leads, lead page (history, follow-up, e-mail templates), CSV
 * import, offers by axis (drafts, prices, publication) and resource figures.
 */

require_once __DIR__ . '/civilina_sales.php';

const CIVILINA_SALES_VIEWS = ['sales', 'lead', 'lead_import', 'offers', 'offer', 'resources'];

function civilina_admin_sales_tabs(string $current): string
{
    $tabs = ['sales' => 'Prospection', 'offers' => 'Offres', 'resources' => 'Modèles téléchargés', 'lead_import' => 'Import CSV'];
    $out = '<nav class="seg nl-tabs" aria-label="Offres et prospection">';
    foreach ($tabs as $view => $label) {
        $out .= '<a class="' . ($view === $current ? 'on' : '') . '" href="' . h(civilina_admin_url($view)) . '">' . h($label) . '</a>';
    }
    return $out . '</nav>';
}

function civilina_stage_badge(string $stage): string
{
    $class = ['nouveau' => 'new', 'qualifie' => 'in_progress', 'proposition' => 'in_progress', 'gagne' => 'done', 'perdu' => 'archived'][$stage] ?? 'new';
    return '<span class="status status-' . $class . '">' . h(CIVILINA_LEAD_STAGES[$stage] ?? $stage) . '</span>';
}

function civilina_admin_date(string $day): string
{
    $t = strtotime($day);
    return $t === false || $day === '' ? '—' : date('d/m/Y', $t);
}

/* ---------- POST ---------- */

function civilina_admin_sales_post(string $view, array $user): void
{
    $action = (string) ($_POST['action'] ?? '');
    $userId = (string) $user['id'];
    $back = static function (string $view, array $params = []): void {
        header('Location: ' . civilina_admin_url($view, $params), true, 303);
        exit;
    };
    $field = static fn (string $key, int $max): string => mb_substr(trim((string) preg_replace('~[ \t]+~u', ' ', (string) ($_POST[$key] ?? ''))), 0, $max);

    if ($view === 'lead') {
        $id = (int) ($_POST['id'] ?? 0);
        $lead = $id > 0 ? civilina_lead_find($id) : null;

        if ($action === 'save') {
            $email = mb_strtolower($field('email', 180));
            if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                civilina_flash('Adresse e-mail non valide.');
                $back('lead', $id > 0 ? ['id' => $id] : ['new' => 1]);
            }
            $stage = array_key_exists((string) ($_POST['stage'] ?? ''), CIVILINA_LEAD_STAGES) ? (string) $_POST['stage'] : 'nouveau';
            $axis = array_key_exists((string) ($_POST['axis'] ?? ''), CIVILINA_AXES) ? (string) $_POST['axis'] : 'a_qualifier';
            $next = (string) ($_POST['next_action_at'] ?? '');
            $next = preg_match('~^\d{4}-\d{2}-\d{2}$~', $next) ? $next : '';
            $values = [
                'axis' => $axis, 'stage' => $stage, 'name' => $field('name', 160), 'company' => $field('company', 160), 'position' => $field('position', 160),
                'email' => $email, 'phone' => $field('phone', 80), 'offer_id' => (int) ($_POST['offer_id'] ?? 0),
                'value_da' => max(0, (int) preg_replace('~\D~', '', (string) ($_POST['value_da'] ?? '0'))),
                'next_action' => $field('next_action', 200), 'next_action_at' => $next,
                'notes' => mb_substr(trim((string) ($_POST['notes'] ?? '')), 0, 4000), 'lost_reason' => $field('lost_reason', 200),
            ];
            if ($values['name'] === '' && $values['email'] === '' && $values['company'] === '') {
                civilina_flash('Renseignez au moins un nom, une entreprise ou un e-mail.');
                $back('lead', $id > 0 ? ['id' => $id] : ['new' => 1]);
            }
            $db = civilina_db();
            if ($lead) {
                $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($values)));
                $db->prepare('UPDATE leads SET ' . $set . ', updated_at = ? WHERE id = ?')->execute([...array_values($values), date('c'), $id]);
                if ($lead['stage'] !== $stage) {
                    civilina_lead_event($id, 'etape', CIVILINA_LEAD_STAGES[$lead['stage']] . ' → ' . CIVILINA_LEAD_STAGES[$stage] . ($stage === 'perdu' && $values['lost_reason'] !== '' ? ' (' . $values['lost_reason'] . ')' : ''), $userId);
                }
                civilina_flash('Prospect enregistré.');
            } else {
                $id = civilina_lead_upsert($values + ['source' => 'manuel'], 'creation', 'Saisie manuelle', $db, $userId);
                $set = implode(', ', array_map(static fn (string $k): string => $k . ' = ?', array_keys($values)));
                $db->prepare('UPDATE leads SET ' . $set . ' WHERE id = ?')->execute([...array_values($values), $id]);
                civilina_flash('Prospect créé.');
            }
            civilina_audit('lead.saved', '#' . $id, $userId);
            $back('lead', ['id' => $id]);
        }
        if (!$lead) {
            $back('sales');
        }
        if ($action === 'event') {
            $type = array_key_exists((string) ($_POST['type'] ?? ''), ['note' => 1, 'appel' => 1, 'rdv' => 1, 'email' => 1]) ? (string) $_POST['type'] : 'note';
            $detail = mb_substr(trim((string) ($_POST['detail'] ?? '')), 0, 2000);
            if ($detail !== '') {
                civilina_lead_event($id, $type, $detail, $userId);
                civilina_flash('Ajouté à l\'historique.');
            }
            $back('lead', ['id' => $id]);
        }
        if ($action === 'mail') {
            $templates = civilina_lead_templates($lead);
            $key = (string) ($_POST['template'] ?? '');
            if (isset($templates[$key]) && $lead['email'] !== '') {
                [$label, $subject, $body] = $templates[$key];
                civilina_lead_event($id, 'email', 'E-mail préparé : ' . $label, $userId);
                // Shown as a link on the lead page: the back-office CSP (form-action 'self')
                // forbids redirecting a form to mailto:. Nothing is sent from the server.
                $_SESSION['lead_mailto'] = ['id' => $id, 'label' => $label, 'url' => civilina_mailto($lead['email'], $subject, $body)];
            }
            $back('lead', ['id' => $id]);
        }
        if ($action === 'delete' && !empty($_POST['confirm_delete'])) {
            $db = civilina_db();
            $db->prepare('DELETE FROM lead_events WHERE lead_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM leads WHERE id = ?')->execute([$id]);
            civilina_audit('lead.deleted', '#' . $id, $userId);
            civilina_flash('Prospect supprimé définitivement.');
            $back('sales');
        }
        $back('lead', ['id' => $id]);
    }

    if ($view === 'lead_import' && $action === 'import') {
        $csv = (string) ($_POST['csv'] ?? '');
        if (isset($_FILES['file']) && is_uploaded_file((string) $_FILES['file']['tmp_name']) && (int) $_FILES['file']['size'] <= 1_000_000) {
            $csv = (string) file_get_contents((string) $_FILES['file']['tmp_name']);
        }
        if (!mb_check_encoding($csv, 'UTF-8')) {
            $csv = mb_convert_encoding($csv, 'UTF-8', 'Windows-1252');
        }
        $result = civilina_leads_import_csv($csv, $userId);
        civilina_flash($result['added'] . ' prospect(s) ajouté(s), ' . $result['skipped'] . ' déjà présent(s).' . ($result['errors'] ? ' ' . implode(' ', array_slice($result['errors'], 0, 5)) : ''));
        $back($result['added'] > 0 ? 'sales' : 'lead_import');
    }

    if ($view === 'offer') {
        $id = (int) ($_POST['id'] ?? 0);
        $o = $id > 0 ? civilina_offer_find($id) : null;
        if (!$o) {
            $back('offers');
        }
        if ($action === 'save' || $action === 'save_publish' || $action === 'unpublish') {
            foreach (['title' => 120, 'tagline' => 300, 'for_who' => 200, 'duration' => 80, 'price_note' => 60, 'cta_label' => 40] as $key => $max) {
                $o[$key] = $field($key, $max);
            }
            $o['deliverables'] = mb_substr(str_replace("\r\n", "\n", trim((string) ($_POST['deliverables'] ?? ''))), 0, 2000);
            foreach (['price_da', 'price_eur'] as $key) {
                $digits = preg_replace('~\D~', '', (string) ($_POST[$key] ?? ''));
                $o[$key] = $digits === '' ? '' : (string) (int) $digits;
            }
            if ($action === 'save_publish') {
                $problems = civilina_offer_problems($o);
                if ($problems !== []) {
                    civilina_offer_save($o);
                    civilina_flash('Offre enregistrée mais non mise en ligne : ' . implode(' ', $problems));
                    $back('offer', ['id' => $id]);
                }
                $o['status'] = 'published';
            }
            if ($action === 'unpublish') {
                $o['status'] = 'draft';
            }
            civilina_offer_save($o);
            if ($action !== 'save') {
                try {
                    $files = civilina_offers_publish($userId);
                    civilina_flash(($action === 'unpublish' ? 'Offre retirée du site.' : 'Offre en ligne.') . ($files ? ' Page mise à jour : ' . implode(', ', $files) : ''));
                } catch (Throwable $ex) {
                    civilina_flash('Offre enregistrée, mais la page n\'a pas pu être mise à jour : ' . $ex->getMessage());
                }
            } else {
                civilina_flash('Offre enregistrée' . ($o['status'] === 'published' ? ' : cliquez sur « Enregistrer et mettre en ligne » pour mettre la page à jour.' : ' (brouillon).'));
            }
            $back('offer', ['id' => $id]);
        }
    }

    $back($view);
}

/* ---------- Views ---------- */

function civilina_admin_sales_view(string $view, array $user): void
{
    match ($view) {
        'lead' => civilina_admin_lead($user),
        'lead_import' => civilina_admin_lead_import($user),
        'offers' => civilina_admin_offers($user),
        'offer' => civilina_admin_offer($user),
        'resources' => civilina_admin_resources($user),
        default => civilina_admin_pipeline($user),
    };
}

function civilina_admin_pipeline(array $user): void
{
    $db = civilina_db();
    $summary = civilina_leads_summary();
    $stage = (string) ($_GET['stage'] ?? 'ouverts');
    $axis = (string) ($_GET['axis'] ?? '');
    $source = (string) ($_GET['source'] ?? '');
    $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $where = [];
    $params = [];
    if ($stage === 'ouverts') {
        $where[] = "stage NOT IN ('gagne', 'perdu')";
    } elseif ($stage === 'relances') {
        $where[] = "stage NOT IN ('gagne', 'perdu') AND next_action_at != '' AND next_action_at <= ?";
        $params[] = date('Y-m-d');
    } elseif (array_key_exists($stage, CIVILINA_LEAD_STAGES)) {
        $where[] = 'stage = ?';
        $params[] = $stage;
    }
    if (array_key_exists($axis, CIVILINA_AXES)) {
        $where[] = 'axis = ?';
        $params[] = $axis;
    }
    if (array_key_exists($source, CIVILINA_LEAD_SOURCES)) {
        $where[] = 'source = ?';
        $params[] = $source;
    }
    if ($search !== '') {
        $where[] = '(name LIKE ? OR company LIKE ? OR email LIKE ?)';
        array_push($params, '%' . $search . '%', '%' . $search . '%', '%' . $search . '%');
    }
    $order = $stage === 'relances' ? 'next_action_at ASC' : "CASE WHEN next_action_at = '' THEN 1 ELSE 0 END, next_action_at ASC, updated_at DESC";
    $statement = $db->prepare('SELECT * FROM leads' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY ' . $order . ' LIMIT 500');
    $statement->execute($params);
    $leads = $statement->fetchAll();
    $today = date('Y-m-d');

    civilina_admin_start('Offres et prospection', 'sales', $user);
    echo civilina_admin_sales_tabs('sales');
    ?>
    <?php if ($summary['late'] > 0): ?>
      <p class="bo-alert"><?= $summary['late'] ?> relance<?= $summary['late'] > 1 ? 's' : '' ?> en retard. <a href="<?= h(civilina_admin_url('sales', ['stage' => 'relances'])) ?>">Les voir →</a></p>
    <?php endif; ?>
    <section class="kpis">
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('sales')) ?>"><span>Prospects ouverts</span><strong><?= $summary['open'] ?></strong><small><?= $summary['stages']['nouveau'] ?> nouveaux · <?= $summary['stages']['qualifie'] ?> qualifiés</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('sales', ['stage' => 'relances'])) ?>"><span>Relances à faire</span><strong><?= $summary['due'] ?></strong><small>aujourd'hui ou en retard</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('sales', ['stage' => 'proposition'])) ?>"><span>Propositions en cours</span><strong><?= $summary['stages']['proposition'] ?></strong><small><?= h(civilina_money($summary['proposal_value'])) ?> DA estimés</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('sales', ['stage' => 'gagne'])) ?>"><span>Gagnés · 90 jours</span><strong><?= $summary['won_90d'] ?></strong><small><?= h(civilina_money($summary['won_value_90d'])) ?> DA</small></a>
    </section>

    <form class="filters" method="get" action="/admin/">
      <input type="hidden" name="view" value="sales">
      <label>Étape
        <select name="stage">
          <option value="ouverts"<?= $stage === 'ouverts' ? ' selected' : '' ?>>Ouverts</option>
          <option value="relances"<?= $stage === 'relances' ? ' selected' : '' ?>>Relances à faire</option>
          <?php foreach (CIVILINA_LEAD_STAGES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $stage ? ' selected' : '' ?>><?= h($label) ?> (<?= $summary['stages'][$key] ?>)</option><?php endforeach; ?>
          <option value="tous"<?= $stage === 'tous' ? ' selected' : '' ?>>Tous</option>
        </select>
      </label>
      <label>Axe
        <select name="axis"><option value="">Tous</option><?php foreach (CIVILINA_AXES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $axis ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Origine
        <select name="source"><option value="">Toutes</option><?php foreach (CIVILINA_LEAD_SOURCES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $source ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select>
      </label>
      <label>Recherche <input type="search" name="q" value="<?= h($search) ?>" placeholder="Nom, entreprise, e-mail"></label>
      <button class="bo-btn" type="submit">Filtrer</button>
      <a class="bo-btn bo-btn-ghost" href="<?= h(civilina_admin_url('lead', ['new' => 1])) ?>">+ Nouveau prospect</a>
      <span class="count"><?= count($leads) ?> prospect<?= count($leads) > 1 ? 's' : '' ?></span>
    </form>

    <section class="panel">
      <?php if (!$leads): ?>
        <p class="empty">Aucun prospect ne correspond. Les demandes de contact, de devis et les téléchargements de modèles arrivent ici automatiquement ; vous pouvez aussi en ajouter ou en importer.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Prospect</th><th>Axe</th><th>Étape</th><th>Prochaine action</th><th>Origine</th><th>Mis à jour</th></tr></thead>
        <tbody>
        <?php foreach ($leads as $l): $late = $l['next_action_at'] !== '' && $l['next_action_at'] < $today && !in_array($l['stage'], ['gagne', 'perdu'], true); ?>
          <tr>
            <td><a href="<?= h(civilina_admin_url('lead', ['id' => $l['id']])) ?>"><?= h($l['name'] !== '' ? $l['name'] : ($l['email'] !== '' ? $l['email'] : $l['company'])) ?></a><?php if ($l['company'] !== ''): ?><small><?= h($l['company']) ?></small><?php endif; ?></td>
            <td><?= civilina_axis_badge($l['axis']) ?></td>
            <td><?= civilina_stage_badge($l['stage']) ?><?php if ((int) $l['value_da'] > 0): ?><small><?= h(civilina_money((int) $l['value_da'])) ?> DA</small><?php endif; ?></td>
            <td class="<?= $late ? 'late' : '' ?>"><?= h(civilina_admin_date($l['next_action_at'])) ?><?php if ($l['next_action'] !== ''): ?><small><?= h($l['next_action']) ?></small><?php endif; ?></td>
            <td><small><?= h(CIVILINA_LEAD_SOURCES[$l['source']] ?? $l['source']) ?></small></td>
            <td><?= h(civilina_admin_datetime($l['updated_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php
    civilina_admin_end();
}

function civilina_admin_lead(array $user): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $lead = $id > 0 ? civilina_lead_find($id) : null;
    if ($id > 0 && !$lead) {
        header('Location: ' . civilina_admin_url('sales'));
        exit;
    }
    $isNew = $lead === null;
    $l = $lead ?? ['id' => 0, 'name' => '', 'company' => '', 'position' => '', 'email' => '', 'phone' => '', 'axis' => 'a_qualifier', 'stage' => 'nouveau', 'offer_id' => 0, 'value_da' => 0, 'next_action' => '', 'next_action_at' => '', 'notes' => '', 'lost_reason' => '', 'source' => 'manuel', 'created_at' => date('c')];
    $csrf = civilina_csrf_token();
    $action = h(civilina_admin_url('lead', $isNew ? ['new' => 1] : ['id' => $id]));
    $offers = civilina_offers_all();
    $events = $isNew ? [] : civilina_lead_events($id);
    $downloads = [];
    if (!$isNew && $l['email'] !== '') {
        $statement = civilina_db()->prepare('SELECT resource, created_at, downloads FROM resource_downloads WHERE email = ? ORDER BY id DESC');
        $statement->execute([$l['email']]);
        $downloads = $statement->fetchAll();
    }

    civilina_admin_start($isNew ? 'Nouveau prospect' : ($l['name'] !== '' ? $l['name'] : ($l['company'] !== '' ? $l['company'] : $l['email'])), 'sales', $user);
    ?>
    <p class="crumbs"><a href="<?= h(civilina_admin_url('sales')) ?>">← Prospection</a></p>
    <?php $mailto = $_SESSION['lead_mailto'] ?? null; unset($_SESSION['lead_mailto']); ?>
    <?php if (is_array($mailto) && (int) $mailto['id'] === $id): ?>
      <p class="bo-note">E-mail « <?= h($mailto['label']) ?> » prêt. <a class="bo-btn bo-btn-small" href="<?= h($mailto['url']) ?>">Ouvrir dans ma messagerie ↗</a></p>
    <?php endif; ?>
    <div class="bo-grid">
      <section class="panel panel-wide">
        <div class="panel-head"><h2>Fiche</h2><?= $isNew ? '' : civilina_stage_badge($l['stage']) ?></div>
        <form method="post" action="<?= $action ?>" class="stack">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
          <div class="nl-row">
            <label>Nom<input type="text" name="name" maxlength="160" value="<?= h($l['name']) ?>"></label>
            <label>Entreprise<input type="text" name="company" maxlength="160" value="<?= h($l['company']) ?>"></label>
          </div>
          <div class="nl-row">
            <label>Fonction<input type="text" name="position" maxlength="160" value="<?= h($l['position']) ?>"></label>
            <label>Téléphone<input type="text" name="phone" maxlength="80" value="<?= h($l['phone']) ?>"></label>
          </div>
          <div class="nl-row">
            <label>E-mail<input type="email" name="email" maxlength="180" value="<?= h($l['email']) ?>"></label>
            <label>Axe<select name="axis"><?php foreach (CIVILINA_AXES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $l['axis'] ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="nl-row">
            <label>Étape<select name="stage"><?php foreach (CIVILINA_LEAD_STAGES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $l['stage'] ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?></select></label>
            <label>Offre concernée<select name="offer_id"><option value="0">—</option><?php foreach ($offers as $o): ?><option value="<?= $o['id'] ?>"<?= $o['id'] === (int) $l['offer_id'] ? ' selected' : '' ?>><?= h((CIVILINA_AXES[$o['axis']] ?? '') . ' · ' . ($o['title'] !== '' ? $o['title'] : CIVILINA_OFFER_TIERS[$o['tier']])) ?></option><?php endforeach; ?></select></label>
          </div>
          <div class="nl-row">
            <label>Montant estimé (DA)<input type="text" name="value_da" inputmode="numeric" value="<?= (int) $l['value_da'] > 0 ? (int) $l['value_da'] : '' ?>"></label>
            <label>Raison si perdu<input type="text" name="lost_reason" maxlength="200" value="<?= h($l['lost_reason']) ?>" placeholder="Budget, délai, concurrent…"></label>
          </div>
          <div class="nl-row">
            <label>Prochaine action<input type="text" name="next_action" maxlength="200" value="<?= h($l['next_action']) ?>" placeholder="Appeler, envoyer la proposition…"></label>
            <label>Date de relance<input type="date" name="next_action_at" value="<?= h($l['next_action_at']) ?>"></label>
          </div>
          <label>Notes<textarea name="notes" rows="4"><?= h($l['notes']) ?></textarea></label>
          <div class="actions"><button class="bo-btn" type="submit">Enregistrer</button></div>
        </form>
      </section>

      <?php if (!$isNew): ?>
      <section class="panel">
        <div class="panel-head"><h2>Écrire</h2></div>
        <?php if ($l['email'] === ''): ?>
          <p class="hint">Ajoutez un e-mail pour préparer un message.</p>
        <?php else: ?>
          <form method="post" action="<?= $action ?>" class="stack">
            <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="mail"><input type="hidden" name="id" value="<?= $id ?>">
            <?php foreach (civilina_lead_templates($l) as $key => [$label]): ?>
              <button class="bo-btn bo-btn-ghost" type="submit" name="template" value="<?= h($key) ?>"><?= h($label) ?></button>
            <?php endforeach; ?>
          </form>
          <p class="hint">Choisissez un modèle, puis cliquez sur « Ouvrir dans ma messagerie » : le message s'ouvre pré-rempli, vous le relisez et l'envoyez vous-même. La préparation est notée dans l'historique.</p>
        <?php endif; ?>

        <div class="panel-head nl-sub"><h2>Ajouter à l'historique</h2></div>
        <form method="post" action="<?= $action ?>" class="stack">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="event"><input type="hidden" name="id" value="<?= $id ?>">
          <label>Type<select name="type"><option value="note">Note</option><option value="appel">Appel</option><option value="rdv">Rendez-vous</option><option value="email">E-mail envoyé</option></select></label>
          <label>Détail<textarea name="detail" rows="3" required></textarea></label>
          <button class="bo-btn" type="submit">Ajouter</button>
        </form>
      </section>

      <section class="panel panel-wide">
        <div class="panel-head"><h2>Historique</h2><small>Origine : <?= h(CIVILINA_LEAD_SOURCES[$l['source']] ?? $l['source']) ?> · créé le <?= h(civilina_admin_datetime($l['created_at'])) ?></small></div>
        <?php if ($downloads): ?>
          <p class="hint">Modèles demandés : <?php foreach ($downloads as $d): ?><?= h(CIVILINA_RESOURCES[$d['resource']]['title'] ?? $d['resource']) ?> (<?= h(civilina_admin_date($d['created_at'])) ?><?= (int) $d['downloads'] > 0 ? ', téléchargé' : ', pas encore téléchargé' ?>) · <?php endforeach; ?></p>
        <?php endif; ?>
        <ul class="timeline">
          <?php foreach ($events as $ev): ?>
            <li><span class="tl-type"><?= h(CIVILINA_LEAD_EVENT_TYPES[$ev['type']] ?? $ev['type']) ?></span> <span class="tl-date"><?= h(civilina_admin_datetime($ev['created_at'])) ?></span><p><?= nl2br(h($ev['detail'])) ?></p></li>
          <?php endforeach; ?>
        </ul>
        <details class="row-actions"><summary>Supprimer ce prospect</summary>
          <form method="post" action="<?= $action ?>" class="stack">
            <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= $id ?>">
            <label class="check"><input type="checkbox" name="confirm_delete" value="1" required> Effacer la fiche et son historique</label>
            <button class="bo-btn bo-btn-small bo-btn-danger" type="submit">Supprimer définitivement</button>
          </form>
        </details>
      </section>
      <?php endif; ?>
    </div>
    <?php
    civilina_admin_end();
}

function civilina_admin_lead_import(array $user): void
{
    $csrf = civilina_csrf_token();
    civilina_admin_start('Importer des prospects', 'sales', $user);
    echo civilina_admin_sales_tabs('lead_import');
    ?>
    <div class="bo-grid">
      <section class="panel panel-wide">
        <form method="post" action="<?= h(civilina_admin_url('lead_import')) ?>" class="stack" enctype="multipart/form-data">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="import">
          <label>Fichier CSV <small>(Excel : Fichier › Enregistrer sous › CSV, séparateur point-virgule)</small><input type="file" name="file" accept=".csv,text/csv"></label>
          <label>… ou collez les lignes<textarea name="csv" rows="8" class="code" placeholder="nom;entreprise;fonction;email;telephone;axe;notes&#10;Karim Benali;BET Atlas;Directeur;k.benali@exemple.dz;0550 00 00 00;engineering;Salon Batimatec"></textarea></label>
          <button class="bo-btn" type="submit">Importer</button>
        </form>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Format</h2></div>
        <ul class="nl-steps">
          <li>1re ligne : les en-têtes <b>nom ; entreprise ; fonction ; email ; telephone ; axe ; notes</b> (dans n'importe quel ordre).</li>
          <li><b>axe</b> : engineering, entreprise ou digital (sinon « À qualifier »).</li>
          <li>Les adresses déjà présentes dans un prospect ouvert sont ignorées.</li>
          <li>Prospection B2B : contactez des professionnels pour une offre liée à leur fonction, présentez-vous clairement et retirez immédiatement toute personne qui le demande. N'ajoutez pas ces contacts à la newsletter sans leur accord.</li>
        </ul>
      </section>
    </div>
    <?php
    civilina_admin_end();
}

function civilina_admin_offers(array $user): void
{
    $offers = civilina_offers_all();
    civilina_admin_start('Offres', 'sales', $user);
    echo civilina_admin_sales_tabs('offers');
    ?>
    <p class="bo-note">Chaque axe présente trois offres sur sa page (section « Offres », avant le contact). Le prix « À partir de » s'affiche quand un montant est renseigné, sinon « Sur devis ». Une offre en brouillon n'apparaît pas sur le site.</p>
    <section class="panel">
      <div class="table-wrap"><table>
        <thead><tr><th>Axe</th><th>Niveau</th><th>Offre</th><th>Prix affiché</th><th>Statut</th><th>Mise à jour</th></tr></thead>
        <tbody>
        <?php foreach ($offers as $o): ?>
          <tr>
            <td><?= civilina_axis_badge($o['axis']) ?></td>
            <td><?= h(CIVILINA_OFFER_TIERS[$o['tier']] ?? $o['tier']) ?></td>
            <td><a href="<?= h(civilina_admin_url('offer', ['id' => $o['id']])) ?>"><?= h($o['title'] !== '' ? $o['title'] : '(sans titre)') ?></a></td>
            <td><?= h(civilina_offer_price($o)) ?></td>
            <td><?= $o['status'] === 'published' ? '<span class="status status-done">En ligne</span>' : '<span class="status status-new">Brouillon</span>' ?></td>
            <td><?= h(civilina_admin_datetime($o['updated_at'])) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    <?php
    civilina_admin_end();
}

function civilina_admin_offer(array $user): void
{
    $o = civilina_offer_find((int) ($_GET['id'] ?? 0));
    if (!$o) {
        header('Location: ' . civilina_admin_url('offers'));
        exit;
    }
    $csrf = civilina_csrf_token();
    $page = '/' . preg_replace('~index\.html$~', '', CIVILINA_OFFER_PAGES[$o['axis']]) . '#offres';
    civilina_admin_start(($o['title'] !== '' ? $o['title'] : 'Offre') . ' — ' . (CIVILINA_AXES[$o['axis']] ?? ''), 'sales', $user);
    ?>
    <p class="crumbs"><a href="<?= h(civilina_admin_url('offers')) ?>">← Offres</a> · <?= $o['status'] === 'published' ? '<a href="' . h($page) . '" target="_blank" rel="noopener">Voir sur le site ↗</a>' : 'Brouillon' ?></p>
    <form method="post" action="<?= h(civilina_admin_url('offer', ['id' => $o['id']])) ?>" class="nl-editor">
      <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= $o['id'] ?>">
      <div class="bo-grid">
        <section class="panel panel-wide">
          <div class="panel-head"><h2><?= h(CIVILINA_OFFER_TIERS[$o['tier']]) ?> · <?= h(CIVILINA_AXES[$o['axis']]) ?></h2><?= $o['status'] === 'published' ? '<span class="status status-done">En ligne</span>' : '<span class="status status-new">Brouillon</span>' ?></div>
          <div class="stack">
            <label>Nom de l'offre<input type="text" name="title" maxlength="120" value="<?= h($o['title']) ?>"></label>
            <label>Présentation <small>(une ou deux phrases)</small><textarea name="tagline" rows="2" maxlength="300"><?= h($o['tagline']) ?></textarea></label>
            <label>Pour qui <small>(facultatif)</small><input type="text" name="for_who" maxlength="200" value="<?= h($o['for_who']) ?>"></label>
            <label>Livrables <small>(un par ligne)</small><textarea name="deliverables" rows="6"><?= h($o['deliverables']) ?></textarea></label>
            <div class="nl-row">
              <label>Durée indicative<input type="text" name="duration" maxlength="80" value="<?= h($o['duration']) ?>" placeholder="2 à 3 semaines"></label>
              <label>Texte du bouton<input type="text" name="cta_label" maxlength="40" value="<?= h($o['cta_label']) ?>"></label>
            </div>
          </div>
        </section>
        <section class="panel">
          <div class="panel-head"><h2>Prix « À partir de »</h2></div>
          <div class="stack">
            <label>Montant en DA <small>(vide = « Sur devis »)</small><input type="text" name="price_da" inputmode="numeric" value="<?= h((string) $o['price_da']) ?>" placeholder="ex. 150000"></label>
            <label>Montant en € <small>(facultatif)</small><input type="text" name="price_eur" inputmode="numeric" value="<?= h((string) $o['price_eur']) ?>" placeholder="ex. 1000"></label>
            <label>Mention après le prix<input type="text" name="price_note" maxlength="60" value="<?= h($o['price_note']) ?>" placeholder="HT"></label>
          </div>
          <p class="nl-audience"><small>Affiché :</small><br><strong style="font-size:1.05rem"><?= h(civilina_offer_price($o)) ?></strong></p>
          <p class="hint">Conseil : affichez un prix sur l'offre d'appel seulement ; laissez l'offre principale et l'accompagnement « Sur devis ».</p>
        </section>
      </div>
      <section class="panel nl-send">
        <div class="actions">
          <button class="bo-btn" type="submit" name="action" value="save_publish">Enregistrer et mettre en ligne</button>
          <button class="bo-btn bo-btn-ghost" type="submit" name="action" value="save">Enregistrer</button>
          <?php if ($o['status'] === 'published'): ?><button class="bo-btn bo-btn-ghost bo-btn-danger" type="submit" name="action" value="unpublish">Retirer du site</button><?php endif; ?>
        </div>
        <p class="hint">La mise en ligne réécrit la section « Offres » de la page <?= h($page) ?> (sauvegarde automatique avant chaque modification).</p>
      </section>
    </form>
    <?php
    civilina_admin_end();
}

function civilina_admin_resources(array $user): void
{
    $stats = civilina_resources_stats();
    $recent = civilina_db()->query('SELECT r.*, l.id AS lead FROM resource_downloads r LEFT JOIN leads l ON l.id = r.lead_id ORDER BY r.id DESC LIMIT 50')->fetchAll();
    civilina_admin_start('Modèles téléchargés', 'sales', $user);
    echo civilina_admin_sales_tabs('resources');
    ?>
    <section class="kpis">
      <?php foreach (CIVILINA_RESOURCES as $slug => $r): $s = $stats[$slug]; ?>
        <article class="kpi"><span><?= h($r['title']) ?></span><strong><?= $s['requests'] ?></strong><small><?= $s['downloaded'] ?> téléchargé<?= $s['downloaded'] > 1 ? 's' : '' ?> · <?= $s['optins'] ?> inscrit<?= $s['optins'] > 1 ? 's' : '' ?> newsletter<?= civilina_resource_path($slug) ? '' : ' · FICHIER MANQUANT' ?></small></article>
      <?php endforeach; ?>
    </section>
    <section class="panel">
      <div class="panel-head"><h2>Dernières demandes</h2><a href="/ressources/" target="_blank" rel="noopener">Page publique ↗</a></div>
      <?php if (!$recent): ?>
        <p class="empty">Aucune demande pour le moment. Les modèles sont proposés sur /ressources/ et dans les articles associés.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Date</th><th>Modèle</th><th>Contact</th><th>Téléchargé</th><th>Newsletter</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $d): ?>
          <tr>
            <td><?= h(civilina_admin_datetime($d['created_at'])) ?></td>
            <td><?= h(CIVILINA_RESOURCES[$d['resource']]['title'] ?? $d['resource']) ?></td>
            <td><?php if ($d['lead']): ?><a href="<?= h(civilina_admin_url('lead', ['id' => $d['lead']])) ?>"><?= h($d['email']) ?></a><?php else: ?><?= h($d['email']) ?><?php endif; ?><?php if ($d['company'] !== ''): ?><small><?= h($d['company']) ?></small><?php endif; ?></td>
            <td><?= (int) $d['downloads'] > 0 ? 'Oui (' . (int) $d['downloads'] . ')' : 'Pas encore' ?></td>
            <td><?= (int) $d['newsletter'] === 1 ? 'Demandée' : '—' ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <?php
    civilina_admin_end();
}
