<?php

declare(strict_types=1);

/*
 * Back-office: newsletter (milestone M3). Campaigns, subscribers, Brevo settings.
 * Loaded by civilina_admin.php; data and sending in civilina_newsletter.php.
 */

require_once __DIR__ . '/civilina_newsletter.php';

const CIVILINA_NL_VIEWS = ['newsletter', 'campaign', 'campaign_preview', 'subscribers', 'nl_settings'];

function civilina_admin_nl_tabs(string $current, bool $isSuper): string
{
    $tabs = ['newsletter' => 'Campagnes', 'subscribers' => 'Abonnés'];
    if ($isSuper) {
        $tabs['nl_settings'] = 'Réglages d\'envoi';
    }
    $out = '<nav class="seg nl-tabs" aria-label="Newsletter">';
    foreach ($tabs as $view => $label) {
        $out .= '<a class="' . ($view === $current ? 'on' : '') . '" href="' . h(civilina_admin_url($view)) . '">' . h($label) . '</a>';
    }
    return $out . '</nav>';
}

function civilina_campaign_badge(string $status): string
{
    $class = ['draft' => 'new', 'scheduled' => 'in_progress', 'sending' => 'in_progress', 'sent' => 'done'][$status] ?? 'new';
    return '<span class="status status-' . $class . '">' . h(CIVILINA_CAMPAIGN_STATUSES[$status] ?? $status) . '</span>';
}

function civilina_nl_status_badge(string $status): string
{
    $class = ['pending' => 'in_progress', 'confirmed' => 'done', 'unsubscribed' => 'archived', 'blocked' => 'new'][$status] ?? 'new';
    return '<span class="status status-' . $class . '">' . h(CIVILINA_NL_STATUSES[$status] ?? $status) . '</span>';
}

/** Readiness banner shared by the newsletter screens. */
function civilina_admin_nl_setup_note(bool $isSuper): string
{
    if (civilina_nl_ready()) {
        return '';
    }
    $link = $isSuper ? ' <a href="' . h(civilina_admin_url('nl_settings')) . '">Configurer l\'envoi →</a>' : '';
    return '<p class="bo-alert">L\'envoi d\'e-mails n\'est pas encore configuré : les inscriptions sont enregistrées et les e-mails de confirmation partiront dès que la clé Brevo et l\'expéditeur seront renseignés.' . $link . '</p>';
}

/* ---------- POST actions ---------- */

function civilina_admin_campaign_from_post(array $c): array
{
    $text = static fn (string $key, int $max): string => mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) ($_POST[$key] ?? ''))), 0, $max);
    $block = static fn (string $key): string => mb_substr(str_replace("\r\n", "\n", trim((string) ($_POST[$key] ?? ''))), 0, 6000);
    $c['subject'] = $text('subject', 150);
    $c['preheader'] = $text('preheader', 150);
    $c['title'] = $text('title', 150);
    $c['intro'] = $block('intro');
    $c['outro'] = $block('outro');
    $c['axes'] = civilina_nl_clean_axes($_POST['axes'] ?? []);
    $published = array_column(civilina_articles_published(), 'slug');
    $c['articles'] = array_values(array_intersect($published, array_map('strval', (array) ($_POST['articles'] ?? []))));
    $test = civilina_nl_normalize_email((string) ($_POST['test_email'] ?? ''));
    $c['test_email'] = civilina_nl_valid_email($test) ? $test : '';
    return $c;
}

