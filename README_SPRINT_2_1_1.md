# CIVILINA.COM - Sprint 2.1.1 - Reasoning Hardening

## Objectif

Durcir le prototype Civilina AI avant sa future migration Laravel, sans refaire la homepage, le branding ou l’architecture front. Le sprint sépare les besoins de l’entreprise des expertises mobilisées, centralise les règles et recommandations, renforce la confidentialité B2B et rend la prochaine question dépendante de son impact décisionnel.

## Version

- Date : 2026-08-14
- Branche : `sprint-2.1.1-reasoning-hardening`
- Commit : `feat: harden civilina reasoning core for production readiness`
- Parent : `1e4b842511d9cf4b1a24e2a07116d0bd2766fb89`
- Déploiement : aucun

## Autorités métier

- `js/civilina-ai-domains.js` : Business Domains et Capabilities.
- `js/civilina-ai-rules.js` : moteur pur `all`, `any`, `none`.
- `js/civilina-ai-recommendations.js` : preuves, règles, classement et limites.
- `js/civilina-ai-privacy.js` : confidentialité B2B, People Guardrail et stockage nettoyé.
- `js/civilina-ai-reasoning.js` : faits, signaux, ambiguïtés, contradictions et Next Best Question.
- `js/civilina-ai-scenarios.js` : questions uniquement.
- `js/civilina-ai-journey.js` : état et rendu DOM uniquement.

## Taxonomies

Business Domains :

- Projects
- Organisation
- Management Systems
- Digitalisation
- People

Capabilities :

- Project Controls
- Engineering
- Quality
- Procurement
- Data

Une capacité ne peut pas devenir un domaine principal ou associé.

## Garanties

- Maximum 3 recommandations immédiates et 2 à explorer.
- Toute recommandation possède une preuve et une justification.
- Les scénarios ne contiennent aucune recommandation ou priorité.
- `manager un projet` ne déclenche pas People.
- `profil de risque` ne déclenche pas People.
- Une décision candidat/affectation active le People Guardrail et bloque le ranking.
- La confidentialité exige une intention de transmettre et un objet sensible.
- Le texte libre brut n’est jamais conservé dans `sessionStorage`.

## QA

Le rapport consolidé est `qa/qa-summary.json`.

- Raisonnement adversarial : 80/80.
- Privacy et stockage unitaire : 53/53.
- Parcours navigateur : 7/7.
- Canaux de fuite navigateur : 8/8.
- Accessibilité : 11/11.
- Responsive : 45/45.
- Total : 204/204.
- Console : 0 erreur.
- Débordement horizontal ou contenu vertical masqué : 0.

Largeurs validées : 1440, 1024, 768, 430 et 390 px.

## Captures

Le dossier `captures/` contient 45 fichiers selon la convention :

`{largeur}-{etat}.png`

États : `conversation-longue`, `clarification`, `confirmation`, `multi-domain`, `recommendations`, `contradiction`, `privacy-alert`, `people-guardrail`, `expert-suggestion`.

## Preview locale

Depuis le dossier livré :

```powershell
python -m http.server 4173
```

Ouvrir ensuite `http://127.0.0.1:4173/`.

## Rejouer les validations

Les tests purs demandent uniquement Node.js :

```powershell
node --preserve-symlinks --preserve-symlinks-main qa\reasoning-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\privacy-validation.mjs
```

Les tests navigateur demandent Playwright et Google Chrome :

```powershell
node --preserve-symlinks --preserve-symlinks-main qa\validate-journey.mjs
node --preserve-symlinks --preserve-symlinks-main qa\storage-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\accessibility-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\capture-journey.mjs
node --preserve-symlinks --preserve-symlinks-main qa\build-qa-summary.mjs
```

## Laravel cible

`LARAVEL_REASONING_TARGET.md` prépare les futurs services :

- `ReasoningEngine.php`
- `RuleEngine.php`
- `PrivacyClassifier.php`
- `DomainRegistry.php`
- `RecommendationEngine.php`

La migration PHP n’est volontairement pas réalisée dans ce sprint.

## Non implémenté volontairement

- Classes PHP et API Laravel.
- Base de données et migrations.
- Authentification et persistance serveur.
- LLM ou service AI distant.
- Upload, workspace confidentiel ou NDA réel.
- Décision RH automatisée.
- Déploiement.
