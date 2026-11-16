# Application PHP de civilina.com (espace membre et back-office)

Le site vitrine (`site-vitrine/`) est statique. Cette partie contient le code PHP
dynamique, déployé sur l'hébergement Octenium (cPanel).

| Dépôt | Serveur |
|---|---|
| `app/private/*.php` | `~/civilina_private/` (hors `public_html`) |
| `app/public/<page>/index.php` | `~/public_html/<page>/index.php` |
| `site-vitrine/assets/css/admin.css` | `~/public_html/assets/css/admin.css` |
| `app/tools/` | Scripts ponctuels, jamais laissés sur le serveur |

## Données

- Base SQLite : `~/civilina_private/storage/civilina.sqlite` (utilisateurs,
  demandes de contact, tentatives de connexion, journal des actions, paramètres).
- Version du schéma : `PRAGMA user_version`. La version 1 importe une fois
  `users.json` et `contact_requests.json` ; ces fichiers restent en archive et ne
  sont plus écrits.
- Les adresses IP ne sont jamais stockées en clair (empreinte salée).
- Ne jamais versionner la base, `users.json`, `contact_requests.json` ni
  `mbti_results.json` : ils contiennent des données personnelles.

## Journal (jalon M2) : le serveur devient la source

Depuis M2, les articles sont rédigés et publiés dans le back-office
(`/admin/?view=journal`). La publication écrit sur le serveur :

- `journal/<slug>/index.html` et `assets/journal/<couverture>.svg` ;
- la liste des articles de `journal/index.html` ;
- les 3 cartes de l'accueil (`default.html`) : le dernier article de chaque axe ;
- les entrées Journal de `sitemap.xml`.

Conséquences pour les déploiements depuis ce dépôt :

- ne plus copier ces fichiers depuis `site-vitrine/` vers le serveur sans
  vérifier qu'ils n'ont pas été republiés entre-temps (comparer les empreintes) ;
- le contenu de référence des articles est dans la base SQLite du serveur
  (table `articles`), pas dans `site-vitrine/journal/`.

Chaque fichier remplacé est d'abord copié dans
`civilina_private/backups/journal/<date>-<objet>/` (25 dernières sauvegardes).
La syntaxe d'écriture est décrite en tête de `private/civilina_markup.php` et
dans l'aide-mémoire de l'éditeur. Les brouillons préparés hors du back-office
sont dans `docs/site-vitrine/brouillons/` et chargés avec `tools/load_draft.php`.

## Newsletter (jalon M3)

- Code : `private/civilina_newsletter.php` (abonnés, envoi Brevo, campagnes) et
  `private/civilina_admin_newsletter.php` (écrans du back-office) ; page publique
  `public/newsletter/index.php` ; schéma v4 (subscribers, campaigns,
  campaign_sends, email_log, form_hits).
- Double opt-in : l'e-mail de confirmation mène à une page avec un bouton (les
  antivirus de messagerie qui ouvrent les liens ne confirment donc rien). Liens
  personnels signés pour les thèmes et la désinscription, en-têtes
  List-Unsubscribe (désinscription en un clic).
- Envoi : API transactionnelle Brevo, un appel par destinataire, dans le quota
  quotidien (réglage, 280 par défaut). La file est traitée par la page de la
  campagne, le back-office et le collecteur `/visite/` ; une campagne programmée
  part donc à la première visite après son heure.
- La clé d'API est saisie par le super administrateur dans le back-office et
  stockée dans la base privée ; elle n'est jamais réaffichée.
- Le bloc d'inscription est écrit par le gabarit des articles
  (`civilina_newsletter_block`) ; il a été inséré une fois sur l'accueil, la page
  Journal et les articles existants par `tools/deploy_m3.php`.
- Test local : le routeur de développement définit `CIVILINA_MAIL_OUTBOX` ; les
  e-mails sont alors écrits en JSON dans `civilina_private/outbox/`.

## Référencement et images de partage (16/11/2026)

- Chaque publication écrit, à côté de la couverture SVG, une image PNG
  1200 × 630 (`assets/journal/<couverture>.png`) dessinée avec GD et la police
  Inter de `private/fonts/` (licence OFL) : LinkedIn, Facebook et X refusent le
  SVG. Les pages d'articles déclarent cette image (og:image, dimensions, texte
  alternatif, twitter:card) et des données structurées Article (image, auteur,
  logo) et BreadcrumbList.
- Titre de recherche : champ « Titre SEO » de l'éditeur, 60 caractères au plus ;
  description : 155 caractères au plus. Le titre Open Graph reste le titre complet.
- `private/civilina_seo.php` contient les titres et descriptions courts appliqués
  le 16/11 aux 17 articles et aux 6 pages principales (accueil, axes, IA,
  Journal), leurs images de partage (`assets/brand/share/`) et les données
  Organization/WebSite de l'accueil. Appliqué une fois par `tools/deploy_seo.php`
  (idempotent, sauvegarde des pages, dates de publication inchangées).

## Rôles

- `admin` : accès au back-office `/admin/`.
- Super administrateur : un `admin` avec le drapeau `super_admin` ; seul accès aux
  pages Utilisateurs et Sécurité. Attribué avec `app/tools/promote_super_admin.php`.

## Sécurité

- Sessions sécurisées, jeton CSRF sur tous les formulaires.
- Connexion bloquée 15 min après 5 échecs par compte ou 20 par adresse.
- Back-office : `noindex`, `no-store`, CSP sans script, interdiction d'affichage
  en iframe.

## Test local

Assembler une copie (site + `app/public` + `app/private` + `mbti_module.php`)
avec des comptes fictifs, puis `php -S localhost:8767 -t <copie>/public_html`.
