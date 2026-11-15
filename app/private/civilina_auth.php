<?php
declare(strict_types=1);

const CIVILINA_COMPANY_LINKEDIN = 'https://www.linkedin.com/company/civilina';
const CIVILINA_FOUNDER_LINKEDIN = 'https://www.linkedin.com/in/abdelghani-bouhouche';

function civilina_private_dir(): string
{
    $docRoot = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
    $home = $docRoot !== '' ? dirname($docRoot) : dirname(__DIR__, 2);
    return $home . '/civilina_private';
}

require_once __DIR__ . '/civilina_db.php';
require_once __DIR__ . '/civilina_journal.php';

/*
 * Day/night mode. No cookie: the device preference applies (CSS
 * prefers-color-scheme). The switch is a plain link (?theme=light|dark) that
 * stores the choice for a year in the civilina_theme cookie and reloads the
 * page; no JavaScript, so it also works under the back-office CSP.
 */
const CIVILINA_THEME_COOKIE = 'civilina_theme';

function civilina_theme(): string
{
    $theme = (string) ($_COOKIE[CIVILINA_THEME_COOKIE] ?? '');
    return in_array($theme, ['light', 'dark'], true) ? $theme : '';
}

function civilina_theme_url(?string $theme): string
{
    $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = (string) (parse_url($uri, PHP_URL_PATH) ?: '/');
    parse_str((string) (parse_url($uri, PHP_URL_QUERY) ?? ''), $query);
    unset($query['theme']);
    if ($theme !== null) {
        $query['theme'] = $theme;
    }
    return $path . ($query ? '?' . http_build_query($query) : '');
}

function civilina_apply_theme_request(): void
{
    $theme = $_GET['theme'] ?? null;
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'GET' || !is_string($theme) || !in_array($theme, ['light', 'dark'], true)) {
        return;
    }
    setcookie(CIVILINA_THEME_COOKIE, $theme, [
        'expires' => time() + 365 * 86400,
        'path' => '/',
        'secure' => true,
        'httponly' => false,
        'samesite' => 'Lax',
    ]);
    header('Location: ' . civilina_theme_url(null), true, 303);
    exit;
}

function civilina_theme_attr(): string
{
    $theme = civilina_theme();
    return $theme !== '' ? ' data-theme="' . $theme . '"' : '';
}

/** Both links are rendered; CSS shows the one that leads to the other mode. */
function civilina_theme_switch(): string
{
    return '<span class="theme-switch">'
        . '<a class="ts-to-dark" href="' . h(civilina_theme_url('dark')) . '" title="Passer en mode nuit" aria-label="Mode jour actif. Passer en mode nuit"><span class="ts-track"><span class="ts-knob">☀</span></span><span class="ts-label">Jour</span></a>'
        . '<a class="ts-to-light" href="' . h(civilina_theme_url('light')) . '" title="Passer en mode jour" aria-label="Mode nuit actif. Passer en mode jour"><span class="ts-track"><span class="ts-knob">☾</span></span><span class="ts-label">Nuit</span></a>'
        . '</span>';
}

/**
 * Day mode of the member pages (login, register, member area, MBTI), which are
 * dark by default. Each rule is emitted twice: for an explicit choice
 * (data-theme="light") and for a light device preference without a choice.
 */
