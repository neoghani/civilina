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
