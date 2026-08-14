# SPRINT 2.1.1 - REASONING HARDENING RESULT

## UX

Le parcours conserve la homepage et le branding existants. La prochaine question est choisie par impact décisionnel. Les résultats distinguent clairement le besoin principal, les sujets associés et les capacités mobilisées.

Score d’acceptation interne : 9.4/10.

## Architecture

Les taxonomies Business Domains et Capabilities sont strictement séparées. Les scénarios ne portent plus que les questions. Le registre de recommandations, les preuves et le ranking sont centralisés dans `civilina-ai-recommendations.js`.

Score d’acceptation interne : 9.6/10.

## Privacy

La détection couvre contrats, devis, factures, paie, salaires, marges, trésorerie, clients, fournisseurs, prix, offres, budgets, données financières, RH et documents internes. Le mode sensible exige toujours une intention de transmettre et un objet sensible. La sentinelle de test ne fuit dans aucun des huit canaux navigateur contrôlés.

Score d’acceptation interne : 9.7/10.

## Reasoning

Le ranking est limité à 3 recommandations immédiates et 2 à explorer. Aucune recommandation n’est visible sans preuve et justification. Les tests négatifs empêchent notamment EPC seul de produire Engineering, Procurement ou QA/QC. La correction d’une réponse supprime les recommandations devenues sans preuve.

Score d’acceptation interne : 9.5/10.

## People

`manager un projet` et `profil de risque` restent dans Projects. Les décisions concernant candidats, recrutement, affectation ou promotion activent le People Guardrail. Une discussion MBTI sans décision reste autorisée comme contexte humain; aucune décision RH n’est produite.

## Multi-domaines

Les cinq domaines et cinq capacités ne se chevauchent pas. Les couplages ISO/Organisation, ISO/Projects, Digitalisation/Organisation, Digitalisation/Management Systems et Organisation/Digitalisation sont validés sans transformer les capacités en domaines.

Score d’acceptation interne : 9.5/10.

## Responsive

45 captures ont été générées : neuf états à 1440, 1024, 768, 430 et 390 px. Les scripts vérifient le débordement horizontal, le masquage vertical du résultat, les erreurs console et les erreurs page.

Score d’acceptation interne : 9.6/10.

## QA

- 80/80 tests adversariaux de raisonnement.
- 53/53 tests Privacy, People contextuel et stockage unitaire.
- 7/7 contrôles de parcours navigateur.
- 8/8 canaux de fuite navigateur.
- 11/11 contrôles d’accessibilité.
- 45/45 états responsive.
- Total : 204/204, 0 échec.
- Console : 0 erreur.

Rapport principal : `qa/qa-summary.json`.

## Laravel

`LARAVEL_REASONING_TARGET.md` définit le rôle, les frontières et le contrat des futurs `ReasoningEngine.php`, `RuleEngine.php`, `PrivacyClassifier.php`, `DomainRegistry.php` et `RecommendationEngine.php`. Aucun code PHP n’a été créé.

## Fichiers produit

Modifiés :

- `index.html`
- `js/civilina-ai-domains.js`
- `js/civilina-ai-journey.js`
- `js/civilina-ai-privacy.js`
- `js/civilina-ai-reasoning.js`
- `js/civilina-ai-scenarios.js`

Créé :

- `js/civilina-ai-recommendations.js`

## Fichiers QA et documentation

- `qa/reasoning-validation.mjs`
- `qa/reasoning-validation.json`
- `qa/privacy-validation.mjs`
- `qa/privacy-validation.json`
- `qa/validate-journey.mjs`
- `qa/journey-validation.json`
- `qa/storage-validation.mjs`
- `qa/storage-validation.json`
- `qa/accessibility-validation.mjs`
- `qa/accessibility-validation.json`
- `qa/capture-journey.mjs`
- `qa/responsive-validation.json`
- `qa/build-qa-summary.mjs`
- `qa/qa-summary.json`
- `LARAVEL_REASONING_TARGET.md`
- `README_SPRINT_2_1_1.md`
- `RAPPORT_SPRINT_2_1_1.md`
- `GIT_INFO.txt`
- 45 captures dans `captures/`.

## Git

- Branche : `sprint-2.1.1-reasoning-hardening`.
- Commit : `feat: harden civilina reasoning core for production readiness`.
- Parent : `1e4b842511d9cf4b1a24e2a07116d0bd2766fb89`.

## Limites volontaires

Pas de migration PHP, API, base, authentification, LLM, upload, workspace confidentiel, NDA réel ou déploiement dans ce sprint.
