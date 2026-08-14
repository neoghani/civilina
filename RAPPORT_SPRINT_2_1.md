# SPRINT 2.1 - CIVILINA REASONING CORE RESULT

## Architecture

Le moteur métier est séparé en Domain Registry, Rule Engine, Reasoning Engine et Privacy Classifier. Le Journey orchestre l'état et le DOM sans décider du domaine, des preuves, de la sensibilité ou du garde-fou People. Laravel reste explicitement le backend cible.

## Multi-domain

Le scénario unique Sprint 2 est remplacé par `entryIntent`, `primaryDomain` et `relatedDomains`. ISO + responsabilités floues conserve ISO et Organisation. ISO + retards conserve Projects avec clarification. Digitalisation + validations floues conserve Organisation. Procurement exige un signal d'approvisionnement.

## Rule Engine

`ALL`, `ANY` et `NONE` possèdent une sémantique explicite et six tests dédiés. La règle Engineering & Documents exige un contexte EPC et un besoin documentaire, tout en étant bloquée par un sujet coût seul.

## Evidence Engine

Chaque recommandation visible possède au moins une preuve et une justification courte. Le contexte sectoriel n'est jamais utilisé seul comme preuve de besoin.

## Recommendation Quality

Maximum trois priorités immédiates et deux pistes à explorer. EPC seul ne déclenche ni Engineering & Documents, ni Procurement, ni QA/QC. Cost seul ne déclenche pas QA/QC; retard planning seul ne déclenche pas Procurement.

## Confidence & Uncertainty

Les scores restent internes. « Nous avons du mal à piloter » demande le sujet principal. Un retard transverse peut demander si sa cause vient du planning, de l'approvisionnement, des deux ou reste inconnue.

## Contradictions

Le couple « processus structurés » / « validations peu claires » est détecté et présenté comme un point à clarifier. La correction d'une réponse recalcule entièrement la lecture et supprime les anciennes recommandations devenues sans preuve.

## Privacy

Six cas négatifs et six cas positifs passent. Le test `CIVILINA_SECRET_TEST_92841` confirme l'absence de fuite dans sessionStorage, localStorage, URL, console, mailto et datasets DOM. Aucun upload n'existe.

## People / MBTI

La demande d'affectation basée sur les MBTI active le garde-fou People, place People devant le mot « chantier » et ne recommande aucune personne.

## Expert Handoff

L'expert est réservé aux interactions multi-domaines fortes, contradictions, incertitudes transverses et décisions humaines sensibles. Le résumé e-mail est catégoriel et ne reprend aucun texte libre.

## QA

- Reasoning: 40/40.
- Privacy: 12/12.
- Parcours navigateur: 4/4 + ambiguïté + multi-domaine.
- Storage sentinel: 8/8 canaux/règles validés.
- Console et erreurs page: 0.

## Responsive

Neuf nouveaux états contrôlés à 1440, 1024, 768, 430 et 390 px, soit 45 captures. Aucun débordement horizontal.

## Accessibility

Un H1, progressbar, deux régions live, labels, menu Escape, focus Modifier, reduced motion, clarification, contradiction et People Guardrail validés.

## Future Laravel Integration

Les quatre modules JavaScript correspondent aux futurs services `ReasoningEngine.php`, `RuleEngine.php`, `PrivacyClassifier.php` et `DomainRegistry.php`. Aucune classe PHP, API ou infrastructure serveur n'a été créée.

## Git

Branche: `sprint-2.1-reasoning-core`. Commit final: `feat: build multi-domain civilina reasoning core`.

## Livraison

Le dossier indépendant et le ZIP sont produits après le commit, avec les fichiers fonctionnels, QA, captures, README, rapport et informations Git, sans secret ni dépendance de test.

## Limites restantes

Le moteur comprend des catégories françaises déterministes, sans LLM ni données métier serveur. L'authentification, la persistance, les workspaces, les documents, le NDA et le handoff suivi restent à intégrer dans le futur Civilina Core Laravel.
