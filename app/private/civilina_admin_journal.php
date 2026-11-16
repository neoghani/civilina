<?php

declare(strict_types=1);

/*
 * Back-office: Journal editor (milestone M2).
 * Views: journal (list), article (edit / new), preview. No JavaScript: the
 * back-office CSP forbids scripts; the preview opens in a new tab.
 */

require_once __DIR__ . '/civilina_publish.php';

/** Builds an article from the submitted form, on top of the stored version. */
function civilina_admin_article_from_post(?array $existing): array
{
    $p = static fn (string $key, int $max = 400): string => mb_substr(trim(str_replace("\r\n", "\n", (string) ($_POST[$key] ?? ''))), 0, $max);
    $a = $existing ?? (civilina_article_defaults() + ['id' => 0, 'slug' => '', 'status' => 'draft', 'publish_at' => '', 'date_published' => '', 'date_modified' => '']);

    $a['title'] = $p('title', 200);
    $a['axis'] = array_key_exists((string) ($_POST['axis'] ?? ''), CIVILINA_ARTICLE_AXES) ? (string) $_POST['axis'] : 'engineering';
    $a['rubric'] = $p('rubric', 60);
    $a['crumb'] = $p('crumb', 80);
    $a['seo_title'] = $p('seo_title', 200);
    $a['description'] = $p('description', 300);
    $a['chapo'] = $p('chapo', 1500);
    $a['body'] = $p('body', 60000);
    $a['sources_title'] = $p('sources_title', 80);
    $a['sources'] = $p('sources', 4000);
    $a['sources_note'] = $p('sources_note', 600);
    $a['minutes'] = max(0, min(60, (int) ($_POST['minutes'] ?? 0)));
    $a['cta'] = ['title' => $p('cta_title', 160), 'text' => $p('cta_text', 400), 'url' => $p('cta_url', 200), 'label' => $p('cta_label', 80)];
    if ($a['cta']['url'] !== '' && !preg_match('~^(/|https://)~', $a['cta']['url'])) {
        $a['cta']['url'] = '';
    }
    $color = (string) ($_POST['cover_color'] ?? '');
    $a['cover'] = [
        'file' => $a['cover']['file'] ?? '',
        'alt' => $p('cover_alt', 200), 'kicker' => $p('cover_kicker', 60), 'line1' => $p('cover_line1', 60),
        'line2' => $p('cover_line2', 60), 'subtitle' => $p('cover_subtitle', 90),
        'color' => array_key_exists($color, CIVILINA_COVER_COLORS) ? $color : ($a['cover']['color'] ?? '#003b8c'),
    ];
    $a['card_text'] = $p('card_text', 300);
    $a['home_cat'] = $p('home_cat', 60);
    $a['home_title'] = $p('home_title', 120);
    $a['home_text'] = $p('home_text', 160);

    $titles = [];
    foreach (civilina_articles_published() as $other) {
        $titles[$other['slug']] = $other['crumb'] !== '' ? $other['crumb'] : $other['title'];
    }
    // Keep the label already written for a link that does not change.
    foreach ($a['related'] as [$slug, $label]) {
        if (isset($titles[$slug])) {
            $titles[$slug] = $label;
        }
    }
    $related = [];
    foreach (['related_1', 'related_2'] as $field) {
        $slug = (string) ($_POST[$field] ?? '');
        if (isset($titles[$slug]) && $slug !== $a['slug']) {
            $related[] = [$slug, $titles[$slug]];
        }
    }
    $a['related'] = $related;

    // The address can change until the first publication only (links would break afterwards).
    if ($a['status'] !== 'published' && $a['date_published'] === '') {
        $slug = civilina_md_slug($p('slug', 120) !== '' ? $p('slug', 120) : $a['title']);
        $a['slug'] = mb_substr($slug, 0, 90);
    }
    return $a;
}