function civilina_admin_newsletter_post(string $view, array $user, bool $isSuper): void
{
    $action = (string) ($_POST['action'] ?? '');
    $userId = (string) $user['id'];
    $back = static function (string $view, array $params = []): void {
        header('Location: ' . civilina_admin_url($view, $params), true, 303);
        exit;
    };

    if ($action === 'campaign_new') {
        $axes = array_keys(CIVILINA_NL_AXES);
        $months = [1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin', 'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre'];
        $month = $months[(int) date('n')] . ' ' . date('Y');
        $c = civilina_campaign_defaults();
        $c['subject'] = 'Journal Civilina — les nouveautés de ' . $month;
        $c['preheader'] = 'Les derniers guides et modèles du Journal Civilina.';
        $c['title'] = 'Les nouveautés du Journal';
        $c['intro'] = "Voici les derniers articles publiés sur le Journal Civilina : des guides et des modèles directement utilisables sur vos projets.";
        $c['outro'] = 'Une question sur votre projet ? Répondez simplement à cet e-mail.';
        $c['articles'] = civilina_campaign_suggest_articles($axes);
        $c['test_email'] = (string) ($user['email'] ?? '');
        $c['status'] = 'draft';
        $id = civilina_campaign_save($c);
        civilina_audit('newsletter.campaign_created', '#' . $id, $userId);
        $back('campaign', ['id' => $id]);
    }

    if ($view === 'campaign') {
        $id = (int) ($_POST['id'] ?? 0);
        $c = civilina_campaign_find($id);
        if (!$c) {
            $back('newsletter');
        }
        $editable = in_array($c['status'], ['draft', 'scheduled'], true);

        if ($action === 'duplicate') {
            $copy = array_intersect_key($c, civilina_campaign_defaults());
            $copy['subject'] = mb_substr('Copie — ' . $c['subject'], 0, 150);
            $copy['status'] = 'draft';
            $newId = civilina_campaign_save($copy);
            civilina_flash('Copie créée : modifiez-la avant l\'envoi.');
            $back('campaign', ['id' => $newId]);
        }
        if ($action === 'refresh_stats') {
            $error = civilina_campaign_refresh_stats($id);
            civilina_flash($error === '' ? 'Statistiques mises à jour.' : $error);
            $back('campaign', ['id' => $id]);
        }
        if (!$editable) {
            civilina_flash('Cette campagne est envoyée : elle ne peut plus être modifiée. Dupliquez-la pour en préparer une nouvelle.');
            $back('campaign', ['id' => $id]);
        }
        if ($action === 'delete') {
            if ($c['status'] === 'draft' && civilina_campaign_delete($id)) {
                civilina_audit('newsletter.campaign_deleted', '#' . $id, $userId);
                civilina_flash('Brouillon supprimé.');
                $back('newsletter');
            }
            civilina_flash('Annulez d\'abord la programmation.');
            $back('campaign', ['id' => $id]);
        }

        $c = civilina_admin_campaign_from_post($c);
        if ($action === 'unschedule') {
            $c['status'] = 'draft';
            $c['scheduled_at'] = '';
        }
        civilina_campaign_save($c);

        if ($action === 'test') {
            $problems = civilina_campaign_problems($c, false);
            if ($c['test_email'] === '') {
                $problems[] = 'Renseignez une adresse de test valide.';
            }
            if ($problems === [] && civilina_mail_quota_left() <= 0) {
                $problems[] = 'Quota d\'envoi du jour atteint.';
            }
            if ($problems !== []) {
                civilina_flash('Test non envoyé : ' . implode(' ', $problems));
            } else {
                $sent = civilina_campaign_send_test($c, $c['test_email']);
                civilina_flash($sent['ok'] ? 'E-mail de test envoyé à ' . $c['test_email'] . '.' : 'Échec du test : ' . $sent['error']);
            }
        } elseif ($action === 'schedule') {
            $when = strtotime(str_replace('T', ' ', (string) ($_POST['scheduled_at'] ?? '')));
            $problems = civilina_campaign_problems($c, true);
            if ($when === false || $when < time() + 300) {
                $problems[] = 'Choisissez une date et une heure au moins 5 minutes plus tard.';
            }
            if ($problems !== []) {
                civilina_flash('Programmation refusée : ' . implode(' ', $problems));
            } else {
                $c['status'] = 'scheduled';
                $c['scheduled_at'] = date('Y-m-d H:i', $when);
                civilina_campaign_save($c);
                civilina_audit('newsletter.campaign_scheduled', '#' . $id . ' : ' . $c['scheduled_at'], $userId);
                civilina_flash('Campagne programmée le ' . date('d/m/Y à H:i', $when) . '. L\'envoi démarre à la première visite du site ou du back-office après cette heure.');
            }
        } elseif ($action === 'send_now') {
            $count = count(civilina_campaign_audience($c['axes']));
            if (empty($_POST['confirm_send']) || (int) ($_POST['confirm_count'] ?? -1) !== $count) {
                civilina_flash('Envoi non lancé : cochez la case de confirmation (le nombre de destinataires a peut-être changé : ' . $count . ').');
            } else {
                $started = civilina_campaign_start($id, $userId);
                civilina_flash($started['ok'] ? 'Envoi lancé : ' . $started['messages'][0] . '. Gardez cette page ouverte jusqu\'à la fin, elle se recharge toute seule.' : 'Envoi refusé : ' . implode(' ', $started['messages']));
            }
        } elseif ($action === 'unschedule') {
            civilina_flash('Programmation annulée : la campagne est revenue en brouillon.');
        } else {
            civilina_flash('Campagne enregistrée.');
        }
        $back('campaign', ['id' => $id]);
    }

    if ($view === 'subscribers') {
        $id = (int) ($_POST['id'] ?? 0);
        if ($action === 'add') {
            if (empty($_POST['asked'])) {
                civilina_flash('Ajout refusé : la personne doit avoir demandé son inscription (case à cocher).');
            } else {
                $result = civilina_nl_subscribe((string) ($_POST['email'] ?? ''), (string) ($_POST['first_name'] ?? ''), $_POST['axes'] ?? [], 'back-office');
                civilina_audit('newsletter.invited', civilina_hash(civilina_nl_normalize_email((string) ($_POST['email'] ?? ''))), $userId);
                civilina_flash(['sent' => 'E-mail de confirmation envoyé (ou adresse déjà inscrite) : l\'inscription sera active après le clic de la personne.', 'queued' => 'Inscription enregistrée : l\'e-mail de confirmation partira dès que l\'envoi sera configuré.', 'invalid' => 'Adresse e-mail non valide.'][$result]);
            }
        } elseif ($action === 'unsubscribe' && $id > 0) {
            civilina_nl_unsubscribe($id, 'back-office');
            civilina_flash('Abonné désinscrit.');
        } elseif ($action === 'resend' && $id > 0) {
            $s = civilina_nl_find($id);
            $ok = $s && $s['status'] === 'pending' && civilina_nl_ready() && civilina_nl_send_confirmation($id);
            civilina_flash($ok ? 'E-mail de confirmation renvoyé.' : 'Renvoi impossible (abonné non en attente, ou envoi non configuré).');
        } elseif ($action === 'delete' && $id > 0 && !empty($_POST['confirm_delete'])) {
            $db = civilina_db();
            $db->prepare('DELETE FROM campaign_sends WHERE subscriber_id = ?')->execute([$id]);
            $db->prepare('DELETE FROM subscribers WHERE id = ?')->execute([$id]);
            civilina_audit('newsletter.deleted', '#' . $id . ' (effacement des données)', $userId);
            civilina_flash('Abonné supprimé définitivement.');
        }
        $back('subscribers');
    }

    if ($view === 'nl_settings' && $isSuper) {
        if ($action === 'save_settings') {
            $messages = [];
            $key = trim((string) ($_POST['brevo_key'] ?? ''));
            if (!empty($_POST['remove_key'])) {
                civilina_setting_set('nl_brevo_key', '');
                civilina_audit('newsletter.key_removed', '', $userId);
                $messages[] = 'Clé d\'API supprimée.';
            } elseif ($key !== '') {
                if (!preg_match('~^xkeysib-[A-Za-z0-9-]{20,200}$~', $key)) {
                    $messages[] = 'Clé refusée : une clé d\'API Brevo commence par « xkeysib- » (la clé SMTP « xsmtpsib- » ne convient pas).';
                } else {
                    [, , $error] = civilina_brevo_request('GET', '/account', null, $key);
                    if ($error !== '') {
                        $messages[] = 'Clé non enregistrée : ' . $error;
                    } else {
                        civilina_setting_set('nl_brevo_key', $key);
                        civilina_audit('newsletter.key_saved', '', $userId);
                        $messages[] = 'Clé d\'API vérifiée et enregistrée.';
                    }
                }
            }
            $email = static fn (string $field): string => civilina_nl_normalize_email((string) ($_POST[$field] ?? ''));
            $sender = $email('sender_email');
            $reply = $email('reply_to');
            if ($sender !== '' && !civilina_nl_valid_email($sender)) {
                $messages[] = 'Adresse d\'expéditeur non valide.';
            } else {
                civilina_setting_set('nl_sender_email', $sender);
            }
            if ($reply !== '' && !civilina_nl_valid_email($reply)) {
                $messages[] = 'Adresse de réponse non valide.';
            } else {
                civilina_setting_set('nl_reply_to', $reply);
            }
            civilina_setting_set('nl_sender_name', mb_substr(trim((string) ($_POST['sender_name'] ?? 'Civilina')), 0, 70) ?: 'Civilina');
            civilina_setting_set('nl_legal', mb_substr(trim((string) preg_replace('~\s+~u', ' ', (string) ($_POST['legal'] ?? ''))), 0, 200));
            civilina_setting_set('nl_daily_limit', (string) max(1, min(10000, (int) ($_POST['daily_limit'] ?? CIVILINA_NL_DEFAULT_DAILY_LIMIT))));
            civilina_audit('newsletter.settings', '', $userId);
            $messages[] = 'Réglages enregistrés.';
            civilina_flash(implode(' ', array_unique($messages)));
        } elseif ($action === 'check') {
            $_SESSION['nl_checks'] = civilina_nl_check_brevo();
            civilina_flash('Vérification terminée.');
        }
        $back('nl_settings');
    }

    $back('newsletter');
}

/* ---------- Views ---------- */

function civilina_admin_newsletter_view(string $view, array $user, bool $isSuper): void
{
    match ($view) {
        'campaign' => civilina_admin_campaign_form($user, $isSuper),
        'campaign_preview' => civilina_admin_campaign_preview(),
        'subscribers' => civilina_admin_subscribers($user, $isSuper),
        'nl_settings' => civilina_admin_nl_settings($user),
        default => civilina_admin_campaigns($user, $isSuper),
    };
}

function civilina_admin_campaigns(array $user, bool $isSuper): void
{
    $counts = civilina_nl_counts();
    $campaigns = civilina_campaigns_all();
    $csrf = civilina_csrf_token();
    civilina_admin_start('Newsletter', 'newsletter', $user);
    echo civilina_admin_nl_tabs('newsletter', $isSuper);
    echo civilina_admin_nl_setup_note($isSuper);
    ?>
    <section class="kpis">
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('subscribers', ['status' => 'confirmed'])) ?>"><span>Abonnés confirmés</span><strong><?= $counts['status']['confirmed'] ?></strong><small><?= implode(' · ', array_map(static fn (string $k, string $l): string => h($l) . ' ' . $counts['axes'][$k], array_keys(CIVILINA_NL_AXES), CIVILINA_NL_AXES)) ?></small></a>
      <article class="kpi"><span>Nouveaux abonnés · 30 jours</span><strong>+<?= $counts['confirmed_30d'] ?></strong><small>Inscriptions confirmées</small></article>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('subscribers', ['status' => 'pending'])) ?>"><span>En attente de confirmation</span><strong><?= $counts['status']['pending'] ?></strong><small>Lien valable 7 jours</small></a>
      <a class="kpi kpi-link" href="<?= h(civilina_admin_url('subscribers', ['status' => 'unsubscribed'])) ?>"><span>Désinscrits</span><strong><?= $counts['status']['unsubscribed'] ?></strong><small><?= $counts['status']['blocked'] ?> bloqué(s) par Brevo</small></a>
    </section>

    <section class="panel">
      <div class="panel-head"><h2>Campagnes</h2>
        <form method="post" action="<?= h(civilina_admin_url('newsletter')) ?>" class="inline">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="campaign_new">
          <button class="bo-btn" type="submit">+ Nouvelle campagne</button>
        </form>
      </div>
      <?php if (!$campaigns): ?>
        <p class="empty">Aucune campagne. « Nouvelle campagne » prépare un e-mail avec les derniers articles du Journal.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Objet</th><th>Statut</th><th>Destinataires</th><th>Ouvertures</th><th>Clics</th><th>Date</th></tr></thead>
        <tbody>
        <?php foreach ($campaigns as $c):
            $st = $c['stats'];
            $base = max(1, (int) ($st['delivered'] ?? $st['sent'] ?? 0));
            $date = $c['sent_at'] ?: ($c['started_at'] ?: ($c['scheduled_at'] ?: $c['updated_at'])); ?>
          <tr>
            <td><a href="<?= h(civilina_admin_url('campaign', ['id' => $c['id']])) ?>"><?= h($c['subject'] !== '' ? $c['subject'] : '(sans objet)') ?></a></td>
            <td><?= civilina_campaign_badge($c['status']) ?></td>
            <td><?= $c['recipients'] > 0 ? $c['recipients'] : '—' ?></td>
            <td><?= isset($st['uniqueOpens']) ? round($st['uniqueOpens'] * 100 / $base) . ' %' : '—' ?></td>
            <td><?= isset($st['uniqueClicks']) ? round($st['uniqueClicks'] * 100 / $base) . ' %' : '—' ?></td>
            <td><?= h(civilina_admin_datetime($date)) ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <p class="hint">Ouvertures et clics : chiffres de Brevo, en part des e-mails délivrés. Les ouvertures sont indicatives (certaines messageries ouvrent les e-mails automatiquement) ; les clics sont plus fiables. Les visites venues de la newsletter apparaissent aussi dans Statistiques (source « Newsletter »).</p>
    <?php
    civilina_admin_end();
}

