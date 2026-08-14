# SPRINT 2.1.2 - REASONING CORE V1 FREEZE RESULT

## External review fixes

Les corrections portent sur les formulations People naturelles, la causalité des retards, la sémantique documentaire, le seuil d’affichage des capacités, la confidentialité B2B et la valeur décisionnelle de la prochaine question.

## People Guardrail

Les décisions de nomination, promotion, recrutement ou affectation activent le garde-fou même en contexte projet, EPC ou chantier. Les expressions métier sans décision humaine restent hors guardrail. L’interface propose de structurer les critères de décision sans choisir une personne.

## Delay causality

Un retard seul crée une incertitude bloquante. Une recommandation causale devient immédiate uniquement lorsque planning, procurement, ressources, engineering ou approbations sont explicitement étayés. Les cas inconnus, multiples et sous-traitants déclenchent une clarification adaptée.

## Document semantics

Les documents qualité et la GED alimentent Document Control. Les plans IFC, drawings et technical submittals alimentent Engineering & Documents sans créer automatiquement un besoin Document Control qualité.

## Capability gating

Une capacité est visible avec un signal fort, une preuve directe ou une recommandation qui la mobilise. Le contexte EPC seul n’affiche plus Engineering.

## Next Best Question V2

La sélection additionne réduction d’incertitude, discrimination entre recommandations, discrimination entre domaines et importance du risque, puis retranche la pénalité d’information déjà connue. Le parcours peut s’arrêter quand les preuves sont suffisantes.

## Golden Dataset

55 cas de référence, versionnés en `1.0.0`, couvrent domaines, capacités, recommandations, People, Privacy et incertitudes bloquantes.

## QA

354/354 validations réussies, dont 204/204 contrôles baseline conservés, 82/82 tests externes et 55/55 cas Golden. Zéro erreur console et zéro overflow. Dix captures ciblées ont été produites aux largeurs 1440, 1024, 768, 430 et 390 px, en complément des 45 captures baseline.

## Privacy

Le classifieur exige intention de transmettre + objet sensible. Il couvre business plans, forecasts, P&L, bilan, cash-flow, BPU, DQE, offres fournisseurs et données nominatives, avec prise en compte des intentions explicitement niées.

## Laravel Parity Contract

`LARAVEL_REASONING_TARGET.md` documente les cinq services cibles, leurs DTO, erreurs, responsabilités, invariants et absence d’effets de bord. Aucun code PHP n’est créé dans ce sprint.

## Scores ciblés

- UX : 9.5/10
- Architecture : 9.7/10
- Responsive : 9.6/10
- Privacy : 9.8/10
- Raisonnement métier : 9.7/10
- Multi-domaines : 9.6/10
- People Guardrail : 9.7/10

## Git

- Branche : `sprint-2.1.2-reasoning-core-freeze`
- Commit attendu : `feat: finalize civilina reasoning core v1 hardening`
- Baseline : `9fd5ae65857fd2d06ca4cfbb3783ad9b461a849b`

## Livraison

Le dossier et le ZIP de livraison contiennent le prototype, les sources JS, la QA JSON, le Golden Dataset, les captures impactées, les documents Sprint et les informations Git. Aucun secret, `.env`, cache, dépendance lourde ou ancien projet n’y est inclus.
