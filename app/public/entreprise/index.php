<?php
require dirname($_SERVER['DOCUMENT_ROOT']) . '/civilina_private/civilina_auth.php';

$user = civilina_require_auth();
$isCompany = (string) ($user['role'] ?? '') === 'company';

civilina_page_start('Espace entreprise Civilina', 'dashboard-page');
?>
<main class="dash">
    <div class="shell">
        <section class="dash-head">
            <span class="eyebrow">Espace entreprise</span>
            <h1>Structurer vos processus, projets et indicateurs.</h1>
            <p>Cet espace entreprise est provisoire. Il prépare les futurs modules Civilina pour le pilotage opérationnel, la qualité, le QHSE, la supply chain et les tableaux de bord.</p>
            <?php if (!$isCompany): ?>
                <p class="success">Votre compte actuel est de type <?= h(civilina_role_label((string) ($user['role'] ?? 'visitor'))) ?>. Les fonctionnalités entreprise seront activées progressivement selon votre profil.</p>
            <?php endif; ?>
            <div class="actions">
                <a class="btn btn-primary" href="/dashboard">Retour dashboard</a>
                <a class="btn btn-light" href="/#contact">Demander un accompagnement</a>
            </div>
        </section>
        <section class="section grid grid-3">
            <article class="card"><strong>Diagnostic processus</strong><p>Cartographier les flux, responsabilités, preuves et points de décision.</p></article>
            <article class="card"><strong>Pilotage projet</strong><p>Structurer jalons, risques, revues et indicateurs de coordination.</p></article>
            <article class="card"><strong>Tableaux de bord</strong><p>Préparer des KPI lisibles pour les responsables et managers.</p></article>
        </section>
    </div>
</main>
<?php civilina_page_end(); ?>