function civilina_admin_campaign_form(array $user, bool $isSuper): void
{
    $c = civilina_campaign_find((int) ($_GET['id'] ?? 0));
    if (!$c) {
        header('Location: ' . civilina_admin_url('newsletter'));
        exit;
    }
    $csrf = civilina_csrf_token();
    $editable = in_array($c['status'], ['draft', 'scheduled'], true);
    $progress = civilina_campaign_progress($c['id']);
    $audience = count(civilina_campaign_audience($c['axes']));
    $quota = civilina_mail_quota_left();
    $refresh = $c['status'] === 'sending' && $progress['queued'] > 0 && $quota > 0 && civilina_nl_ready();
    $head = $refresh ? '<meta http-equiv="refresh" content="3">' : '';
    $action = h(civilina_admin_url('campaign', ['id' => $c['id']]));
    $published = array_slice(civilina_articles_published(), 0, 24);

    civilina_admin_start($c['subject'] !== '' ? $c['subject'] : 'Campagne', 'newsletter', $user, $head);
    ?>
    <p class="crumbs"><a href="<?= h(civilina_admin_url('newsletter')) ?>">← Campagnes</a></p>
    <?= civilina_admin_nl_setup_note($isSuper) ?>
    <?php if ($c['status'] !== 'draft'): ?>
      <section class="panel nl-progress">
        <div class="panel-head"><h2><?= civilina_campaign_badge($c['status']) ?>
          <?php if ($c['status'] === 'scheduled'): ?> le <?= h(date('d/m/Y à H:i', strtotime($c['scheduled_at']))) ?><?php endif; ?></h2>
          <?php if ($c['started_at'] !== ''): ?><small>Démarrée le <?= h(civilina_admin_datetime($c['started_at'])) ?><?= $c['sent_at'] !== '' ? ' · terminée le ' . h(civilina_admin_datetime($c['sent_at'])) : '' ?></small><?php endif; ?>
        </div>
        <?php if ($c['started_at'] !== ''): ?>
          <ul class="bars">
            <li><span>Envoyés</span><meter min="0" max="<?= max(1, $c['recipients']) ?>" value="<?= $progress['sent'] ?>"></meter><b><?= $progress['sent'] ?> / <?= $c['recipients'] ?></b></li>
          </ul>
          <p class="hint"><?= $progress['queued'] ?> en file · <?= $progress['failed'] ?> en échec · <?= $progress['skipped'] ?> ignorés (désinscrits entre-temps)<?= $progress['sending'] > 0 ? ' · ' . $progress['sending'] . ' interrompus, non renvoyés' : '' ?></p>
          <?php if ($c['status'] === 'sending'): ?>
            <?php if ($refresh): ?><p class="bo-note">Envoi en cours : la page se recharge toute seule toutes les 3 secondes.</p>
            <?php elseif ($quota <= 0): ?><p class="bo-alert">Quota d'envoi du jour atteint : l'envoi reprendra automatiquement demain.</p>
            <?php else: ?><p class="bo-alert">Envoi en pause : <?= h(civilina_setting('nl_last_error', 'vérifiez les réglages d\'envoi.')) ?></p><?php endif; ?>
          <?php endif; ?>
        <?php endif; ?>
        <?php if ($c['status'] === 'sent' || ($c['status'] === 'sending' && $progress['sent'] > 0)):
            $st = $c['stats'];
            $base = max(1, (int) ($st['delivered'] ?? 0)); ?>
          <?php if (isset($st['delivered'])): ?>
          <section class="kpis">
            <article class="kpi"><span>Délivrés</span><strong><?= (int) $st['delivered'] ?></strong><small><?= (int) ($st['hardBounces'] ?? 0) + (int) ($st['softBounces'] ?? 0) ?> rebonds · <?= (int) ($st['blocked'] ?? 0) ?> bloqués</small></article>
            <article class="kpi"><span>Ouvertures uniques</span><strong><?= round((int) $st['uniqueOpens'] * 100 / $base) ?> %</strong><small><?= (int) $st['uniqueOpens'] ?> personnes (indicatif)</small></article>
            <article class="kpi"><span>Clics uniques</span><strong><?= round((int) $st['uniqueClicks'] * 100 / $base) ?> %</strong><small><?= (int) $st['uniqueClicks'] ?> personnes</small></article>
            <article class="kpi"><span>Désinscriptions</span><strong><?= (int) ($st['unsubscribed'] ?? 0) ?></strong><small><?= (int) ($st['spamReports'] ?? 0) ?> signalement(s) spam</small></article>
          </section>
          <?php endif; ?>
          <form method="post" action="<?= $action ?>" class="inline">
            <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= $c['id'] ?>">
            <button class="bo-btn bo-btn-ghost" type="submit" name="action" value="refresh_stats">Actualiser les statistiques</button>
            <?php if ($c['stats_at'] !== ''): ?><small class="hint">Mises à jour le <?= h(civilina_admin_datetime($c['stats_at'])) ?></small><?php endif; ?>
          </form>
        <?php endif; ?>
      </section>
    <?php endif; ?>

    <form method="post" action="<?= $action ?>" class="nl-editor">
      <input type="hidden" name="_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= $c['id'] ?>">
      <fieldset class="nl-fields"<?= $editable ? '' : ' disabled' ?>>
      <div class="bo-grid">
        <section class="panel panel-wide">
          <div class="panel-head"><h2>Contenu de l'e-mail</h2></div>
          <div class="stack">
            <label>Objet <small>(ce que l'abonné voit dans sa boîte de réception, 50 à 70 caractères conseillés)</small><input type="text" name="subject" maxlength="150" value="<?= h($c['subject']) ?>" required></label>
            <label>Texte d'aperçu <small>(affiché après l'objet par la plupart des messageries)</small><input type="text" name="preheader" maxlength="150" value="<?= h($c['preheader']) ?>"></label>
            <label>Titre dans l'e-mail <small>(facultatif)</small><input type="text" name="title" maxlength="150" value="<?= h($c['title']) ?>"></label>
            <label>Introduction <small>(paragraphes séparés par une ligne vide ; **gras**, *italique*, [lien](https://…))</small><textarea name="intro" rows="5"><?= h($c['intro']) ?></textarea></label>
            <fieldset class="nl-articles"><legend>Articles à mettre en avant <small>(dans l'ordre du Journal, du plus récent au plus ancien)</small></legend>
              <?php foreach ($published as $a): ?>
                <label><input type="checkbox" name="articles[]" value="<?= h($a['slug']) ?>"<?= in_array($a['slug'], $c['articles'], true) ? ' checked' : '' ?>> <?= civilina_axis_badge($a['axis']) ?> <?= h($a['title']) ?> <small><?= h(civilina_fr_date($a['date_published'])) ?></small></label>
              <?php endforeach; ?>
            </fieldset>
            <label>Conclusion <small>(facultatif)</small><textarea name="outro" rows="3"><?= h($c['outro']) ?></textarea></label>
          </div>
        </section>
        <section class="panel">
          <div class="panel-head"><h2>Destinataires</h2></div>
          <fieldset class="nl-axes-admin"><legend>Abonnés confirmés ayant choisi au moins un de ces thèmes</legend>
            <?php foreach (CIVILINA_NL_AXES as $key => $label): ?>
              <label><input type="checkbox" name="axes[]" value="<?= $key ?>"<?= in_array($key, $c['axes'], true) ? ' checked' : '' ?>> <?= h($label) ?></label>
            <?php endforeach; ?>
          </fieldset>
          <p class="nl-audience"><strong><?= $c['status'] === 'draft' || $c['status'] === 'scheduled' ? $audience : $c['recipients'] ?></strong> destinataire<?= ($editable ? $audience : $c['recipients']) > 1 ? 's' : '' ?><?= $editable ? ' <small>(recalculé à l\'enregistrement)</small>' : '' ?></p>
          <p class="hint">Quota du jour : <?= civilina_mail_sent_today() ?> e-mails envoyés sur <?= civilina_nl_config()['daily_limit'] ?>. Au-delà, l'envoi reprend le lendemain.</p>
          <div class="panel-head nl-sub"><h2>Tester</h2></div>
          <label class="stack">Adresse de test<input type="email" name="test_email" value="<?= h($c['test_email'] !== '' ? $c['test_email'] : (string) ($user['email'] ?? '')) ?>"></label>
          <div class="actions">
            <button class="bo-btn bo-btn-ghost" type="submit" name="action" value="test">Envoyer un test</button>
            <button class="bo-btn bo-btn-ghost" type="submit" formaction="<?= h(civilina_admin_url('campaign_preview')) ?>" formtarget="_blank">Aperçu ↗</button>
          </div>
        </section>
      </div>
      </fieldset>

      <?php if ($editable): ?>
      <section class="panel nl-send">
        <div class="actions">
          <button class="bo-btn" type="submit" name="action" value="save">Enregistrer</button>
          <?php if ($c['status'] === 'draft'): ?><button class="bo-btn bo-btn-ghost bo-btn-danger" type="submit" name="action" value="delete">Supprimer le brouillon</button><?php endif; ?>
        </div>
        <div class="nl-send-row">
          <label>Programmer l'envoi le <input type="datetime-local" name="scheduled_at" value="<?= h($c['scheduled_at'] !== '' ? str_replace(' ', 'T', $c['scheduled_at']) : date('Y-m-d\T08:00', strtotime('+1 day'))) ?>"></label>
          <button class="bo-btn bo-btn-ghost" type="submit" name="action" value="schedule"><?= $c['status'] === 'scheduled' ? 'Reprogrammer' : 'Programmer' ?></button>
          <?php if ($c['status'] === 'scheduled'): ?><button class="bo-btn bo-btn-ghost" type="submit" name="action" value="unschedule">Annuler la programmation</button><?php endif; ?>
        </div>
        <div class="nl-send-row">
          <input type="hidden" name="confirm_count" value="<?= $audience ?>">
          <label class="check"><input type="checkbox" name="confirm_send" value="1"> Je confirme l'envoi immédiat à <b><?= $audience ?></b> abonné<?= $audience > 1 ? 's' : '' ?></label>
          <button class="bo-btn" type="submit" name="action" value="send_now">Envoyer maintenant</button>
        </div>
        <p class="hint">Avant l'envoi : enregistrez, envoyez-vous un test et vérifiez les liens. Une campagne envoyée ne peut plus être modifiée.</p>
      </section>
      <?php else: ?>
      <div class="actions"><button class="bo-btn bo-btn-ghost" type="submit" name="action" value="duplicate">Dupliquer cette campagne</button></div>
      <?php endif; ?>
    </form>
    <?php
    civilina_admin_end();
}

/** E-mail preview in a new tab (from the form, without saving). */
function civilina_admin_campaign_preview(): void
{
    $c = civilina_campaign_find((int) ($_POST['id'] ?? $_GET['id'] ?? 0));
    if (!$c) {
        header('Location: ' . civilina_admin_url('newsletter'));
        exit;
    }
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && in_array($c['status'], ['draft', 'scheduled'], true)) {
        $c = civilina_admin_campaign_from_post($c);
    }
    $mail = civilina_campaign_email($c, null);
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, max-age=0');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'none'; img-src https: data: 'self'; style-src 'unsafe-inline'; form-action 'none'; frame-ancestors 'none'; base-uri 'none'");
    $banner = '<div style="background:#9a5b00;color:#fff;text-align:center;font:600 14px Inter,Arial,sans-serif;padding:8px">Aperçu — Objet : ' . h($c['subject']) . '</div>';
    echo preg_replace('~<body([^>]*)>~', '<body$1>' . $banner, $mail['html'], 1);
}

function civilina_admin_subscribers(array $user, bool $isSuper): void
{
    $db = civilina_db();
    $status = (string) ($_GET['status'] ?? '');
    $axis = (string) ($_GET['axis'] ?? '');
    $search = mb_substr(trim((string) ($_GET['q'] ?? '')), 0, 100);
    $where = [];
    $params = [];
    if (array_key_exists($status, CIVILINA_NL_STATUSES)) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    if (array_key_exists($axis, CIVILINA_NL_AXES)) {
        $where[] = "(',' || axes || ',') LIKE ?";
        $params[] = '%,' . $axis . ',%';
    }
    if ($search !== '') {
        $where[] = '(email LIKE ? OR first_name LIKE ?)';
        $params[] = '%' . $search . '%';
        $params[] = '%' . $search . '%';
    }
    $sql = 'SELECT * FROM subscribers' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY created_at DESC';

    if (isset($_GET['export'])) {
        $statement = $db->prepare($sql);
        $statement->execute($params);
        civilina_audit('newsletter.exported', ($status !== '' ? $status : 'tous') . ($axis !== '' ? ', ' . $axis : ''), (string) $user['id']);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="abonnes-newsletter-' . date('Y-m-d') . '.csv"');
        header('Cache-Control: no-store');
        $out = fopen('php://output', 'w');
        fwrite($out, "\xEF\xBB\xBF");
        fputcsv($out, ['email', 'prenom', 'themes', 'statut', 'inscription', 'confirmation', 'source'], ';');
        foreach ($statement->fetchAll() as $s) {
            fputcsv($out, [$s['email'], $s['first_name'], $s['axes'], $s['status'], $s['created_at'], $s['confirmed_at'], $s['source']], ';');
        }
        fclose($out);
        exit;
    }

    $statement = $db->prepare($sql . ' LIMIT 500');
    $statement->execute($params);
    $rows = $statement->fetchAll();
    $counts = civilina_nl_counts();
    $csrf = civilina_csrf_token();
    $action = h(civilina_admin_url('subscribers'));

    civilina_admin_start('Abonnés à la newsletter', 'newsletter', $user);
    echo civilina_admin_nl_tabs('subscribers', $isSuper);
    echo civilina_admin_nl_setup_note($isSuper);
    ?>
    <form class="filters" method="get" action="/admin/">
      <input type="hidden" name="view" value="subscribers">
      <label>Statut
        <select name="status"><option value="">Tous</option>
          <?php foreach (CIVILINA_NL_STATUSES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $status ? ' selected' : '' ?>><?= h($label) ?> (<?= $counts['status'][$key] ?>)</option><?php endforeach; ?>
        </select>
      </label>
      <label>Thème
        <select name="axis"><option value="">Tous</option>
          <?php foreach (CIVILINA_NL_AXES as $key => $label): ?><option value="<?= h($key) ?>"<?= $key === $axis ? ' selected' : '' ?>><?= h($label) ?></option><?php endforeach; ?>
        </select>
      </label>
      <label>Recherche <input type="search" name="q" value="<?= h($search) ?>" placeholder="E-mail ou prénom"></label>
      <button class="bo-btn" type="submit">Filtrer</button>
      <a class="bo-btn bo-btn-ghost" href="<?= h(civilina_admin_url('subscribers', array_filter(['status' => $status, 'axis' => $axis, 'q' => $search, 'export' => 'csv']))) ?>">Exporter (CSV)</a>
      <span class="count"><?= count($rows) ?> abonné<?= count($rows) > 1 ? 's' : '' ?></span>
    </form>

    <div class="bo-grid">
      <section class="panel panel-wide">
        <?php if (!$rows): ?>
          <p class="empty">Aucun abonné ne correspond à ces filtres. Les inscriptions arrivent par les formulaires du site (accueil, Journal, articles, page /newsletter/).</p>
        <?php else: ?>
        <div class="table-wrap"><table>
          <thead><tr><th>E-mail</th><th>Thèmes</th><th>Statut</th><th>Inscription</th><th>Origine</th><th></th></tr></thead>
          <tbody>
          <?php foreach ($rows as $s): ?>
            <tr>
              <td><?= h($s['email']) ?><?php if ($s['first_name'] !== ''): ?><small><?= h($s['first_name']) ?></small><?php endif; ?></td>
              <td><?= h(civilina_nl_axes_label($s['axes'])) ?></td>
              <td><?= civilina_nl_status_badge($s['status']) ?><?php if ($s['status'] === 'pending' && $s['confirm_sent_at'] === ''): ?><small>e-mail non encore envoyé</small><?php endif; ?></td>
              <td><?= h(civilina_admin_datetime($s['created_at'])) ?><?php if ($s['confirmed_at'] !== ''): ?><small>confirmé le <?= h(civilina_admin_datetime($s['confirmed_at'])) ?></small><?php endif; ?></td>
              <td><small><?= h($s['source']) ?></small></td>
              <td>
                <details class="row-actions"><summary>Actions</summary>
                  <form method="post" action="<?= $action ?>" class="stack">
                    <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="id" value="<?= (int) $s['id'] ?>">
                    <?php if ($s['status'] === 'pending'): ?><button class="bo-btn bo-btn-small bo-btn-ghost" type="submit" name="action" value="resend">Renvoyer la confirmation</button><?php endif; ?>
                    <?php if (in_array($s['status'], ['pending', 'confirmed'], true)): ?><button class="bo-btn bo-btn-small bo-btn-ghost" type="submit" name="action" value="unsubscribe">Désinscrire</button><?php endif; ?>
                    <label class="check"><input type="checkbox" name="confirm_delete" value="1"> Effacer toutes ses données</label>
                    <button class="bo-btn bo-btn-small bo-btn-danger" type="submit" name="action" value="delete">Supprimer définitivement</button>
                  </form>
                </details>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table></div>
        <?php endif; ?>
      </section>

      <section class="panel">
        <div class="panel-head"><h2>Inscrire quelqu'un</h2></div>
        <form method="post" action="<?= $action ?>" class="stack">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="add">
          <label>E-mail<input type="email" name="email" required></label>
          <label>Prénom <small>(facultatif)</small><input type="text" name="first_name" maxlength="80"></label>
          <fieldset class="nl-axes-admin"><legend>Thèmes</legend>
            <?php foreach (CIVILINA_NL_AXES as $key => $label): ?><label><input type="checkbox" name="axes[]" value="<?= $key ?>" checked> <?= h($label) ?></label><?php endforeach; ?>
          </fieldset>
          <label class="check"><input type="checkbox" name="asked" value="1" required> Cette personne m'a demandé à être inscrite</label>
          <button class="bo-btn" type="submit">Envoyer l'invitation</button>
        </form>
        <p class="hint">La personne reçoit l'e-mail de confirmation : elle n'est inscrite qu'après son clic (double opt-in, exigé par le RGPD). N'importez pas de listes de contacts qui n'ont pas demandé la newsletter.</p>
      </section>
    </div>
    <?php
    civilina_admin_end();
}

function civilina_admin_nl_settings(array $user): void
{
    $config = civilina_nl_config();
    $csrf = civilina_csrf_token();
    $checks = $_SESSION['nl_checks'] ?? null;
    unset($_SESSION['nl_checks']);
    $waiting = (int) civilina_db()->query("SELECT COUNT(*) FROM subscribers WHERE status = 'pending' AND confirm_sent_at = ''")->fetchColumn();
    $lastError = civilina_setting('nl_last_error');
    $action = h(civilina_admin_url('nl_settings'));

    civilina_admin_start('Réglages d\'envoi', 'newsletter', $user);
    echo civilina_admin_nl_tabs('nl_settings', true);
    ?>
    <div class="bo-grid">
      <section class="panel panel-wide">
        <div class="panel-head"><h2>Brevo</h2><?= civilina_nl_ready() ? '<span class="status status-done">Envoi configuré</span>' : '<span class="status status-new">À configurer</span>' ?></div>
        <form method="post" action="<?= $action ?>" class="stack" autocomplete="off">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="save_settings">
          <label>Clé d'API Brevo <small><?= $config['has_key'] ? 'enregistrée (' . h($config['key_hint']) . ') : laissez vide pour la garder' : 'commence par « xkeysib- »' ?></small>
            <input type="password" name="brevo_key" autocomplete="new-password" spellcheck="false" placeholder="<?= $config['has_key'] ? '••••••••••••' : 'xkeysib-…' ?>">
          </label>
          <?php if ($config['has_key']): ?><label class="check"><input type="checkbox" name="remove_key" value="1"> Supprimer la clé enregistrée</label><?php endif; ?>
          <div class="nl-row">
            <label>Nom de l'expéditeur<input type="text" name="sender_name" maxlength="70" value="<?= h($config['sender_name']) ?>"></label>
            <label>Adresse de l'expéditeur <small>(validée dans Brevo)</small><input type="email" name="sender_email" value="<?= h($config['sender_email']) ?>" placeholder="newsletter@civilina.com"></label>
          </div>
          <div class="nl-row">
            <label>Adresse de réponse <small>(facultatif)</small><input type="email" name="reply_to" value="<?= h($config['reply_to']) ?>"></label>
            <label>Quota quotidien <small>(offre gratuite Brevo : 300 e-mails/jour)</small><input type="number" name="daily_limit" min="1" max="10000" value="<?= (int) $config['daily_limit'] ?>"></label>
          </div>
          <label>Mention en bas des e-mails <small>(identité de l'expéditeur, adresse si vous le souhaitez)</small><input type="text" name="legal" maxlength="200" value="<?= h($config['legal']) ?>"></label>
          <div class="actions"><button class="bo-btn" type="submit">Enregistrer</button></div>
        </form>
        <form method="post" action="<?= $action ?>" class="inline">
          <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="action" value="check">
          <button class="bo-btn bo-btn-ghost" type="submit">Vérifier le compte, l'expéditeur et le domaine</button>
        </form>
        <?php if (is_array($checks)): ?>
          <ul class="nl-checks"><?php foreach ($checks as $check): ?><li class="<?= $check['ok'] ? 'ok' : 'ko' ?>"><?= $check['ok'] ? '✓' : '✗' ?> <?= h($check['label']) ?></li><?php endforeach; ?></ul>
        <?php endif; ?>
        <p class="hint">La clé est conservée dans la base privée du serveur, hors du site public ; elle n'est jamais réaffichée. Aujourd'hui : <?= civilina_mail_sent_today() ?> e-mail(s) envoyé(s) · <?= $waiting ?> confirmation(s) en attente d'envoi<?= $lastError !== '' ? ' · dernière erreur : ' . h($lastError) : '' ?>.</p>
      </section>
      <section class="panel">
        <div class="panel-head"><h2>Mise en route</h2></div>
        <ol class="nl-steps">
          <li>Créez un compte sur <b>brevo.com</b> (offre gratuite).</li>
          <li><b>Expéditeurs, domaines et IP dédiées → Domaines</b> : ajoutez civilina.com. Brevo affiche 3 à 4 enregistrements DNS (code Brevo, DKIM, DMARC).</li>
          <li>Dans cPanel, <b>Zone Editor</b> : ajoutez ces enregistrements, puis cliquez sur « Authentifier » dans Brevo (quelques minutes à quelques heures).</li>
          <li><b>Expéditeurs</b> : ajoutez l'adresse d'envoi (par exemple newsletter@civilina.com) et validez-la.</li>
          <li><b>SMTP et API → Clés API</b> : générez une clé, collez-la ici, renseignez l'expéditeur, enregistrez.</li>
          <li>Cliquez sur « Vérifier » : les trois lignes doivent être cochées. Les confirmations en attente partent alors automatiquement.</li>
        </ol>
      </section>
    </div>
    <?php
    civilina_admin_end();
}
