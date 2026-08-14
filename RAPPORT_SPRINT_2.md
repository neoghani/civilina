# SPRINT 2 - CIVILINA AI JOURNEY RESULT

## Parcours

Six états ont été implémentés dans le Command Center existant: question initiale, conversation guidée, première lecture, environnement recommandé, compte, puis confidentialité ou expert lorsque les réponses le justifient. Une seule question est posée à la fois et l'action `Recommencer` réinitialise la session.

## Scénarios

- Projects: environnement EPC, portefeuille, suivi coûts/délais et difficulté principale.
- Organisation: taille, responsabilités, validations et objectif de management.
- ISO: référentiel, maturité, difficulté et outils du système de management.
- Digitalisation: processus, outils actuels, friction et résultat attendu.

Chaque scénario testé utilise quatre réponses et produit une restitution différente.

## Première lecture

Quatre priorités contextuelles et deux ou trois observations sont construites à partir des réponses données. Aucun score scientifique, aucune certification garantie et aucune conclusion arbitraire ne sont affichés.

## Recommandations

Les règles sélectionnent uniquement les briques Civilina pertinentes. Les tests ont produit:

- Projects: 7 briques;
- Organisation: 4 briques;
- ISO: 6 briques;
- Digitalisation: 3 briques.

## Compte

Le compte apparaît après la restitution et l'environnement recommandé. Microsoft, Google et e-mail restent des prochaines intégrations désactivées, car ce livrable autonome ne contient pas de `register.html` et ne doit pas simuler une authentification.

## Confidentialité

La détection sensible exige une intention explicite dans la saisie libre. La réponse sensible est remplacée avant écriture dans `sessionStorage`. Aucun upload, document, NDA ou signature réelle n'existe.

## Expert humain

Le handoff est activé sur les combinaisons spécialisées Projects/EPC et ISO intégré. Une fiche affiche sujet, entreprise à préciser, priorités et résumé. Le visiteur choisit ensuite explicitement le lien e-mail existant.

## Responsive

Captures réalisées pour conversation, première lecture et recommandation à:

- 1440 px;
- 1024 px;
- 768 px;
- 430 px;
- 390 px.

Aucun débordement horizontal détecté sur les 15 états capturés.

## Technique

Fichiers principaux modifiés ou créés:

- `index.html`
- `js/civilina-ai-scenarios.js`
- `js/civilina-ai-journey.js`
- `qa/validate-journey.mjs`
- `qa/accessibility-validation.mjs`
- `qa/capture-journey.mjs`

Résultats:

- quatre scénarios: validés;
- reprise `sessionStorage`: validée;
- reset: validé;
- détection sensible sans stockage en clair: validée;
- aucun champ upload: validé;
- un seul H1: validé;
- progression, labels et régions `aria-live`: validés;
- menu mobile, `aria-expanded` et Escape: validés;
- `prefers-reduced-motion`: validé;
- console: 0 erreur;
- erreurs de page: 0.

## Git

- Branche: `sprint-2-ai-journey`
- Baseline importée: `16fb412`
- Commit fonctionnel: `HEAD` - `feat: build civilina ai diagnostic journey prototype`

## Livraison

Le dossier et le ZIP sont produits après commit et contrôlés sans `.env`, secret, token, `vendor`, `node_modules`, cache ni sauvegarde inutile.

## Prévu pour intégration Laravel

Visitor Session -> Conversation -> Diagnostic -> Organisation/User -> Workspace -> Expert Handoff -> Projects/Processes/Documents. La session serveur, la persistance consentie, les comptes, les workspaces, les autorisations et le suivi expert restent à construire dans Civilina Core.
