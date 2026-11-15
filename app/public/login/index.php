<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_auth.php';

$errors = [];
$email = '';

$current = civilina_current_user();
if ($current) {
    header('Location: ' . (civilina_is_admin($current) ? '/admin/' : '/dashboard/'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    civilina_verify_csrf();
    $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');

    if (civilina_login_blocked($email)) {
        // Same message whatever the account: it does not reveal whether the e-mail exists.
        $errors[] = 'Trop de tentatives de connexion. Réessayez dans 15 minutes.';
        civilina_audit('login.blocked');
    } else {
        $user = civilina_find_user_by_email($email);
        if (!$user || !password_verify($password, (string) ($user['password_hash'] ?? ''))) {
            civilina_record_login_attempt($email, false);
            civilina_audit('login.failed', '', (string) ($user['id'] ?? ''));
            $errors[] = 'Email ou mot de passe incorrect.';
        } else {
            civilina_record_login_attempt($email, true);
            civilina_login($user);
            civilina_audit('login.success', '', (string) $user['id']);
            header('Location: ' . (civilina_is_admin($user) ? '/admin/' : '/dashboard/'));
            exit;
        }
    }
}

civilina_page_start('Connexion | Civilina');
?>
<main class="auth-hero">
    <div class="shell auth-grid">
        <?php civilina_auth_identity_card(); ?>
        <section class="glass-card">
            <span class="eyebrow">Connexion</span>
            <h2>Accéder à votre espace Civilina.</h2>
            <p>Connectez-vous pour retrouver vos ressources, vos résultats MBTI et les derniers articles du Journal.</p>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?= h($error) ?></p>
            <?php endforeach; ?>
            <form method="post" action="/login/" novalidate>
                <input type="hidden" name="_token" value="<?= h(civilina_csrf_token()) ?>">
                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= h($email) ?>" autocomplete="email" required>

                <label for="password">Mot de passe</label>
                <input id="password" name="password" type="password" autocomplete="current-password" required>

                <div class="actions">
                    <button class="btn btn-primary" type="submit">Se connecter</button>
                    <a class="btn btn-light" href="/register/">Créer un accès gratuit</a>
                </div>
            </form>
        </section>
    </div>
</main>
<?php civilina_page_end(); ?>