function civilina_admin_journal_post(array $user): void
{
    $action = (string) ($_POST['action'] ?? '');
    $id = (int) ($_POST['id'] ?? 0);
    $existing = $id > 0 ? civilina_article_find($id) : null;
    $userId = (string) $user['id'];

    if ($action === 'unpublish' && $existing) {
        try {
            $result = civilina_article_unpublish($id, $userId);
        } catch (Throwable $e) {
            civilina_audit('article.error', $existing['slug'] . ' : ' . $e->getMessage(), $userId);
            $result = ['ok' => false, 'messages' => ['Retrait interrompu : ' . $e->getMessage() . ' Réessayez.']];
        }
        civilina_flash($result['ok'] ? 'Article retiré du site. Il reste disponible ici en brouillon.' : implode(' ', $result['messages']));
        header('Location: ' . civilina_admin_url('article', ['id' => $id]), true, 303);
        exit;
    }

    if (!in_array($action, ['save', 'publish', 'schedule'], true)) {
        header('Location: ' . civilina_admin_url('journal'), true, 303);
        exit;
    }

    $a = civilina_admin_article_from_post($existing);
    $errors = [];
    if ($a['title'] === '') {
        $errors[] = 'Le titre est obligatoire.';
    }
    $sameSlug = $a['slug'] !== '' ? civilina_article_find_by_slug($a['slug']) : null;
    if ($a['slug'] === '' || ($sameSlug && $sameSlug['id'] !== (int) ($a['id'] ?? 0))) {
        $errors[] = 'Cette adresse (slug) est déjà utilisée par un autre article : choisissez-en une autre.';
    }
    if ($action === 'schedule') {
        $when = trim((string) ($_POST['publish_date'] ?? '')) . ' ' . trim((string) ($_POST['publish_time'] ?? '08:00'));
        if (!preg_match('~^\d{4}-\d{2}-\d{2} \d{2}:\d{2}$~', $when) || $when <= date('Y-m-d H:i')) {
            $errors[] = 'Choisissez une date et une heure de publication dans le futur.';
        }
    }
    if ($errors !== []) {
        $_SESSION['article_draft'] = $a;
        $_SESSION['article_errors'] = $errors;
        header('Location: ' . civilina_admin_url('article', $existing ? ['id' => $id] : ['new' => 1]), true, 303);
        exit;
    }

    if ($a['status'] === 'published') {
        $a['pending'] = true;
    }
    if ($action === 'schedule') {
        $a['status'] = 'scheduled';
        $a['publish_at'] = $when;
    } elseif ($a['status'] === 'scheduled' && $action === 'save') {
        // keeps its schedule
    }
    $id = civilina_article_save($a);
    civilina_audit($existing ? 'article.saved' : 'article.created', $a['slug'], $userId);

    if ($action === 'publish') {
        try {
            $result = civilina_article_publish($id, $userId);
        } catch (Throwable $e) {
            // Every step can be replayed: publishing again finishes the job.
            civilina_audit('article.error', $a['slug'] . ' : ' . $e->getMessage(), $userId);
            $result = ['ok' => false, 'messages' => ['Publication interrompue : ' . $e->getMessage() . ' Cliquez à nouveau sur « Mettre à jour en ligne » pour terminer.']];
        }
        if (!$result['ok']) {
            $_SESSION['article_errors'] = $result['messages'];
            civilina_flash('Enregistré, mais pas encore publié : complétez les points signalés.');
        } else {
            $_SESSION['article_published'] = $result['messages'];
            civilina_flash('Article publié sur civilina.com.');
        }
    } elseif ($action === 'schedule') {
        $problems = civilina_article_problems(civilina_article_find($id) ?? $a);
        if ($problems !== []) {
            civilina_db()->prepare("UPDATE articles SET status = 'draft', publish_at = '' WHERE id = ?")->execute([$id]);
            $_SESSION['article_errors'] = $problems;
            civilina_flash('Enregistré en brouillon : la programmation demande un article complet.');
        } else {
            civilina_flash('Publication programmée le ' . date('d/m/Y à H:i', strtotime($when)) . '.');
        }
    } else {
        civilina_flash($a['status'] === 'published' ? 'Enregistré. La version en ligne ne change qu\'avec « Mettre à jour en ligne ».' : 'Brouillon enregistré.');
    }
    header('Location: ' . civilina_admin_url('article', ['id' => $id]), true, 303);
    exit;
}

