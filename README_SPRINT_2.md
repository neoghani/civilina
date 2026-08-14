# CIVILINA.COM - Sprint 2 - Civilina AI Journey

## Objectif

Transformer la démonstration Civilina AI de la homepage Sprint 1.1 en un parcours produit local et déterministe:

Visiteur -> conversation -> compréhension -> première lecture -> environnement recommandé -> compte -> confidentialité ou expert lorsque pertinent.

La homepage, Civilina Control, Civilina Engineering et l'identité visuelle Sprint 1.1 restent la baseline.

## Version

- Sprint: 2
- Date: 2026-08-14
- Branche: `sprint-2-ai-journey`
- Commit de livraison: `HEAD` (`feat: build civilina ai diagnostic journey prototype`)
- Baseline Sprint 1.1: `880d24cebb502815da7bc545ec5010b9f9c3766e`
- Déploiement: aucun

## Architecture du prototype

- `index.html`: interface Civilina AI, styles du parcours et homepage gelée.
- `js/civilina-ai-scenarios.js`: questions, suggestions, règles, priorités et recommandations.
- `js/civilina-ai-journey.js`: état, navigation, rendu, reprise de session et sécurité locale.
- `qa/`: tests Playwright fonctionnels, responsive et accessibilité.

Le moteur ne contient ni API, ni endpoint, ni appel OpenAI. Toutes les règles sont déterministes et exécutées dans le navigateur.

## Modèle d'état

```text
state = {
  scenario,
  phase,
  step,
  answers,
  messages,
  priorities,
  observations,
  recommendations,
  sensitive,
  expertSuggested,
  peopleSuggested
}
```

Les phases visibles sont: `start`, `conversation`, `reading`, `recommendation`, `account`, `followup`.

## Quatre scénarios

### Projects

Activité, volume du portefeuille, outils de suivi et difficulté principale. La restitution peut recommander Project & Portfolio Control, Planning & WBS, Cost Control, Engineering & Documents, QA/QC, Procurement et Executive Reporting selon les réponses.

### Organisation

Taille, difficulté organisationnelle, mode de décision et objectif. Les recommandations couvrent seulement les besoins déclenchés: rôles, processus, workflows de décision, reporting ou documents.

### ISO / Management System

Référentiel, maturité, difficulté et outils. Aucune certification n'est garantie. Les recommandations peuvent inclure Quality Management, Process Management, Audit & Actions, Document Control, responsabilités et dashboard.

### Digitalisation

Processus, outils actuels, difficulté et objectif. Le prototype part du fonctionnement réel avant de proposer workflow, automatisation, contrôle documentaire, reporting ou procurement.

## Logique de restitution

- Quatre réponses avant la première lecture.
- Aucun score scientifique ou indicateur inventé.
- Deux ou trois observations issues uniquement des réponses fournies.
- Quatre priorités avec un statut contextuel.
- Trois à sept briques recommandées selon les règles du scénario.
- Le compte n'apparaît qu'après la première lecture et la recommandation.

## Session et confidentialité

`sessionStorage` conserve temporairement la progression non sensible. Les réponses libres ne sont jamais conservées en clair: elles sont remplacées par une mention neutre. Aucun mot de passe, document, NDA, token ou contenu confidentiel n'est stocké.

Une intention explicite de partager un document interne ouvre uniquement une étape informative. Aucun champ fichier, upload ou signature n'existe.

## Compte et expert

Microsoft, Google et e-mail sont présentés comme prochaines intégrations, désactivées dans ce prototype autonome. Aucun faux login n'est créé.

Le handoff expert est proposé seulement lorsque plusieurs conditions spécialisées sont réunies. Le bouton e-mail utilise le moyen existant et laisse l'utilisateur décider de transmettre le résumé affiché.

## Limites volontaires

- Pas de backend AI ou Laravel.
- Pas de LLM, API ou clé.
- Pas de base de données.
- Pas d'authentification réelle.
- Pas de connexion Microsoft ou Google.
- Pas d'upload, NDA ou workspace réel.
- Pas de reconstruction MBTI.
- Pas de déploiement, cPanel, DNS ou modification serveur.
- Les routes secondaires de la baseline ne sont pas dupliquées dans ce livrable ciblé.

## Intégration Laravel future

```text
Visitor Session
-> Civilina AI Conversation
-> Diagnostic
-> Organisation / User
-> Workspace
-> Expert Handoff
-> Projects / Processes / Documents
```

Le futur Civilina Core pourra remplacer `sessionStorage` par une session serveur, transformer les scénarios en configuration métier versionnée, persister le diagnostic après consentement, relier l'utilisateur à une organisation, ouvrir un workspace autorisé et tracer un handoff expert. Ce sprint n'implémente aucun de ces éléments côté serveur.

## Preview locale

Depuis ce dossier:

```powershell
python -m http.server 4175
```

Ouvrir `http://127.0.0.1:4175/`.

## Validation

```powershell
node qa/validate-journey.mjs
node qa/accessibility-validation.mjs
node qa/capture-journey.mjs
```

Les scripts utilisent Playwright et Chrome localement. Les résultats JSON sont conservés dans `qa/` et les captures dans `captures/`.
