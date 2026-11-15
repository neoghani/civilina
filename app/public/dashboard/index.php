<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_auth.php';
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/mbti_module.php';

$user = civilina_require_auth();
$latestMbti = civilina_mbti_latest_for_user($user);
$articles = array_slice(civilina_journal_articles(), 0, 3);
$axes = [
    ['Engineering', 'PAQ, ITP, base normative et contrôle qualité pour les bureaux d\'études et les projets.', '/engineering/'],
    ['Entreprise', 'Management fondé sur ISO ou PMI, avec des tableaux de bord web et Excel.', '/gestion-entreprise/'],
    ['Digital', 'Plateformes de gestion sur mesure pour les startups et les entreprises.', '/digital/'],
];

civilina_page_start('Mon espace | Civilina', 'dashboard-page');
?>
<main class="dash">
    <div class="shell">
        <section class="dash-head">
            <span class="eyebrow">Mon espace</span>
            <h1>Bonjour, <?= h((string) ($user['first_name'] ?? $user['name'] ?? '')) ?>.</h1>
            <p><span class="chip"><?= h(civilina_user_label($user)) ?></span></p>
            <div class="actions">
                <?php if (civilina_is_admin($user)): ?>
                    <a class="btn btn-primary" href="/admin/">Ouvrir le back-office</a>
                <?php endif; ?>
                <a class="btn btn-light" href="/journal/">Lire le Journal</a>
                <a class="btn btn-ghost" href="/logout/">Se déconnecter</a>
            </div>
        </section>

        <section class="section">
            <span class="eyebrow">Derniers articles</span>
            <h2>Le Journal Civilina.</h2>
            <div class="grid grid-3">
                <?php foreach ($articles as $article): ?>
                    <article class="card post-card">
                        <div class="post-meta"><span class="chip"><?= h($article['category']) ?></span></div>
                        <h3><?= h($article['title']) ?></h3>
                        <p><?= h(civilina_format_date($article['date'])) ?></p>
                        <div class="actions"><a class="btn btn-light" href="<?= h($article['url']) ?>">Lire l'article</a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section">
            <span class="eyebrow">Nos 3 axes</span>
            <h2>Ce que Civilina peut faire pour vous.</h2>
            <div class="grid grid-3">
                <?php foreach ($axes as [$title, $description, $url]): ?>
                    <article class="card">
                        <strong>CIVILINA <?= h($title) ?></strong>
                        <p><?= h($description) ?></p>
                        <div class="actions"><a class="btn btn-ghost" href="<?= h($url) ?>">Découvrir</a></div>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="section grid grid-3">
            <article class="card">
                <strong>Test MBTI Civilina</strong>
                <?php if ($latestMbti): ?>
                    <p>Dernier résultat : <span class="chip"><?= h((string) ($latestMbti['profile_code'] ?? '')) ?></span></p>
                    <div class="actions">
                        <a class="btn btn-light" href="<?= h(civilina_mbti_result_url('fr', (string) $latestMbti['session_id'])) ?>">Consulter</a>
                        <a class="btn btn-ghost" href="/fr/mbti/history">Historique</a>
                    </div>
                <?php else: ?>
                    <p>Préférences de travail et de management, en français, arabe ou anglais. Résultat privé.</p>
                    <div class="actions"><a class="btn btn-light" href="/fr/mbti/test">Démarrer le test</a></div>
                <?php endif; ?>
            </article>
            <article class="card">
                <strong>Un projet à cadrer ?</strong>
                <p>Décrivez votre besoin en quelques lignes : nous revenons vers vous pour un premier échange.</p>
                <div class="actions"><a class="btn btn-light" href="/#contact">Nous contacter</a></div>
            </article>
            <article class="card">
                <strong>Suivre Civilina</strong>
                <p>Les nouveaux articles du Journal sont partagés sur la page LinkedIn Civilina.</p>
                <div class="actions"><a class="btn btn-ghost" href="<?= h(CIVILINA_COMPANY_LINKEDIN) ?>" target="_blank" rel="noopener">LinkedIn</a></div>
            </article>
        </section>
    </div>
</main>
<?php civilina_page_end(); ?>
