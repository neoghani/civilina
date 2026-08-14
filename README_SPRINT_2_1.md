# CIVILINA.COM - Sprint 2.1 - Civilina Reasoning Core

## 1. Objectif

Le Sprint 2.1 transforme le parcours déterministe Sprint 2 en un moteur de raisonnement multi-domaine, explicable, prudent et testable. Il fonctionne localement, sans LLM, API, backend actif, base, authentification, upload ou NDA réel. Laravel reste le backend cible officiel.

## 2. Architecture

Les responsabilités sont séparées comme suit:

- `js/civilina-ai-domains.js`: registres des domaines, libellés et identifiants de recommandations.
- `js/civilina-ai-rules.js`: évaluation pure des conditions et règles `ALL`, `ANY`, `NONE`.
- `js/civilina-ai-reasoning.js`: signaux, domaines, preuves, confiance, incertitudes, contradictions, classement et prochaine question.
- `js/civilina-ai-privacy.js`: intention de partage, objet sensible, People signal et état de session nettoyé.
- `js/civilina-ai-scenarios.js`: questions et suggestions des quatre portes Sprint 2.
- `js/civilina-ai-journey.js`: orchestration, état conversationnel et rendu DOM uniquement.

## 3. Laravel target architecture

```text
Civilina AI
-> Laravel Application / API Layer
-> Civilina Reasoning Core
-> Domain Services
-> Organizations / Users / Projects / Processes / Documents
-> Eloquent / Database
-> Queues / Events / Notifications / Audit
```

Le navigateur reste un environnement de validation. Il n'est pas l'autorité métier finale.

## 4. Domain Signal Engine

Une réponse peut renforcer plusieurs domaines: Projects, Project Controls, Organisation, ISO, Quality, Digitalisation, Engineering, Procurement et People. `entryIntent`, `primaryDomain` et `relatedDomains` sont distincts. Les pondérations sont centralisées et ne sont jamais affichées au visiteur.

## 5. ALL / ANY / NONE Rule Engine

`ALL` exige toutes les conditions, `ANY` au moins une condition lorsqu'il existe, et `NONE` bloque la règle si une condition correspond. Les opérateurs utiles sont `equals`, `notEquals`, `oneOf`, `includes`, `containsText`, `exists`, `greaterThan` et `lessThan`.

## 6. Evidence Engine

Chaque recommandation visible contient au moins une preuve structurée avec source, poids et raison utilisateur. Le contexte EPC seul n'est jamais une preuve suffisante pour Engineering & Documents, Procurement ou QA/QC.

## 7. Confidence / Uncertainty

La confiance interne sert au choix du domaine principal et des sujets associés. Aucun pourcentage n'est affiché. Une demande insuffisamment qualifiée produit une incertitude et une question de clarification plutôt qu'un catalogue de recommandations.

## 8. Next Best Question

Le moteur choisit une question non répondue ayant un effet sur la qualification. Une demande ambiguë déclenche une clarification du domaine. Un retard projet transverse peut déclencher une question sur sa cause. La première restitution demande normalement quatre questions, cinq lorsqu'une clarification utile est nécessaire.

## 9. Multi-domain reasoning

ISO + responsabilités floues conserve ISO comme besoin principal et Organisation comme sujet associé. Digitalisation + validations floues conserve Digitalisation et Organisation. Projects + approvisionnements critiques conserve Projects et Procurement. Un sujet associé n'est pas automatiquement une recommandation.

## 10. Recommendation ranking

La force interne est `high`, `medium` ou `low`. La restitution limite les priorités immédiates à trois et les pistes à explorer à deux. Une preuve directe ou plusieurs preuves convergentes déterminent le classement; les signaux faibles restent exploratoires ou invisibles.

## 11. Contradiction handling

Le moteur signale avec prudence les situations telles que « processus structurés » et « responsabilités de validation peu définies ». Le message invite à clarifier le point sans accuser l'utilisateur de contradiction.

## 12. Privacy detection

Le mode sensible exige simultanément une intention de partage et un objet sensible. « Contrôler mon budget » n'est pas sensible; « envoyer notre budget » l'est. Les 12 formulations obligatoires sont couvertes par `qa/privacy-validation.mjs`.

## 13. Storage privacy

Le texte libre n'est jamais persisté en clair. `sessionStorage` conserve uniquement des réponses structurées, des faits dérivés et des placeholders. Le diagnostic n'utilise pas `localStorage`, n'écrit pas de réponse dans l'URL, la console, un attribut `data-*` ou un `mailto`. La sentinelle est testée dans `qa/storage-validation.mjs`.

## 14. People / MBTI guardrail

Une décision portant sur MBTI, personnalité, candidat, affectation ou équipe active le garde-fou People. Aucune personne ou fonction n'est recommandée. Le message rappelle les compétences, l'expérience, les responsabilités, la performance, la disponibilité et les exigences du poste.

## 15. Expert handoff

L'expert est suggéré pour une interaction multi-domaine forte, une contradiction importante, une décision humaine sensible ou une incertitude transverse. Un retard planning simple ne suffit pas. Le `mailto` contient uniquement des catégories et recommandations dérivées.

## 16. QA

```powershell
node --preserve-symlinks --preserve-symlinks-main qa/reasoning-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa/privacy-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa/validate-journey.mjs
node --preserve-symlinks --preserve-symlinks-main qa/storage-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa/accessibility-validation.mjs
node --preserve-symlinks --preserve-symlinks-main qa/capture-journey.mjs
```

Résultats: 40/40 tests métier, 12/12 tests privacy, 4/4 parcours, sentinelle sans fuite, accessibilité validée et 45 captures sans débordement ni erreur console.

## 17. Limitations

- Compréhension du langage fondée sur des règles et catégories françaises.
- Aucune persistance serveur ni historique utilisateur.
- Aucun LLM, apprentissage automatique ou analyse de document.
- Connexion et espace confidentiel seulement présentés comme étapes futures.
- Le prototype ne remplace pas une analyse métier ou RH professionnelle.

## 18. Future Laravel integration

Correspondance cible:

```text
civilina-ai-reasoning.js -> app/Domain/CivilinaAI/Reasoning/ReasoningEngine.php
civilina-ai-rules.js     -> app/Domain/CivilinaAI/Reasoning/RuleEngine.php
civilina-ai-privacy.js   -> app/Domain/CivilinaAI/Privacy/PrivacyClassifier.php
civilina-ai-domains.js   -> app/Domain/CivilinaAI/Domain/DomainRegistry.php
Journey state            -> Laravel Session / Diagnostic aggregate
```

Laravel prendra en charge utilisateurs, organisations, permissions, sessions authentifiées, diagnostics persistés, versions de règles, audit trail, confidentialité, workspaces, documents, handoff expert, workflows, notifications et données Project/WBS/Process.

## 19. Future Civilina AI / LLM integration

```text
Natural Language -> Civilina AI / LLM -> Structured Intent -> Laravel
-> Civilina Reasoning Core -> Domain Data -> Recommendation / Action
-> Civilina AI Response
```

Le futur LLM comprendra le langage; le Reasoning Core Laravel encadrera les décisions et conservera la traçabilité.

## Preview locale

```powershell
python -m http.server 4175
```

Ouvrir `http://127.0.0.1:4175/`. Aucun déploiement n'a été réalisé.
