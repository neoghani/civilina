# Laravel Reasoning Target - Reasoning Core V1

## Statut

Ce document constitue le contrat de parité du Reasoning Core V1 pour sa future migration vers Laravel. Le Sprint 2.1.2 ne crée aucun fichier PHP, aucune API, aucune migration et aucune nouvelle authentification.

Version de référence : `1.0.0`.

Dataset de parité : `qa/golden-reasoning-cases.json`.

## Namespace cible

`App\Domain\CivilinaAI`

## Services cibles

### `DomainRegistry.php`

Autorité unique pour les taxonomies.

- Business Domains : `projects`, `organisation`, `managementSystems`, `digitalisation`, `people`.
- Capabilities : `projectControls`, `engineering`, `quality`, `procurement`, `data`.
- Les domaines décrivent le besoin de l’entreprise.
- Les capacités décrivent les expertises mobilisées pour y répondre.
- Une capacité ne peut jamais devenir un domaine principal ou associé.

Source prototype : `js/civilina-ai-domains.js`.

DTO d’entrée : aucun. Le registre est immuable.

DTO de sortie : `BusinessDomainDefinition[]` ou `CapabilityDefinition[]`, chaque définition contenant `id` et `label`.

Erreurs : `UnknownBusinessDomain`, `UnknownCapability`.

### `RuleEngine.php`

Moteur pur de conditions `all`, `any` et `none`.

- Aucun libellé UI.
- Aucun accès base de données.
- Aucun effet de bord.
- Entrée : faits normalisés et règle déclarative.
- Sortie : booléen de correspondance.

Source prototype : `js/civilina-ai-rules.js`.

DTO d’entrée : `RuleEvaluationInput { facts: string[], context: array, rule: RuleDefinition }`.

DTO de sortie : `RuleMatch { ruleId: string, matched: bool }`.

Erreurs : `InvalidRuleDefinition`, `UnsupportedRuleOperator`.

### `PrivacyClassifier.php`

Classe les intentions avant toute persistance ou transmission.

- Une information B2B est sensible uniquement si le texte contient une intention de transmettre et un objet sensible.
- Objets couverts : contrats, devis, factures, paie, salaires, marges, trésorerie, clients, fournisseurs, prix, offres, budgets, business plans, prévisions, P&L, compte de résultat, bilan, cash-flow, BPU, DQE, offres/devis fournisseurs, données financières, données nominatives, RH et documents internes.
- Distingue une décision concernant une personne d’un contexte projet.
- `manager un projet` et `profil de risque` ne déclenchent pas le guardrail People.
- Le texte libre brut n’entre jamais dans l’état persistant.

Source prototype : `js/civilina-ai-privacy.js`.

DTO d’entrée : `PrivacyClassificationInput { freeText: ?string }`.

DTO de sortie : `PrivacyClassification { sensitiveIntent: bool, peopleSignal: bool, peopleDecision: bool, safeStorageValue: ?string }`.

Erreurs : aucune erreur métier pour une entrée vide ; `InvalidTextEncoding` si la normalisation est impossible.

### `RecommendationEngine.php`

Autorité unique pour les recommandations métier.

- Registre des recommandations, règles, preuves et justifications.
- Toute recommandation doit avoir au moins une preuve normalisée et une justification.
- Maximum trois recommandations `immediate` et deux `explore`.
- Déduplication des familles fonctionnelles.
- Aucune recommandation automatique quand le People Guardrail est actif.

Source prototype : `js/civilina-ai-recommendations.js`.

DTO d’entrée : `RecommendationInput { facts: string[], evidence: Evidence[], uncertainties: Uncertainty[], peopleGuardrail: bool }`.

DTO de sortie : `RecommendationRanking { immediate: Recommendation[], explore: Recommendation[] }`.

Chaque `Recommendation` expose `id`, `label`, `businessDomain`, `capability`, `strength`, `score` et au moins une `Evidence { source, reason }`.

Erreurs : `RecommendationWithoutEvidence`, `UnknownRecommendation`, `RankingInvariantViolation`.

### `ReasoningEngine.php`

Orchestrateur stateless.

1. Normaliser l’entrée.
2. Dériver les faits.
3. Évaluer séparément domaines et capacités.
4. Détecter ambiguïtés et contradictions.
5. Classer confidentialité et décision humaine.
6. Appeler `RecommendationEngine`.
7. Choisir la question au plus fort impact décisionnel.
8. Retourner un résultat explicable.

Source prototype : `js/civilina-ai-reasoning.js`.

