# CIVILINA.COM - Sprint 2.1.2 - Reasoning Core V1 Freeze

## Objectif

Finaliser le durcissement adversarial du prototype Civilina AI et figer un contrat de parité V1 avant sa future migration vers Laravel. Aucun backend, aucune API, aucune base de données et aucun déploiement ne sont ajoutés.

## Version

- Date : 2026-08-14
- Reasoning Core : `1.0.0`
- Branche : `sprint-2.1.2-reasoning-core-freeze`
- Commit : `feat: finalize civilina reasoning core v1 hardening`
- Baseline : `9fd5ae65857fd2d06ca4cfbb3783ad9b461a849b`
- Déploiement : aucun

## Durcissements livrés

- People Guardrail fondé sur sujet humain + verbe de décision + rôle ou responsabilité.
- Normalisation Unicode NFD et suppression des diacritiques avant classification.
- Retard isolé traité comme signal incertain, sans recommandation causale automatique.
- Causes adaptatives : planning, procurement, ressources, engineering, approbations, sous-traitance, causes multiples et cause inconnue.
- Documents qualité séparés des documents techniques IFC/drawings/submittals.
- Sous-traitance conservée comme contexte Projects, jamais comme domaine ou capacité.
- Affichage d’une Capability soumis à un signal fort, une preuve directe ou une recommandation associée.
- Next Best Question V2 fondée sur la valeur décisionnelle.
- Privacy B2B étendue aux business plans, forecasts, P&L, bilan, cash-flow, BPU, DQE, offres fournisseurs et données nominatives.
- Ranking limité à 3 recommandations immédiates et 2 pistes, toutes avec preuve et justification.

## Autorités

- `js/civilina-ai-domains.js` : Business Domains et Capabilities.
- `js/civilina-ai-rules.js` : moteur pur `all`, `any`, `none`.
- `js/civilina-ai-recommendations.js` : preuves, règles, confidence gate et ranking.
- `js/civilina-ai-privacy.js` : normalisation, Privacy B2B, People Guardrail et stockage nettoyé.
- `js/civilina-ai-reasoning.js` : faits, signaux, incertitudes, capability gate et NBQ V2.
- `js/civilina-ai-scenarios.js` : questions uniquement.
- `js/civilina-ai-journey.js` : état et rendu DOM uniquement.

## QA

Rapport consolidé : `qa/qa-summary.json`.

- QA totale : 354/354.
- Raisonnement baseline : 80/80.
- Privacy baseline : 53/53.
- Adversarial externe : 82/82.
- Golden parity : 55/55.
- Parcours navigateur : 8/8.
- Stockage navigateur : 8/8.
- Accessibilité : 13/13.
- Responsive baseline : 45/45.
- Responsive impacté : 10/10.
- Console : 0 erreur.
- Overflow : 0.

Largeurs validées : 1440, 1024, 768, 430 et 390 px.

## Golden Dataset

`qa/golden-reasoning-cases.json` contient 55 cas représentatifs. Il constitue le contrat de parité du Reasoning Core V1 pour les futurs tests PHPUnit.

Rejouer sa validation :

```powershell
node --preserve-symlinks --preserve-symlinks-main qa\golden-parity-validation.mjs
```

## Preview locale

Depuis le dossier livré :

```powershell
python -m http.server 4173
```

Ouvrir `http://127.0.0.1:4173/`.

## Rejouer toute la QA

```powershell
node --preserve-symlinks --preserve-symlinks-main qa\reasoning-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\privacy-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\external-adversarial-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\golden-parity-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\validate-journey.mjs
node --preserve-symlinks --preserve-symlinks-main qa\storage-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\accessibility-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa\capture-freeze-impact.mjs
node --preserve-symlinks --preserve-symlinks-main qa\build-qa-summary.mjs
```

Les validations navigateur nécessitent Playwright et Google Chrome.

## Non implémenté volontairement

- Migration PHP du Reasoning Core.
- API Laravel, contrôleurs, base de données ou migrations.
- LLM ou service AI distant.
- Authentification et persistance serveur.
- Upload ou espace NDA réel.
- Décision RH automatisée.
- Déploiement.
