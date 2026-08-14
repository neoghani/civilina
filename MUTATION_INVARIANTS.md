# Mutation Invariants

Les sentinelles de `qa/external-adversarial-validation.mjs` rendent visibles quatre régressions critiques sans ajouter de framework de mutation testing.

## Evidence removed

Mutation simulée : supprimer les preuves alors que les faits correspondent à une règle.

Résultat obligatoire : aucune recommandation `immediate` ou `explore`.

## People guardrail bypassed

Mutation simulée : appeler le ranking avec `peopleGuardrail = false` sur un cas mixte décision humaine + coûts/délais.

Résultat attendu du test : le ranking muté produirait une recommandation alors que le moteur réel n’en produit aucune. La différence garantit que le test détecte le bypass.

## Immediate limit changed

Mutation simulée : injecter simultanément des preuves Cost, Planning, Reporting, Engineering, Procurement et Quality.

Résultat obligatoire : maximum 3 `immediate` et 2 `explore`.

## Second authority introduced

Mutation simulée : ajouter recommandations, priorités ou règles de handoff dans `civilina-ai-scenarios.js`.

Résultat obligatoire : échec de la sentinelle Single Source of Truth.