DTO d’entrée : `ReasoningInput` défini ci-dessous.

DTO de sortie : `ReasoningResult` défini ci-dessous.

Erreurs : `InvalidEntryIntent`, `InvalidAnswerToken`, `UnknownSeedFact`, `ReasoningInvariantViolation`.

La question suivante utilise :

`uncertaintyReduction + recommendationDiscrimination + domainDiscrimination + riskImportance - alreadyKnownPenalty`.

Le moteur peut s’arrêter avant cinq questions si le domaine principal est clair, qu’aucune incertitude bloquante ou contradiction majeure ne subsiste et que les recommandations sont suffisamment justifiées.

## Contrat d’entrée cible - `ReasoningInput`

```php
[
    'entry_intent' => ?string,
    'answers' => array,
    'free_text' => ?string,
    'seed_facts' => array,
]
```

## Contrat de sortie cible - `ReasoningResult`

```php
[
    'reasoning_core_version' => '1.0.0',
    'entry_intent' => ?string,
    'primary_domain' => ?string,
    'related_domains' => array,
    'capabilities' => array,
    'facts' => array,
    'evidence' => array,
    'uncertainties' => array,
    'contradictions' => array,
    'recommendations' => ['immediate' => array, 'explore' => array],
    'sensitive_intent' => bool,
    'people_guardrail' => bool,
    'expert_suggested' => bool,
]
```

## Sémantique documentaire

- `contrôle documentaire`, `maîtrise documentaire`, `gestion documentaire`, `versions documentaires`, `documents qualité`, `documentation qualité`, `GED` et `document control` alimentent Management Systems, Quality et `Document Control`.
- `plans IFC`, `drawings`, `technical submittals`, `documents techniques` et `dossiers techniques` alimentent Projects, Engineering et `Engineering & Documents`.
- Un document technique ne doit pas produire `Document Control` qualité sans signal qualité explicite.

## Taxonomie sous-traitance

- `sous-traitant` et `sous-traitance` ne sont ni un Business Domain ni une Capability.
- Ils restent un contexte Projects.
- Procurement, Project Controls ou une lecture Delivery ne sont mobilisés que lorsqu’une preuve précise le type de blocage.
- Le simple fait de citer un sous-traitant produit une incertitude sur la nature du retard, pas une recommandation causale.

## Seuil d’affichage des capacités

Une Capability est affichée si au moins une condition est vraie :

1. son signal normalisé est fort (`>= 2`) ;
2. une preuve directe soutient une recommandation qui lui appartient ;
3. une recommandation retenue l’utilise.

Le contexte EPC seul ne suffit donc pas à afficher Engineering.

## Effets de bord

Les cinq services sont `stateless`, déterministes et sans effet de bord :

- aucun accès base de données ;
- aucun accès session ;
- aucun appel réseau ;
- aucun envoi d’événement ;
- aucun stockage de texte libre ;
- aucun usage de l’horloge dans les décisions ;
- aucune mutation des DTO d’entrée.

La journalisation, la persistance et le transport HTTP appartiendront à l’application Laravel, hors de ces services.

## Parité obligatoire

La future suite PHPUnit doit charger les 55 cas de `qa/golden-reasoning-cases.json` et comparer exactement :

- `entryIntent` ;
- `primaryDomain` et `relatedDomains` ;
- `capabilities` ;
- recommandations `immediate` et `explore` ;
- `peopleGuardrail` ;
- `sensitiveIntent` ;
- `expertSuggested` ;
- `uncertainties` et `blockingUncertainty`.

Tout écart nécessite une décision de version ; il ne doit pas être masqué par une adaptation silencieuse du dataset.

## Frontières

- `civilina-ai-scenarios.js` reste un catalogue de questions uniquement.
- Les contrôleurs Laravel futurs valident et transportent les données; ils ne contiennent aucune règle métier.
- La session Laravel future ne stocke pas le texte libre brut sans consentement explicite et politique de conservation définie.
- Les scores de confiance restent internes et ne doivent pas être présentés comme probabilités scientifiques.
- Le MBTI ne produit jamais une décision de recrutement, promotion ou affectation.

## Ordre de migration futur

1. Créer des tests PHPUnit à parité avec les rapports QA JSON.
2. Implémenter `DomainRegistry` et `RuleEngine`.
3. Implémenter `PrivacyClassifier` et ses tests négatifs.
4. Implémenter `RecommendationEngine`.
5. Implémenter `ReasoningEngine` et comparer les snapshots JS/PHP.
6. Exposer une route Laravel protégée seulement après parité complète.
