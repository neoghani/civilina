<?php

declare(strict_types=1);

/*
 * Back-office layout and helpers (civilina.com/admin/).
 * Access: role admin. Users and security pages: super administrator only.
 */

require_once __DIR__ . '/civilina_auth.php';
require_once __DIR__ . '/civilina_stats.php';
require_once __DIR__ . '/civilina_admin_journal.php';
require_once __DIR__ . '/civilina_admin_newsletter.php';
require_once __DIR__ . '/civilina_admin_sales.php';
require_once __DIR__ . '/civilina_backup.php';

/** Back-office navigation. Modules of later milestones are shown with their date, without fake data. */
const CIVILINA_ADMIN_NAV = [
    'dashboard' => ['Tableau de bord', null],
    'contacts' => ['Demandes de contact', null],
    'journal' => ['Journal', null],
    'stats' => ['Statistiques', null],
    'newsletter' => ['Newsletter', null],
    'sales' => ['Offres et prospection', null],
    'users' => ['Utilisateurs', null],
    'security' => ['Sécurité', null],
    'backups' => ['Sauvegardes', null],
];

const CIVILINA_SUPER_ADMIN_VIEWS = ['users', 'security', 'nl_settings', 'backups'];

function civilina_admin_headers(): void
{
    header('X-Robots-Tag: noindex, nofollow');
    header('Cache-Control: no-store, max-age=0');
    header('X-Frame-Options: DENY');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: same-origin');
    header("Content-Security-Policy: default-src 'self'; style-src 'self' https://fonts.googleapis.com; font-src https://fonts.gstatic.com; img-src 'self' data:; script-src 'none'; form-action 'self'; frame-ancestors 'none'; base-uri 'none'");
}

function civilina_require_admin(): array
{
    $user = civilina_require_auth();
    if (!civilina_is_admin($user)) {
        civilina_audit('admin.denied', (string) ($_SERVER['REQUEST_URI'] ?? ''), (string) ($user['id'] ?? ''));
        header('Location: /dashboard/');
        exit;
    }
    return $user;
}

function civilina_flash(?string $message = null): ?string
{
    if ($message !== null) {
        $_SESSION['admin_flash'] = $message;
        return null;
    }
    $value = $_SESSION['admin_flash'] ?? null;
    unset($_SESSION['admin_flash']);
    return is_string($value) ? $value : null;
}

function civilina_admin_url(string $view, array $params = []): string
{
    $params = array_merge($view === 'dashboard' ? [] : ['view' => $view], $params);
    return '/admin/' . ($params ? '?' . http_build_query($params) : '');
}

function civilina_admin_start(string $title, string $view, array $user, string $head = ''): void
{
    civilina_admin_headers();
    $flash = civilina_flash();
    $first = (string) ($user['first_name'] ?? $user['name'] ?? '');
    ?>
<!doctype html>
<html lang="fr"<?= civilina_theme_attr() ?>>
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= h($title) ?> | Back-office Civilina</title>
<link rel="icon" type="image/png" href="/assets/brand/civilina-logo.png">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/admin.css">
<?= $head ?>
</head>
<body>
<div class="bo">
  <aside class="bo-side">
    <a class="bo-brand" href="/admin/"><img src="/assets/brand/civilina-logo.png" alt=""><span>CIVILINA<small>Back-office</small></span></a>
    <nav class="bo-nav" aria-label="Back-office">
      <?php foreach (CIVILINA_ADMIN_NAV as $key => [$label, $milestone]):
          if (in_array($key, CIVILINA_SUPER_ADMIN_VIEWS, true) && !civilina_is_super_admin($user)) {
              continue;
          }
          $class = $key === $view ? 'active' : '';
          if ($milestone !== null) {
              $class .= ' soon';
          }
          ?>
        <a class="<?= h(trim($class)) ?>" href="<?= h(civilina_admin_url($key)) ?>"><?= h($label) ?><?php if ($milestone !== null): ?><em><?= h($milestone) ?></em><?php endif; ?></a>
      <?php endforeach; ?>
    </nav>
    <div class="bo-side-foot">
      <a href="/" target="_blank" rel="noopener">Voir le site ↗</a>
      <a href="/dashboard/">Espace membre</a>
    </div>
  </aside>
  <div class="bo-main">
    <header class="bo-top">
      <h1><?= h($title) ?></h1>
      <div class="bo-user">
        <?= civilina_theme_switch() ?>
        <span><?= h($first) ?> · <b><?= h(civilina_user_label($user)) ?></b></span>
        <a class="bo-btn bo-btn-ghost" href="/logout/">Déconnexion</a>
      </div>
    </header>
    <main class="bo-content">
      <?php if ($flash !== null): ?><p class="bo-flash"><?= h($flash) ?></p><?php endif; ?>
<?php
}

function civilina_admin_end(): void
{
    ?>
    </main>
  </div>
</div>
</body>
</html>
<?php
}

function civilina_axis_badge(string $axis): string
{
    $label = CIVILINA_AXES[$axis] ?? CIVILINA_AXES['a_qualifier'];
    $key = array_key_exists($axis, CIVILINA_AXES) ? $axis : 'a_qualifier';
    return '<span class="axis axis-' . h($key) . '">' . h($label) . '</span>';
}