function civilina_member_theme_css(): string
{
    $rules = [
        ':root' => '--line:#dce3eb;--muted:#5d6673;color-scheme:light',
        'body' => 'background:#f5f7fa;color:#102033',
        'body::before' => 'background:linear-gradient(120deg,rgba(245,247,250,.95),rgba(232,239,249,.92)),url("/assets/brand/civilina-engineering-plans.jpg") center/cover no-repeat',
        'body::after' => 'background:linear-gradient(rgba(0,59,140,.045) 1px,transparent 1px),linear-gradient(90deg,rgba(0,59,140,.045) 1px,transparent 1px);background-size:44px 44px,44px 44px',
        '.site-header' => 'background:rgba(255,255,255,.88);border-bottom-color:#dce3eb',
        '.nav-links' => 'color:#102033',
        '.nav-links a:hover' => 'color:#003b8c',
        '.glass-card, .card, .question-card, .choice-card' => 'background:#fff;border-color:#dce3eb;box-shadow:0 18px 50px rgba(16,32,51,.08)',
        '.dash-head' => 'background:linear-gradient(120deg,rgba(255,255,255,.95),rgba(232,239,249,.9)),url("/assets/brand/civilina-engineering-plans.jpg") center/cover no-repeat;border-color:#dce3eb;box-shadow:0 22px 60px rgba(16,32,51,.08)',
        'h1, h2, h3, .card strong' => 'color:#102033',
        'p' => 'color:#5d6673',
        'label' => 'color:#102033',
        'input, select' => 'border-color:#c9d3df',
        '.eyebrow' => 'color:#0089c4',
        '.chip' => 'color:#003b8c;background:#e8eff9;border-color:#c9d9f0',
        '.btn-light' => 'color:#003b8c;background:#e8eff9;border-color:#c9d9f0',
        '.btn-ghost' => 'color:#102033;background:#fff;border-color:#dce3eb',
        '.error' => 'color:#8a1c1c;background:#fdecec;border-color:#f3b9b9',
        '.success' => 'color:#0f5e40;background:#e7f4ee;border-color:#b7dfc9',
        '.identity-card, .identity-card h1' => 'color:#fff',
        '.identity-card p' => 'color:#dcebf7',
        '.identity-card .eyebrow' => 'color:#7fd3ff',
        '.site-footer' => 'background:#fff;color:#5d6673;border-top-color:#dce3eb',
        '.site-footer strong' => 'color:#102033',
        '.lang-switcher a' => 'color:#003b8c;background:#fff;border-color:#dce3eb',
        '.progress-box' => 'background:rgba(255,255,255,.95);border-color:#dce3eb',
        '.progress-track, .score-meter' => 'background:#e3e9f0',
        '.ts-to-dark' => 'display:inline-flex',
        '.ts-to-light' => 'display:none',
    ];

    $emit = static function (string $prefix) use ($rules): string {
        $css = '';
        foreach ($rules as $selectors => $declarations) {
            $parts = array_map(static fn (string $s): string => $s === ':root' ? $prefix : $prefix . ' ' . trim($s), explode(',', $selectors));
            $css .= implode(',', $parts) . '{' . $declarations . '}';
        }
        return $css;
    };

    return $emit(':root[data-theme="light"]')
        . '@media (prefers-color-scheme:light){' . $emit(':root:not([data-theme="dark"])') . '}'
        . '.theme-switch a{display:inline-flex;align-items:center;gap:8px;font-weight:800;font-size:.8rem;color:inherit}'
        . '.ts-track{position:relative;display:inline-block;width:44px;height:24px;border-radius:999px;background:#1d2f45;border:1px solid #31475e}'
        . '.ts-knob{position:absolute;top:2px;left:22px;width:18px;height:18px;border-radius:50%;background:#0b1828;color:#ffd77a;display:grid;place-items:center;font-size:12px;line-height:1}'
        . '.ts-to-dark .ts-track{background:#dbe5f1;border-color:#c5d3e3}.ts-to-dark .ts-knob{left:2px;background:#fff;color:#c58a00;box-shadow:0 1px 3px rgba(0,0,0,.2)}'
        // Must outrank ".theme-switch a" above; the day rules (prefixed) outrank these.
        . '.theme-switch .ts-to-dark{display:none}.theme-switch .ts-to-light{display:inline-flex}';
}

function civilina_boot(): void
{
    civilina_apply_theme_request();

    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
        session_start();
    }
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function civilina_csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return (string) $_SESSION['csrf_token'];
}

