# Back-office CIVILINA — plan de construction accéléré

Version du 15/11/2026. Périmètre : civilina.com (hébergement Octenium, cPanel).

## 1. Constat sur l'existant

| Élément | État actuel | Problème |
|---|---|---|
| `/dashboard/` | Page d'accueil membre : bannière, profil, 2 cartes | Identique pour un visiteur et pour l'administrateur ; aucune donnée, aucune action |
| Navigation | 10 liens de l'ancienne charte (Services, Publications, Recrutement…) | Pointe vers des pages redirigées ou non indexées ; incohérente avec le site V2 |
| `/admin/` | Formulaires « Publication + LinkedIn », « Recrutement », « MBTI » | Hérité de la V1 ; ne gère pas les articles du Journal |
| Stockage | Fichiers JSON dans `civilina_private/` (users, contacts, MBTI, publications) | Pas de requêtes, pas de statistiques, risque d'écriture concurrente |
| Authentification | Sessions PHP, `password_hash`, jeton CSRF, rôle `admin` | Bonne base, à conserver ; pas de limitation des tentatives de connexion |
| Journal | 16 articles en HTML statique, publiés à la main | Toute modification passe par un déploiement technique |
| Contacts | `contact_requests.json` + e-mail | Aucun suivi commercial, pas de rattachement à un axe |

## 2. Principes retenus

- **Deux espaces distincts** : l'espace membre (`/dashboard/`, clients et visiteurs inscrits) et le back-office (`/admin/`, super administrateur), avec la charte V2.
- **PHP + SQLite**, sur le socle d'authentification existant : un seul fichier de base hors du dossier public, aucun mot de passe de base de données à gérer, sauvegarde simple, 1 inode. Migration possible vers MySQL ou vers la plateforme Laravel plus tard.
- **Le Journal reste statique** : le back-office stocke les articles en base, puis **génère** les pages HTML (article, index du Journal, cartes de l'accueil, sitemap). Le site garde sa vitesse, son référencement et AdSense.
- **Statistiques sans cookie** : comptage côté serveur, adresse IP hachée, aucun bandeau supplémentaire.
- **Newsletter via un prestataire d'envoi** (Brevo, offre gratuite de 300 e-mails/jour) : l'envoi depuis l'hébergement mutualisé serait limité et finirait en spam. Double opt-in et lien de désinscription obligatoires.
- **Tout est rattaché à un axe** : Engineering, Entreprise, Digital (et IA en transverse) — articles, contacts, abonnés, offres, prospects.

## 3. Modules du back-office

1. **Tableau de bord** : visites par jour, articles les plus lus, sources (Google, LinkedIn, direct), demandes de contact et abonnés par axe, taux de conversion, alertes (demande non traitée depuis 48 h).
2. **Journal** : liste, éditeur par blocs (chapo, sections, encadrés, tableaux, checklist, sources), aperçu, statuts brouillon / programmé / publié, couverture SVG générée, liens de partage et brouillon de post LinkedIn.
3. **Newsletter** : abonnés et segments par axe, campagne pré-remplie avec les derniers articles, envoi test, envoi, statistiques d'ouverture et de clic.
4. **Offres** : 3 axes, chacun avec ses offres (offre d'appel, offre principale, accompagnement), affichées sur les pages du site.
5. **Prospection (CRM)** : pistes issues du site et pistes ajoutées à la main ou importées (CSV), pipeline par axe (nouveau → qualifié → proposition → gagné / perdu), relances datées, modèles d'e-mail.
6. **Paramètres et sécurité** : utilisateurs et rôles, journal des actions, sauvegardes.

## 4. Jalons (semaines de travail du dimanche au jeudi)

| Jalon | Date | Livrable | Critère de réussite |
|---|---|---|---|
| **M0 — Socle et nettoyage** ✅ livré le 15/11/2026 | **Mar. 17/11/2026** | Base SQLite, migration des JSON, rôle super administrateur, nouveau gabarit `/admin/` en charte V2, espace membre nettoyé (navigation V2), limitation des tentatives de connexion | L'ancien visuel a disparu ; toutes les données existantes sont retrouvées |
| **M1 — Statistiques** ✅ livré le 15/11/2026 | **Jeu. 19/11/2026** | Compteur de visites sans cookie, tableau de bord des performances, demandes de contact par axe | Les chiffres du jour s'affichent et correspondent aux visites réelles |
| **M2 — Éditeur du Journal** ✅ livré et validé le 16/11/2026 (fiche KPI publiée depuis le back-office) | **Mar. 24/11/2026** | Import des 16 articles, éditeur, aperçu, publication avec génération des pages et du sitemap | L'article 17 (fiche indicateur KPI) est publié entièrement depuis le back-office |
| **M3 — Newsletter** ✅ livré le 16/11/2026 (envoi en attente de la clé Brevo) | **Dim. 29/11/2026** (1er envoi **mar. 01/12**) | Formulaire d'inscription sur le site, double opt-in, segments par axe, composition et envoi via Brevo | La 1re newsletter part le mardi 1er décembre |
| **M4 — Vente et prospection** ✅ livré le 16/11/2026 (offres en brouillon : prix et mise en ligne à faire dans le back-office) | **Lun. 07/12/2026** | Offres par axe sur le site, demandes de devis rattachées à un axe, 3 ressources téléchargeables contre e-mail (modèle ITP, matrice RACI Excel, fiche KPI), CRM et relances | La 1re campagne de prospection est lancée |
| **M5 — Recette et sécurité** | **Jeu. 10/12/2026** | Revue de sécurité (CSRF, injection de contenu, droits), sauvegardes automatiques, documentation | Aucun défaut bloquant ; procédure de sauvegarde testée |

Le Journal continue en parallèle : un article tous les 2 à 3 jours jusqu'à M2, ensuite depuis le back-office. La demande de réexamen AdSense est envisagée vers mi-décembre, avec environ 20 articles indexés.

## 5. Ce qu'il faut de votre côté

- **Avant le 1er envoi (01/12)** : créer le compte Brevo, authentifier le domaine civilina.com (enregistrements DNS fournis par Brevo, à ajouter dans cPanel → Zone Editor), valider l'adresse d'expéditeur, puis coller la clé d'API dans Newsletter → Réglages d'envoi et cliquer sur « Vérifier ». Les inscriptions reçues avant sont enregistrées ; leurs e-mails de confirmation partent dès que la clé est en place.
- **Offres (M4)** : relire les 9 offres préparées (Offres et prospection → Offres), saisir le prix « À partir de » des offres d'appel en DA et en €, puis « Enregistrer et mettre en ligne ». Rien n'apparaît sur les pages d'axe tant qu'aucune offre n'est en ligne.
- **Prospection (M4)** : importer la liste de cibles (CSV : nom ; entreprise ; fonction ; email ; telephone ; axe ; notes) et dater les relances.
- **À chaque jalon** : une recette de 15 minutes sur le site en ligne.

## 6. Règles de déploiement

- Sauvegarde dans `~/civilina-site-backups/` avant chaque modification serveur ; contrôle d'intégrité par empreinte SHA-256.
- Ne pas écraser `/login/`, `/entreprise/`, `/mbti/` sans migration testée ; la base SQLite reste hors de `public_html`.
- Pas de lien vers la plateforme CIVILINA Engineering tant que les correctifs de sécurité P0 ne sont pas testés.
