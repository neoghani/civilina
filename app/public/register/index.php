<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_auth.php';

$errors = [];
$values = [
    'first_name' => '',
    'last_name' => '',
    'email' => '',
    'phone' => '',
    'account_type' => 'visitor',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    civilina_verify_csrf();
    foreach ($values as $key => $default) {
        $values[$key] = trim((string) ($_POST[$key] ?? $default));
    }
    $values['email'] = mb_strtolower($values['email']);
    $allowedTypes = civilina_allowed_account_types();
    $password = (string) ($_POST['password'] ?? '');
    $passwordConfirmation = (string) ($_POST['password_confirmation'] ?? '');

    if ($values['first_name'] === '') {
        $errors[] = 'Le prénom est obligatoire.';
    }
    if ($values['last_name'] === '') {
        $errors[] = 'Le nom est obligatoire.';
    }
    if (!filter_var($values['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Adresse email invalide.';
    }
    if (!array_key_exists($values['account_type'], $allowedTypes)) {
        $errors[] = 'Type de compte invalide.';
    }
    if (strlen($password) < 8) {
        $errors[] = 'Le mot de passe doit contenir au moins 8 caractères.';
    }
    if ($password !== $passwordConfirmation) {
        $errors[] = 'La confirmation du mot de passe ne correspond pas.';
    }
    if (empty($_POST['terms'])) {
        $errors[] = 'Vous devez accepter les conditions.';
    }
    // Anti-spam (M5): 5 account creations attempted per hour and per address.
    if (!$errors && !civilina_form_hit_allowed('register', 5, 3600)) {
        $errors[] = 'Trop de tentatives depuis votre connexion. Réessayez dans une heure.';
    }
    if (!$errors && civilina_find_user_by_email($values['email'])) {
        $errors[] = 'Un compte existe déjà avec cette adresse email.';
    }

    if (!$errors) {
        $users = civilina_read_users();
        $user = [
            'id' => bin2hex(random_bytes(12)),
            'first_name' => $values['first_name'],
            'last_name' => $values['last_name'],
            'name' => trim($values['first_name'] . ' ' . $values['last_name']),
            'email' => $values['email'],
            'phone' => $values['phone'],
            'account_type' => $values['account_type'],
            'role' => $values['account_type'],
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'terms_accepted_at' => gmdate('c'),
            'created_at' => gmdate('c'),
            'updated_at' => gmdate('c'),
        ];
        $users[] = $user;
        civilina_write_users($users);
        civilina_login($user);
        header('Location: /dashboard');
        exit;
    }
}

civilina_page_start('Inscription gratuite Civilina');
?>
<main class="auth-hero">
    <div class="shell auth-grid">
        <?php civilina_auth_identity_card(); ?>
        <section class="glass-card">
            <span class="eyebrow">Inscription gratuite</span>
            <h2>Créer votre accès Civilina.</h2>
            <p>Créez gratuitement votre accès Civilina pour consulter les ressources, suivre les publications, tester les outils disponibles et accéder progressivement aux modules de pilotage, MBTI et performance.</p>
            <?php foreach ($errors as $error): ?>
                <p class="error"><?= h($error) ?></p>
            <?php endforeach; ?>
            <form method="post" action="/register/" novalidate>
                <input type="hidden" name="_token" value="<?= h(civilina_csrf_token()) ?>">
                <div class="form-row">
                    <div>
                        <label for="first_name">Prénom</label>
                        <input id="first_name" name="first_name" value="<?= h($values['first_name']) ?>" autocomplete="given-name" required>
                    </div>
                    <div>
                        <label for="last_name">Nom</label>
                        <input id="last_name" name="last_name" value="<?= h($values['last_name']) ?>" autocomplete="family-name" required>
                    </div>
                </div>

                <label for="email">Email</label>
                <input id="email" name="email" type="email" value="<?= h($values['email']) ?>" autocomplete="email" required>

                <label for="phone">Téléphone facultatif</label>
                <input id="phone" name="phone" value="<?= h($values['phone']) ?>" autocomplete="tel">

                <label for="account_type">Type de compte</label>
                <select id="account_type" name="account_type" required>
                    <?php foreach (civilina_allowed_account_types() as $value => $label): ?>
                        <option value="<?= h($value) ?>" <?= $values['account_type'] === $value ? 'selected' : '' ?>><?= h($label) ?></option>
                    <?php endforeach; ?>
                </select>

                <div class="form-row">
                    <div>
                        <label for="password">Mot de passe</label>
                        <input id="password" name="password" type="password" autocomplete="new-password" required>
                    </div>
                    <div>
                        <label for="password_confirmation">Confirmation mot de passe</label>
                        <input id="password_confirmation" name="password_confirmation" type="password" autocomplete="new-password" required>
                    </div>
                </div>

                <label class="check">
                    <input type="checkbox" name="terms" value="1" required>
                    <span>J’accepte les conditions d’utilisation de l’espace Civilina.</span>
                </label>

                <div class="actions">
                    <button class="btn btn-primary" type="submit">Créer mon accès gratuit</button>
                    <a class="btn btn-light" href="/login">J’ai déjà un compte</a>
                </div>
            </form>
        </section>
    </div>
</main>
<?php civilina_page_end(); ?>