function civilina_admin_status_badge(array $a): string
{
    $class = ['draft' => 'archived', 'scheduled' => 'in_progress', 'published' => 'done'][$a['status']] ?? 'archived';
    $label = CIVILINA_ARTICLE_STATUSES[$a['status']] ?? $a['status'];
    if ($a['status'] === 'published' && !empty($a['pending'])) {
        $label .= ' · modifs non publiées';
        $class = 'new';
    }
    return '<span class="status status-' . $class . '">' . h($label) . '</span>';
}

function civilina_admin_journal_list(array $user): void
{
    if ((int) civilina_db()->query('SELECT COUNT(*) FROM articles')->fetchColumn() === 0) {
        $count = civilina_articles_import_all();
        civilina_audit('article.imported', $count . ' articles', (string) $user['id']);
        civilina_flash($count . ' articles en ligne importés dans l\'éditeur. Vous pouvez maintenant les modifier ici.');
    }
    $articles = civilina_articles_all();
    civilina_admin_start('Journal', 'journal', $user);
    ?>
    <div class="filters">
      <a class="bo-btn" href="<?= h(civilina_admin_url('article', ['new' => 1])) ?>">+ Nouvel article</a>
      <span class="count"><?= count(array_filter($articles, static fn (array $a): bool => $a['status'] === 'published')) ?> en ligne · <?= count(array_filter($articles, static fn (array $a): bool => $a['status'] !== 'published')) ?> en préparation</span>
    </div>
    <section class="panel">
      <div class="table-wrap"><table>
        <thead><tr><th>Titre</th><th>Axe</th><th>Statut</th><th>Date</th><th>Lecture</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><a href="<?= h(civilina_admin_url('article', ['id' => $a['id']])) ?>"><?= h($a['title'] !== '' ? $a['title'] : '(sans titre)') ?></a><small><?= h(civilina_article_pill($a)) ?></small></td>
            <td><?= civilina_axis_badge($a['axis']) ?></td>
            <td><?= civilina_admin_status_badge($a) ?></td>
            <td><?= h($a['status'] === 'scheduled' ? date('d/m/Y H:i', strtotime($a['publish_at'])) : ($a['date_published'] !== '' ? date('d/m/Y', strtotime($a['date_published'])) : '—')) ?></td>
            <td><?= civilina_article_minutes($a) ?> min</td>
            <td class="nowrap"><?php if ($a['status'] === 'published'): ?><a href="/journal/<?= h($a['slug']) ?>/" target="_blank" rel="noopener">Voir ↗</a><?php endif; ?></td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
    </section>
    <?php
    civilina_admin_end();
}

