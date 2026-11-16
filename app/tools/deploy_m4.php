<?php

declare(strict_types=1);

/*
 * One-off (milestone M4): run once on the server after uploading the M4 code
 * and private/resources/. Migrates to schema v5 (contacts become leads),
 * writes the 9 draft offers, adds the "free template" box to the 3 related
 * articles and the new public pages to the sitemap. Deletes itself.
 */

header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');

$private = dirname(rtrim((string) $_SERVER['DOCUMENT_ROOT'], '/\\')) . '/civilina_private';
require $private . '/civilina_sales.php';

try {
    $db = civilina_db();
    echo 'schema=' . (int) $db->query('PRAGMA user_version')->fetchColumn() . "\n";
    echo 'leads=' . (int) $db->query('SELECT COUNT(*) FROM leads')->fetchColumn() . "\n";
    echo 'offers_seeded=' . civilina_offers_seed() . "\n";
    foreach (array_keys(CIVILINA_RESOURCES) as $slug) {
        echo 'resource ' . $slug . '=' . (civilina_resource_path($slug) ? 'ok' : 'MISSING') . "\n";
    }
    echo 'resource_boxes=' . implode(',', civilina_articles_insert_resource_boxes('deploiement-m4')) . "\n";

    $sitemapFile = civilina_site_root() . '/sitemap.xml';
    $sitemap = str_replace("\r\n", "\n", (string) file_get_contents($sitemapFile));
    $added = [];
    foreach (['ressources', 'devis', 'newsletter'] as $page) {
        $loc = '<loc>https://civilina.com/' . $page . '/</loc>';
        if (!str_contains($sitemap, $loc)) {
            $sitemap = str_replace('</urlset>', "  <url>\n    " . $loc . "\n    <lastmod>" . date('Y-m-d') . "</lastmod>\n  </url>\n</urlset>", $sitemap);
            $added[] = $page;
        }
    }
    if ($added !== []) {
        civilina_backup_files([$sitemapFile], 'sitemap-m4');
        civilina_write_file($sitemapFile, $sitemap);
    }
    echo 'sitemap_added=' . implode(',', $added) . "\n";

    // Privacy page: the processing added by M1 (audience), M3 (newsletter) and M4 (quotes, templates, prospecting).
    $privacyFile = civilina_site_root() . '/confidentialite/index.html';
    $privacy = str_replace("\r\n", "\n", (string) file_get_contents($privacyFile));
    if (str_contains($privacy, 'id="newsletter-donnees"')) {
        echo "privacy=already\n";
    } else {
        $edits = [
            '<p class="updated">Dernière mise à jour : 20 août 2026</p>' => '<p class="updated">Dernière mise à jour : 16 novembre 2026</p>',
            '<h2 id="finalites">3. Finalités</h2>' => '<h3>Demandes de devis</h3>' . "\n"
                . '<p>Le formulaire de devis recueille les mêmes informations que le formulaire de contact, ainsi que l’offre choisie. Ces données servent à préparer la proposition et à assurer le suivi commercial de votre demande.</p>' . "\n"
                . '<h3 id="newsletter-donnees">Newsletter</h3>' . "\n"
                . '<p>Si vous vous inscrivez, Civilina conserve votre adresse électronique, votre prénom s’il est indiqué, les thèmes choisis, ainsi que la date de votre inscription et de sa confirmation. L’inscription n’est effective qu’après un clic de confirmation dans l’e-mail reçu (double confirmation). Chaque newsletter contient un lien pour changer de thèmes ou se désinscrire en un clic. Les e-mails sont envoyés par l’intermédiaire du prestataire Brevo, qui mesure les ouvertures et les clics de manière agrégée.</p>' . "\n"
                . '<h3>Modèles téléchargeables</h3>' . "\n"
                . '<p>Pour recevoir un modèle, vous indiquez votre adresse électronique et, si vous le souhaitez, votre prénom et votre entreprise. Ces données servent à vous donner accès au fichier et, le cas échéant, à vous recontacter au sujet des services de Civilina, sur la base de l’intérêt légitime d’une relation professionnelle. Vous pouvez vous y opposer à tout moment. L’inscription à la newsletter proposée sur ce formulaire est facultative et distincte.</p>' . "\n"
                . '<h3>Mesure d’audience</h3>' . "\n"
                . '<p>Civilina compte les pages vues sans cookie : une empreinte anonyme, renouvelée chaque jour, permet de compter les visites sans identifier les visiteurs. L’adresse IP n’est jamais conservée en clair. Seuls la page consultée, le site d’origine, la campagne éventuelle et le type d’appareil sont enregistrés, pendant 400 jours.</p>' . "\n"
                . '<h2 id="finalites">3. Finalités</h2>',
            '<li>vérifier et, après autorisation, monétiser certains contenus éditoriaux avec Google AdSense.</li></ul>' => '<li>envoyer la newsletter aux personnes inscrites et mesurer son efficacité ;</li><li>donner accès aux modèles téléchargeables et suivre les échanges commerciaux avec les professionnels intéressés ;</li><li>mesurer l’audience du site de façon anonyme ;</li><li>vérifier et, après autorisation, monétiser certains contenus éditoriaux avec Google AdSense.</li></ul>',
            'Les données techniques de sécurité ne sont pas utilisées pour établir un profil commercial individuel.</p>' => 'Les données techniques de sécurité ne sont pas utilisées pour établir un profil commercial individuel.</p>' . "\n"
                . '<p>Les données des prospects (demandes, téléchargements de modèles) sont conservées trois ans au plus après le dernier échange. Les données des abonnés à la newsletter sont conservées jusqu’à la désinscription ; une inscription non confirmée est sans effet. Les statistiques d’audience sont conservées 400 jours.</p>',
            'aux prestataires techniques strictement nécessaires à l’hébergement ou au fonctionnement du site.' => 'aux prestataires techniques strictement nécessaires à l’hébergement ou au fonctionnement du site, dont Brevo pour l’envoi des e-mails (confirmation d’inscription, newsletter, liens de téléchargement).',
        ];
        $missing = array_filter(array_keys($edits), static fn (string $anchor): bool => substr_count($privacy, $anchor) !== 1);
        if ($missing !== []) {
            echo 'privacy=SKIPPED (page différente de celle attendue)' . "\n";
        } else {
            civilina_backup_files([$privacyFile], 'confidentialite');
            civilina_write_file($privacyFile, strtr($privacy, $edits));
            echo "privacy=updated\n";
        }
    }
    civilina_audit('sales.deployed', 'M4');
    echo "ok\n";
} catch (Throwable $e) {
    echo 'ERROR ' . get_class($e) . ': ' . $e->getMessage() . "\n";
}

@unlink(__FILE__);