function civilina_verify_csrf(): void
{
    $token = (string) ($_POST['_token'] ?? '');
    if ($token === '' || !hash_equals(civilina_csrf_token(), $token)) {
        http_response_code(419);
        civilina_page_start('Session expirée | Civilina');
        echo '<main class="auth-hero"><div class="shell"><section class="glass-card">';
        echo '<span class="eyebrow">Sécurité</span><h2>Session expirée.</h2>';
        echo '<p>Rechargez la page en HTTPS puis renvoyez le formulaire. Les formulaires Civilina utilisent une protection CSRF et des cookies sécurisés.</p>';
        echo '<div class="actions"><a class="btn btn-primary" href="/login/">Connexion</a><a class="btn btn-light" href="/register/">Inscription</a></div>';
        echo '</section></div></main>';
        civilina_page_end();
        exit;
    }
}

/*
 * Users live in SQLite (table users, one JSON document per user).
 * civilina_read_users() / civilina_write_users() keep their historical
 * signatures so existing pages (register) work unchanged. Writing upserts
 * every given user and never deletes: two simultaneous registrations can no
 * longer overwrite each other.
 */
function civilina_decode_user(string $json): ?array
{
    $user = json_decode($json, true);
    return is_array($user) ? $user : null;
}

function civilina_read_users(): array
{
    $rows = civilina_db()->query('SELECT data FROM users ORDER BY created_at, rowid')->fetchAll(PDO::FETCH_COLUMN);
    return array_values(array_filter(array_map('civilina_decode_user', $rows)));
}

function civilina_save_user(array $user): void
{
    $email = mb_strtolower(trim((string) ($user['email'] ?? '')));
    $id = (string) ($user['id'] ?? '');
    if ($email === '' || $id === '') {
        throw new InvalidArgumentException('User id and email are required.');
    }
    $user['email'] = $email;
    $now = gmdate('c');
    civilina_db()->prepare(
        'INSERT INTO users(id, email, role, data, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?)
         ON CONFLICT(id) DO UPDATE SET email = excluded.email, role = excluded.role, data = excluded.data, updated_at = excluded.updated_at'
    )->execute([
        $id,
        $email,
        (string) ($user['role'] ?? 'visitor'),
        json_encode($user, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        (string) ($user['created_at'] ?? $now),
        (string) ($user['updated_at'] ?? $now),
    ]);
}

function civilina_write_users(array $users): void
{
    $db = civilina_db();
    $db->exec('BEGIN IMMEDIATE');
    try {
        foreach ($users as $user) {
            if (is_array($user)) {
                civilina_save_user($user);
            }
        }
        $db->exec('COMMIT');
    } catch (Throwable $e) {
        $db->exec('ROLLBACK');
        throw $e;
    }
}

function civilina_find_user_by_email(string $email): ?array
{
    $statement = civilina_db()->prepare('SELECT data FROM users WHERE email = ?');
    $statement->execute([mb_strtolower(trim($email))]);
    $json = $statement->fetchColumn();
    return is_string($json) ? civilina_decode_user($json) : null;
}

function civilina_find_user_by_id(string $id): ?array
{
    $statement = civilina_db()->prepare('SELECT data FROM users WHERE id = ?');
    $statement->execute([$id]);
    $json = $statement->fetchColumn();
    return is_string($json) ? civilina_decode_user($json) : null;
}

function civilina_current_user(): ?array
{
    static $cache = [];
    $id = (string) ($_SESSION['user_id'] ?? '');
    if ($id === '') {
        return null;
    }
    if (!array_key_exists($id, $cache)) {
        $cache[$id] = civilina_find_user_by_id($id);
    }
    return $cache[$id];
}

/** Back-office access: role admin. The super administrator is an admin with the super_admin flag. */
function civilina_is_admin(?array $user): bool
{
    return $user !== null && (string) ($user['role'] ?? '') === 'admin';
}

function civilina_is_super_admin(?array $user): bool
{
    return civilina_is_admin($user) && !empty($user['super_admin']);
}

function civilina_user_label(array $user): string
{
    return civilina_is_super_admin($user) ? 'Super administrateur' : civilina_role_label((string) ($user['role'] ?? 'visitor'));
}

function civilina_login(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user_id'] = (string) $user['id'];
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function civilina_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'] ?? '', (bool) $params['secure'], (bool) $params['httponly']);
    }
    session_destroy();
}