function civilina_admin_article_form(array $user): void
{
    $id = (int) ($_GET['id'] ?? 0);
    $stored = $id > 0 ? civilina_article_find($id) : null;
    if ($id > 0 && !$stored) {
        header('Location: ' . civilina_admin_url('journal'));
        exit;
    }
    $a = $_SESSION['article_draft'] ?? $stored ?? (civilina_article_defaults() + ['id' => 0, 'slug' => '', 'status' => 'draft', 'publish_at' => '', 'date_published' => '', 'date_modified' => '']);
    $errors = $_SESSION['article_errors'] ?? [];
    $published = $_SESSION['article_published'] ?? null;
    unset($_SESSION['article_draft'], $_SESSION['article_errors'], $_SESSION['article_published']);

    $render = civilina_md_render($a['body']);
    $others = array_filter(civilina_articles_published(), static fn (array $o): bool => $o['slug'] !== $a['slug']);
    $related = array_column($a['related'], 0);
    $isPublished = $a['status'] === 'published';
    $slugLocked = $isPublished || $a['date_published'] !== '';
    $csrf = civilina_csrf_token();
    $title = $stored ? 'Modifier l\'article' : 'Nouvel article';

    civilina_admin_start($title, 'journal', $user);
    ?>
    <p class="crumbs"><a href="<?= h(civilina_admin_url('journal')) ?>">← Tous les articles</a></p>

    <?php if ($errors): ?>
      <div class="bo-alert"><b>À corriger :</b><ul><?php foreach ($errors as $error): ?><li><?= h($error) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <?php if ($published && $stored): ?>
      <section class="panel published-panel">
        <div class="panel-head"><h2>Publié. Partagez-le sur LinkedIn</h2><a href="/journal/<?= h($stored['slug']) ?>/" target="_blank" rel="noopener">Voir l'article ↗</a></div>
        <p class="hint">Fichiers mis à jour : <?= h(implode(', ', $published)) ?></p>
        <label class="stack-label">Brouillon de post (sélectionnez et copiez)
          <textarea rows="9" readonly><?= h(civilina_article_linkedin_post($stored)) ?></textarea>
        </label>
        <div class="actions"><a class="bo-btn" href="https://www.linkedin.com/sharing/share-offsite/?url=<?= h(rawurlencode(civilina_article_url($stored))) ?>" target="_blank" rel="noopener">Partager sur LinkedIn ↗</a></div>
      </section>
    <?php endif; ?>

    <form method="post" action="<?= h(civilina_admin_url('article', $stored ? ['id' => $stored['id']] : ['new' => 1])) ?>" class="editor">
      <input type="hidden" name="_token" value="<?= h($csrf) ?>">
      <input type="hidden" name="id" value="<?= (int) ($a['id'] ?? 0) ?>">

      <div class="editor-bar">
        <span><?= civilina_admin_status_badge($a) ?>
          <?php if ($a['status'] === 'scheduled'): ?> le <?= h(date('d/m/Y à H:i', strtotime($a['publish_at']))) ?><?php endif; ?>
          <?php if ($isPublished): ?> · <a href="/journal/<?= h($a['slug']) ?>/" target="_blank" rel="noopener">voir en ligne ↗</a><?php endif; ?>
        </span>
        <span class="editor-actions">
          <button class="bo-btn bo-btn-ghost" type="submit" name="action" value="save">Enregistrer</button>
          <button class="bo-btn bo-btn-ghost" type="submit" formaction="<?= h(civilina_admin_url('preview')) ?>" formtarget="_blank">Aperçu ↗</button>
          <button class="bo-btn" type="submit" name="action" value="publish"><?= $isPublished ? 'Mettre à jour en ligne' : 'Publier maintenant' ?></button>
        </span>
      </div>

      <div class="editor-grid">
        <div class="editor-main">
          <fieldset>
            <legend>Article</legend>
            <label>Titre (H1)<input type="text" name="title" value="<?= h($a['title']) ?>" required maxlength="200"></label>
            <div class="row3">
              <label>Axe<select name="axis"><?php foreach (CIVILINA_ARTICLE_AXES as $key => $axis): ?><option value="<?= h($key) ?>"<?= $key === $a['axis'] ? ' selected' : '' ?>><?= h($axis[0]) ?></option><?php endforeach; ?></select></label>
              <label>Rubrique <small>après « Axe · »</small><input type="text" name="rubric" value="<?= h($a['rubric']) ?>" placeholder="Organisation, QA/QC…"></label>
              <label>Titre court <small>fil d'Ariane</small><input type="text" name="crumb" value="<?= h($a['crumb']) ?>"></label>
            </div>
            <label>Adresse <small>civilina.com/journal/…/<?= $slugLocked ? ' — figée depuis la publication' : ' — vide = générée depuis le titre' ?></small>
              <input type="text" name="slug" value="<?= h($a['slug']) ?>" pattern="[a-z0-9-]*"<?= $slugLocked ? ' readonly' : '' ?>></label>
            <label>Chapeau <small>introduction en gras</small><textarea name="chapo" rows="4"><?= h($a['chapo']) ?></textarea></label>
            <label>Corps de l'article <small><?= (int) $render['words'] ?> mots · <?= count($render['toc']) ?> sections · lecture <?= civilina_article_minutes($a) ?> min</small>
              <textarea name="body" rows="30" class="code" spellcheck="true"><?= h($a['body']) ?></textarea></label>
            <details class="syntax">
              <summary>Aide-mémoire de la syntaxe</summary>
              <pre>## Titre de section            → une section (apparaît dans le sommaire)
## Titre {#ancre}              → section avec une ancre choisie
### Sous-titre
Un paragraphe peut tenir sur plusieurs lignes ; une ligne vide le termine.
**gras**   *italique*   ^exposant^
[texte du lien](/journal/autre-article/)    [site externe](https://…)
- élément de liste
1. élément numéroté
- [ ] point de checklist
| Colonne | Colonne |
|---|---|
| cellule | cellule |
{.table-wrap}                  → ligne à mettre avant un tableau large
:::retenir                     (ou :::exemple, :::eviter, suivi d'un titre facultatif)
Texte de l'encadré.
:::
\*  \[  \]  \|                  → caractère affiché tel quel</pre>
            </details>
          </fieldset>

          <fieldset>
            <legend>Sources</legend>
            <div class="row2">
              <label>Titre du bloc<input type="text" name="sources_title" value="<?= h($a['sources_title']) ?>" placeholder="Références citées"></label>
              <label>Durée de lecture <small>0 = calculée</small><input type="number" name="minutes" min="0" max="60" value="<?= (int) $a['minutes'] ?>"></label>
            </div>
            <label>Références <small>une par ligne, commençant par « - »</small><textarea name="sources" rows="5" class="code"><?= h($a['sources']) ?></textarea></label>
            <label>Note sous les références<textarea name="sources_note" rows="2"><?= h($a['sources_note']) ?></textarea></label>
          </fieldset>
        </div>

        <div class="editor-side">
          <fieldset>
            <legend>Publication</legend>
            <?php if (!$isPublished): ?>
              <div class="row2">
                <label>Date<input type="date" name="publish_date" value="<?= h($a['status'] === 'scheduled' ? substr($a['publish_at'], 0, 10) : date('Y-m-d', strtotime('+1 day'))) ?>"></label>
                <label>Heure<input type="time" name="publish_time" value="<?= h($a['status'] === 'scheduled' ? substr($a['publish_at'], 11, 5) : '08:00') ?>"></label>
              </div>
              <button class="bo-btn bo-btn-ghost wide" type="submit" name="action" value="schedule">Programmer la publication</button>
              <p class="hint">Heure d'Alger. L'article est publié dès la première visite du site ou du back-office après cette heure.</p>
            <?php else: ?>
              <p class="hint">En ligne depuis le <?= h(date('d/m/Y', strtotime($a['date_published']))) ?><?= $a['date_modified'] !== $a['date_published'] ? ', mis à jour le ' . h(date('d/m/Y', strtotime($a['date_modified']))) : '' ?>.</p>
            <?php endif; ?>
          </fieldset>

          <fieldset>
            <legend>Référencement</legend>
            <label>Titre de la page <small>vide = titre + « | Civilina »</small><input type="text" name="seo_title" value="<?= h($a['seo_title']) ?>"></label>
            <label>Description <small><?= mb_strlen($a['description']) ?> caractères · Google en affiche environ 155</small><textarea name="description" rows="4"><?= h($a['description']) ?></textarea></label>
          </fieldset>

          <fieldset>
            <legend>Couverture</legend>
            <div class="cover-preview"><img src="data:image/svg+xml;base64,<?= base64_encode(civilina_cover_svg($a)) ?>" alt="Aperçu de la couverture"></div>
            <label>Surtitre <small>vide = axe · rubrique</small><input type="text" name="cover_kicker" value="<?= h($a['cover']['kicker']) ?>"></label>
            <label>Titre, ligne 1 <small>26 caractères max</small><input type="text" name="cover_line1" value="<?= h($a['cover']['line1']) ?>" maxlength="40"></label>
            <label>Titre, ligne 2<input type="text" name="cover_line2" value="<?= h($a['cover']['line2']) ?>" maxlength="40"></label>
            <label>Sous-titre<input type="text" name="cover_subtitle" value="<?= h($a['cover']['subtitle']) ?>" maxlength="70"></label>
            <label>Couleur<select name="cover_color"><?php foreach (CIVILINA_COVER_COLORS as $hex => $name): ?><option value="<?= h($hex) ?>"<?= $hex === $a['cover']['color'] ? ' selected' : '' ?>><?= h($name) ?></option><?php endforeach; ?></select></label>
            <label>Texte alternatif<input type="text" name="cover_alt" value="<?= h($a['cover']['alt']) ?>"></label>
            <p class="hint">L'aperçu se met à jour à l'enregistrement.</p>
          </fieldset>

          <fieldset>
            <legend>Cartes</legend>
            <label>Carte du Journal <small>résumé sous le titre</small><textarea name="card_text" rows="3"><?= h($a['card_text']) ?></textarea></label>
            <label>Accueil : titre court<input type="text" name="home_title" value="<?= h($a['home_title']) ?>"></label>
            <label>Accueil : phrase<input type="text" name="home_text" value="<?= h($a['home_text']) ?>"></label>
            <label>Accueil : étiquette <small>vide = axe · rubrique</small><input type="text" name="home_cat" value="<?= h($a['home_cat']) ?>"></label>
          </fieldset>

          <fieldset>
            <legend>Appel à l'action</legend>
            <label>Titre <small>vide = texte par défaut de l'axe</small><input type="text" name="cta_title" value="<?= h($a['cta']['title']) ?>"></label>
            <label>Texte<textarea name="cta_text" rows="3"><?= h($a['cta']['text']) ?></textarea></label>
            <div class="row2">
              <label>Lien<input type="text" name="cta_url" value="<?= h($a['cta']['url']) ?>" placeholder="/engineering/"></label>
              <label>Bouton<input type="text" name="cta_label" value="<?= h($a['cta']['label']) ?>" placeholder="Découvrir…"></label>
            </div>
          </fieldset>

          <fieldset>
            <legend>Articles liés (pied de page)</legend>
            <?php foreach ([0, 1] as $n): ?>
              <select name="related_<?= $n + 1 ?>" aria-label="Article lié <?= $n + 1 ?>"><option value="">Automatique</option><?php foreach ($others as $o): ?><option value="<?= h($o['slug']) ?>"<?= ($related[$n] ?? '') === $o['slug'] ? ' selected' : '' ?>><?= h($o['crumb'] !== '' ? $o['crumb'] : $o['title']) ?></option><?php endforeach; ?></select>
            <?php endforeach; ?>
          </fieldset>
        </div>
      </div>
    </form>

    <?php if ($isPublished): ?>
      <form method="post" action="<?= h(civilina_admin_url('article', ['id' => $a['id']])) ?>" class="danger-zone">
        <input type="hidden" name="_token" value="<?= h($csrf) ?>">
        <input type="hidden" name="id" value="<?= (int) $a['id'] ?>">
        <span>Retirer l'article du site : la page disparaît, l'article reste ici en brouillon.</span>
        <button class="bo-btn bo-btn-danger" type="submit" name="action" value="unpublish">Dépublier</button>
      </form>
    <?php endif; ?>
    <?php
    civilina_admin_end();
}

/** Full-page preview of the article as submitted (nothing is saved). */
function civilina_admin_article_preview(): void
{
    $id = (int) ($_POST['id'] ?? $_GET['id'] ?? 0);
    $existing = $id > 0 ? civilina_article_find($id) : null;
    $a = $_SERVER['REQUEST_METHOD'] === 'POST' ? civilina_admin_article_from_post($existing) : $existing;
    if (!$a) {
        header('Location: ' . civilina_admin_url('journal'));
        exit;
    }
    if ($a['slug'] === '') {
        $a['slug'] = 'apercu';
    }
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, max-age=0');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: same-origin');
    $html = civilina_article_page($a, true);
    $banner = '<div style="position:sticky;top:0;z-index:100;background:#9a5b00;color:#fff;text-align:center;font:600 14px Inter,Arial,sans-serif;padding:8px">Aperçu — cette version n\'est pas publiée</div>';
    echo preg_replace('~<body>~', '<body>' . $banner, $html, 1);
}