function civilina_status_badge(string $status): string
{
    $label = CIVILINA_CONTACT_STATUSES[$status] ?? $status;
    return '<span class="status status-' . h($status) . '">' . h($label) . '</span>';
}

function civilina_admin_datetime(string $iso): string
{
    $time = strtotime($iso);
    return $time === false ? $iso : date('d/m/Y H:i', $time);
}

/** Count of MBTI results, without reading their content. */
function civilina_mbti_results_count(): int
{
    $file = civilina_private_dir() . '/mbti_results.json';
    if (!is_file($file)) {
        return 0;
    }
    $data = json_decode((string) file_get_contents($file), true);
    if (!is_array($data)) {
        return 0;
    }
    return isset($data['results']) && is_array($data['results']) ? count($data['results']) : count($data);
}

/* ---------- Backups (M5, super administrator) ---------- */

function civilina_admin_backups_post(array $user): void
{
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'backup_now') {
        $r = civilina_backup_run('manuelle');
        civilina_flash($r['ok'] ? 'Sauvegarde créée : ' . $r['file'] . ' (' . $r['files'] . ' fichiers, ' . round($r['size'] / 1024) . ' Ko).' : 'Échec de la sauvegarde : ' . $r['error']);
    } elseif ($action === 'verify') {
        $r = civilina_backup_verify((string) ($_POST['file'] ?? ''));
        $_SESSION['backup_report'] = ['file' => (string) ($_POST['file'] ?? ''), 'ok' => $r['ok'], 'report' => $r['report']];
        civilina_flash($r['ok'] ? 'Sauvegarde vérifiée : restaurable.' : 'La vérification a trouvé un problème.');
    }
    header('Location: ' . civilina_admin_url('backups'), true, 303);
    exit;
}

function civilina_admin_backups_view(array $user): void
{
    if (isset($_GET['download'])) {
        $path = civilina_backup_path((string) $_GET['download']);
        if ($path !== null) {
            civilina_audit('backup.downloaded', basename($path), (string) $user['id']);
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($path) . '"');
            header('Content-Length: ' . filesize($path));
            header('Cache-Control: no-store');
            readfile($path);
            exit;
        }
    }
    $list = civilina_backups_list();
    $report = $_SESSION['backup_report'] ?? null;
    unset($_SESSION['backup_report']);
    $csrf = civilina_csrf_token();
    $last = $list[0] ?? null;
    civilina_admin_start('Sauvegardes', 'backups', $user);
    ?>
    <section class="kpis">
      <article class="kpi"><span>Dernière sauvegarde</span><strong><?= $last ? h(civilina_admin_datetime($last['date'])) : '—' ?></strong><small>Automatique chaque jour, à la première visite après 24 h</small></article>
      <article class="kpi"><span>Sauvegardes conservées</span><strong><?= count($list) ?></strong><small>Les <?= CIVILINA_BACKUP_KEEP_DAYS ?> plus récentes</small></article>
    </section>
    <?php if (is_array($report)): ?>
      <section class="panel"><div class="panel-head"><h2>Vérification de <?= h($report['file']) ?></h2><?= $report['ok'] ? '<span class="status status-done">Restaurable</span>' : '<span class="status status-new">Problème</span>' ?></div>
        <ul class="nl-checks"><?php foreach ($report['report'] as $line): ?><li class="<?= $report['ok'] ? 'ok' : 'ko' ?>"><?= h($line) ?></li><?php endforeach; ?></ul>
      </section>
    <?php endif; ?>
    <section class="panel">
      <div class="panel-head"><h2>Fichiers</h2>
        <form method="post" action="<?= h(civilina_admin_url('backups')) ?>" class="inline"><input type="hidden" name="_token" value="<?= h($csrf) ?>"><button class="bo-btn" type="submit" name="action" value="backup_now">Sauvegarder maintenant</button></form>
      </div>
      <?php if (!$list): ?>
        <p class="empty">Aucune sauvegarde pour le moment.</p>
      <?php else: ?>
      <div class="table-wrap"><table>
        <thead><tr><th>Sauvegarde</th><th>Taille</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($list as $b): ?>
          <tr>
            <td><?= h(civilina_admin_datetime($b['date'])) ?><small><?= h($b['file']) ?></small></td>
            <td><?= round($b['size'] / 1024) ?> Ko</td>
            <td>
              <form method="post" action="<?= h(civilina_admin_url('backups')) ?>" class="inline">
                <input type="hidden" name="_token" value="<?= h($csrf) ?>"><input type="hidden" name="file" value="<?= h($b['file']) ?>">
                <button class="bo-btn bo-btn-small bo-btn-ghost" type="submit" name="action" value="verify">Vérifier</button>
                <a class="bo-btn bo-btn-small bo-btn-ghost" href="<?= h(civilina_admin_url('backups', ['download' => $b['file']])) ?>">Télécharger</a>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table></div>
      <?php endif; ?>
    </section>
    <p class="hint">Chaque sauvegarde contient la base de données (comptes, demandes, prospects, abonnés, articles) et les pages écrites par le back-office ; elle reste hors du site public. « Vérifier » rouvre le fichier, contrôle chaque empreinte et l'intégrité de la base : c'est le test de restauration. Procédure de restauration : docs/site-vitrine/exploitation.md. Les fichiers téléchargés contiennent des données personnelles : à conserver en lieu sûr.</p>
    <?php
    civilina_admin_end();
}