function civilina_require_auth(): array
{
    $user = civilina_current_user();
    if (!$user) {
        header('Location: /login/');
        exit;
    }
    return $user;
}

function civilina_role_label(string $role): string
{
    return [
        'super_admin' => 'Super administrateur',
        'admin' => 'Administrateur',
        'candidate' => 'Candidat',
        'company' => 'Entreprise',
        'client' => 'Client',
        'visitor' => 'Visiteur',
    ][$role] ?? 'Visiteur';
}

function civilina_allowed_account_types(): array
{
    return [
        'candidate' => 'Candidat',
        'company' => 'Entreprise',
        'client' => 'Client',
        'visitor' => 'Visiteur',
    ];
}

function civilina_posts(): array
{
    $file = rtrim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/journal/posts.json';
    if (!is_file($file)) {
        return [];
    }
    $data = json_decode((string) file_get_contents($file), true);
    return is_array($data['posts'] ?? null) ? array_slice($data['posts'], 0, 3) : [];
}

function civilina_page_start(string $title, string $class = 'auth-page', string $locale = 'fr', array $meta = []): void
{
    $user = civilina_current_user();
    $locale = in_array($locale, ['fr', 'ar', 'en'], true) ? $locale : 'fr';
    $dir = $locale === 'ar' ? 'rtl' : 'ltr';
    $bodyClass = trim($class . ' ' . ($locale === 'ar' ? 'rtl' : ''));
    $path = (string) (parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH) ?: '/');
    $canonicalPath = $path === '/' ? '/' : rtrim($path, '/') . '/';
    $canonical = (string) ($meta['canonical'] ?? ('https://civilina.com' . $canonicalPath));
    $description = trim((string) ($meta['description'] ?? 'Civilina accompagne les entreprises dans la structuration des processus, le management de projet, la qualité, le QHSE, la supply chain, les tableaux de bord et les plateformes métier sur mesure.'));
    $ogTitle = (string) ($meta['og_title'] ?? $title);
    $ogDescription = (string) ($meta['og_description'] ?? $description);
    $ogType = (string) ($meta['og_type'] ?? 'website');
    $ogImage = (string) ($meta['og_image'] ?? 'https://civilina.com/assets/brand/civilina-logo.png');
    $robots = (string) ($meta['robots'] ?? 'index, follow');
    foreach (['/login', '/register', '/dashboard', '/admin', '/logout', '/contact'] as $privatePrefix) {
        if (strncmp($path, $privatePrefix, strlen($privatePrefix)) === 0) {
            $robots = (string) ($meta['robots'] ?? 'noindex, nofollow');
            break;
        }
    }
    echo '<!doctype html><html lang="' . h($locale) . '" dir="' . h($dir) . '"' . civilina_theme_attr() . '><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . h($title) . '</title>';
    echo '<meta name="description" content="' . h($description) . '">';
    echo '<meta name="robots" content="' . h($robots) . '">';
    echo '<link rel="canonical" href="' . h($canonical) . '">';
    echo '<meta property="og:title" content="' . h($ogTitle) . '">';
    echo '<meta property="og:description" content="' . h($ogDescription) . '">';
    echo '<meta property="og:type" content="' . h($ogType) . '">';
    echo '<meta property="og:url" content="' . h($canonical) . '">';
    echo '<meta property="og:image" content="' . h($ogImage) . '">';
    echo '<meta name="twitter:card" content="summary_large_image">';
    echo '<link rel="icon" type="image/png" href="/assets/brand/civilina-logo.png">';
    echo '<link rel="shortcut icon" type="image/png" href="/assets/brand/civilina-logo.png">';
    echo '<link rel="apple-touch-icon" href="/assets/brand/civilina-logo.png">';
    echo '<style>
        :root{--ink:#061426;--night:#06111f;--panel:rgba(11,28,48,.78);--panel2:rgba(255,255,255,.08);--cyan:#24d3ee;--blue:#0b4aa3;--line:rgba(160,213,235,.22);--muted:#b7c7d8;--white:#fff;--gold:#d7ae5f}
        *{box-sizing:border-box}html{scroll-behavior:smooth}body{margin:0;font-family:Inter,ui-sans-serif,system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Arial,sans-serif;background:var(--night);color:var(--white);line-height:1.6;overflow-x:hidden}
        body::before{content:"";position:fixed;inset:0;z-index:-2;background:linear-gradient(120deg,rgba(3,12,24,.94),rgba(5,22,42,.88)),url("/assets/brand/civilina-engineering-plans.jpg") center/cover no-repeat}
        body::after{content:"";position:fixed;inset:0;z-index:-1;pointer-events:none;background:linear-gradient(rgba(36,211,238,.05) 1px,transparent 1px),linear-gradient(90deg,rgba(36,211,238,.05) 1px,transparent 1px),radial-gradient(circle at 82% 8%,rgba(36,211,238,.18),transparent 28%);background-size:44px 44px,44px 44px,auto}
        a{color:inherit;text-decoration:none}.shell{width:min(1160px,100%);margin:0 auto;padding:0 20px}
        .site-header{position:sticky;top:0;z-index:20;background:rgba(5,14,28,.78);border-bottom:1px solid var(--line);backdrop-filter:blur(18px)}
        .nav{min-height:78px;display:flex;align-items:center;justify-content:space-between;gap:22px}.brand{display:inline-flex;align-items:center;gap:12px;font-weight:900;letter-spacing:.05em}.brand-mark{width:42px;height:42px;display:grid;place-items:center;border-radius:13px;background:#fff;box-shadow:0 16px 38px rgba(36,211,238,.15)}.brand-mark img{width:31px;height:auto;display:block}
        .nav-links{display:flex;align-items:center;gap:12px;font-size:.8rem;font-weight:850;color:#d6e7f4}.nav-links a{opacity:.9}.nav-links a:hover{opacity:1;color:var(--cyan)}
        .auth-hero{padding:64px 0}.auth-grid{display:grid;grid-template-columns:minmax(0,.92fr) minmax(360px,1.08fr);gap:28px;align-items:stretch}.auth-grid>*{min-width:0}.identity-card,.glass-card{width:100%;max-width:100%;border:1px solid var(--line);border-radius:22px;background:linear-gradient(180deg,rgba(255,255,255,.12),rgba(255,255,255,.06));box-shadow:0 28px 80px rgba(0,0,0,.22);backdrop-filter:blur(16px);overflow:hidden}
        .identity-card{min-height:620px;display:flex;flex-direction:column;justify-content:flex-end;background:linear-gradient(180deg,rgba(4,12,24,.16),rgba(4,12,24,.88)),url("/assets/brand/civilina-iso10006-visual.png") center/cover no-repeat;padding:28px}.identity-card h1{font-size:clamp(2.4rem,6vw,5.2rem);line-height:.92;margin:0}.identity-card p{max-width:560px;color:#dcebf7;font-size:1.05rem}
        .glass-card{padding:30px}.eyebrow{display:inline-flex;align-items:center;gap:10px;color:var(--cyan);font-size:.72rem;font-weight:950;letter-spacing:.12em;text-transform:uppercase}.eyebrow::before{content:"";width:30px;height:2px;background:var(--gold)}
        h1,h2,h3{letter-spacing:0;overflow-wrap:anywhere}h2{margin:12px 0 10px;font-size:clamp(1.8rem,4vw,3rem);line-height:1.03}p{color:var(--muted);overflow-wrap:anywhere}label{display:block;margin:14px 0 6px;color:#eaf6ff;font-weight:800;font-size:.9rem}input,select{width:100%;min-height:46px;border:1px solid rgba(194,226,242,.24);border-radius:12px;background:rgba(255,255,255,.92);color:#07152b;padding:11px 13px;font:inherit}
        .form-row{display:grid;grid-template-columns:1fr 1fr;gap:12px}.check{display:flex;align-items:flex-start;gap:10px;margin-top:16px}.check input{width:auto;min-height:auto;margin-top:5px}.error{padding:12px 14px;border-radius:12px;background:rgba(255,76,76,.14);border:1px solid rgba(255,120,120,.26);color:#ffd8d8}.success{padding:12px 14px;border-radius:12px;background:rgba(36,211,238,.12);border:1px solid rgba(36,211,238,.28);color:#defaff}
        .btn{display:inline-flex;align-items:center;justify-content:center;min-height:46px;padding:12px 18px;border:1px solid transparent;border-radius:11px;font-weight:900;cursor:pointer}.btn-primary{color:#fff;background:linear-gradient(135deg,#0b4aa3,#24d3ee);box-shadow:0 20px 45px rgba(36,211,238,.16)}.btn-light{color:#082f73;background:#fff;border-color:rgba(255,255,255,.24)}.btn-ghost{color:#eaf8ff;background:rgba(255,255,255,.08);border-color:var(--line)}.actions{display:flex;flex-wrap:wrap;gap:12px;margin-top:22px}.actions .btn{border-radius:10px}
        .dash{padding:54px 0}.dash-head{border:1px solid var(--line);border-radius:24px;background:linear-gradient(120deg,rgba(4,14,28,.82),rgba(8,47,115,.62)),url("/assets/brand/civilina-engineering-plans.jpg") center/cover no-repeat;padding:34px;box-shadow:0 28px 80px rgba(0,0,0,.22)}.dash-head h1{margin:12px 0 0;font-size:clamp(2.2rem,5vw,4.4rem);line-height:1}
        .grid{display:grid;gap:16px}.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}.grid-4{grid-template-columns:repeat(4,minmax(0,1fr))}.card{border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.08);padding:20px;box-shadow:0 18px 52px rgba(0,0,0,.14)}.card strong{color:#fff}.card p{margin:.5rem 0 0}.chip{display:inline-flex;border:1px solid var(--line);border-radius:999px;padding:7px 10px;color:#dff7ff;background:rgba(36,211,238,.08);font-weight:850;font-size:.78rem}
        .section{margin-top:26px}.section h2{font-size:clamp(1.5rem,3vw,2.2rem)}.post-card{display:grid;gap:12px}.post-meta{display:flex;flex-wrap:wrap;gap:8px}
        .lang-switcher{display:flex;gap:8px;flex-wrap:wrap;margin:18px 0}.lang-switcher a{border:1px solid var(--line);border-radius:999px;padding:7px 11px;font-weight:900;color:#dff7ff;background:rgba(255,255,255,.07)}.lang-switcher a.active{background:linear-gradient(135deg,#0b4aa3,#24d3ee);color:#fff}
        .mbti-scale{display:grid;gap:14px}.question-card{border:1px solid var(--line);border-radius:18px;background:rgba(255,255,255,.08);padding:18px}.question-options{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-top:12px}.choice-card{display:flex;gap:10px;align-items:flex-start;border:1px solid rgba(194,226,242,.22);border-radius:14px;background:rgba(255,255,255,.08);padding:14px;cursor:pointer}.choice-card input{width:auto;min-height:auto;margin-top:4px}.progress-box{position:sticky;bottom:0;margin-top:20px;border:1px solid var(--line);border-radius:18px;background:rgba(5,14,28,.92);backdrop-filter:blur(18px);padding:16px}.progress-track{height:10px;border-radius:999px;background:rgba(255,255,255,.14);overflow:hidden}.progress-bar{height:100%;width:0;background:linear-gradient(90deg,#0b4aa3,#24d3ee)}
        .score-bars{display:grid;gap:10px}.score-line{display:grid;grid-template-columns:42px 1fr 42px;gap:10px;align-items:center}.score-meter{height:10px;border-radius:999px;background:rgba(255,255,255,.12);overflow:hidden}.score-meter span{display:block;height:100%;background:linear-gradient(90deg,#24d3ee,#d7ae5f)}
        body.rtl{text-align:right}.rtl .nav,.rtl .footer-grid{direction:rtl}.rtl .brand,.rtl .actions,.rtl .post-meta,.rtl .choice-card{direction:rtl}.rtl .eyebrow::before{order:2}.rtl .question-options{direction:rtl}
        .site-footer{padding:34px 0;color:#b9cada;border-top:1px solid var(--line);background:rgba(3,10,20,.72)}.footer-grid{display:flex;justify-content:space-between;gap:20px;align-items:center}.footer-links{display:flex;gap:16px;flex-wrap:wrap;font-weight:850}
        @media(max-width:940px){.nav{align-items:flex-start;flex-direction:column;padding:16px 20px}.nav-links{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));width:min(100%,390px);gap:8px}.nav-links a{border:1px solid var(--line);border-radius:999px;padding:8px 9px;text-align:center;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}.auth-grid,.grid-3,.grid-4{grid-template-columns:1fr}.identity-card{min-height:420px}.form-row{grid-template-columns:1fr}.footer-grid{align-items:flex-start;flex-direction:column}}
        @media(max-width:560px){.shell{padding:0 14px}.nav{padding:14px}.auth-hero,.dash{padding:36px 0}.glass-card,.dash-head{padding:22px}.actions .btn{width:100%}.identity-card h1,.dash-head h1{font-size:2.4rem}.question-options{grid-template-columns:1fr}}
    </style><style>' . civilina_member_theme_css() . '</style></head><body class="' . h($bodyClass) . '">';
    echo '<header class="site-header"><div class="shell nav"><a class="brand" href="/"><span class="brand-mark"><img src="/assets/brand/civilina-logo.png" alt="Logo Civilina"></span><span>CIVILINA</span></a><nav class="nav-links" aria-label="Navigation principale">';
    // Same sections as the public site (V2): the three axes, CIVILINA IA and the Journal.
    echo '<a href="/engineering/">Engineering</a><a href="/gestion-entreprise/">Entreprise</a><a href="/digital/">Digital</a><a href="/ia/">CIVILINA IA</a><a href="/journal/">Journal</a>';
    if ($user) {
        echo '<a href="/dashboard/">Mon espace</a>';
        if (civilina_is_admin($user)) {
            echo '<a href="/admin/">Back-office</a>';
        }
        echo '<a href="/logout/">Déconnexion</a>';
    } else {
        echo '<a href="/login/">Connexion</a>';
    }
    echo '</nav>' . civilina_theme_switch() . '</div></header>';
}

function civilina_page_end(): void
{
    echo '<footer class="site-footer"><div class="shell footer-grid"><div><strong>Civilina</strong><p>Engineering, management et digitalisation pour les projets d\'ingénierie.</p></div><nav class="footer-links"><a href="/engineering/">Engineering</a><a href="/gestion-entreprise/">Entreprise</a><a href="/digital/">Digital</a><a href="/journal/">Journal</a><a href="/#contact">Contact</a><a href="' . CIVILINA_COMPANY_LINKEDIN . '" target="_blank" rel="noopener">LinkedIn</a><a href="/mentions-legales/">Mentions légales</a><a href="/confidentialite/">Confidentialité</a></nav></div></footer></body></html>';
}

function civilina_auth_identity_card(): void
{
    echo '<aside class="identity-card"><span class="eyebrow">Civilina</span><h1>CIVILINA</h1><p>Engineering, management et digitalisation pour les projets d\'ingénierie : qualité, pilotage de projet et outils de gestion.</p></aside>';
}

civilina_boot();


