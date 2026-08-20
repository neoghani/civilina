(function (root, factory) {
    'use strict';

    const isCommonJs =
        typeof module === 'object' &&
        module.exports;

    const api = factory(
        isCommonJs
            ? require('./civilina-ai-recognizer.js')
            : root.CivilinaAIRecognizer
    );

    if (isCommonJs) {
        module.exports = api;
    } else {
        root.CivilinaAIArtifactRequirements = api;
    }

})(typeof globalThis !== 'undefined' ? globalThis : this, function (
    recognizer
) {
    'use strict';

    /*
     * CIVILINA Artifact Requirements Engine
     *
     * Purpose:
     * Determine what information is still required before Civilina
     * can safely and usefully create a requested business artifact.
     *
     * This layer does NOT create the artifact.
     * It establishes readiness and the next best requirement.
     */

    const REQUIREMENT_REGISTRY = Object.freeze({

        itp: Object.freeze([
            {
                id: 'ITP_ACTIVITY_SCOPE_REQUIRED',
                question:
                    'Pour quelle activité, quel ouvrage ou quelle zone faut-il préparer cet ITP ?',
                answerKey: 'itpActivityScope',
                signals: [
                    'activity',
                    'activite',
                    'betonnage',
                    'coffrage',
                    'ferraillage',
                    'excavation',
                    'ancrage',
                    'ancrages',
                    'welding',
                    'soudage',
                    'ouvrage',
                    'zone'
                ],
                importance: 'blocking'
            },
            {
                id: 'ITP_REFERENCE_DOCUMENTS_REQUIRED',
                question:
                    'Quels plans, spécifications, procédures ou exigences contractuelles doivent servir de référence ?',
                answerKey: 'itpReferences',
                signals: [
                    'specification',
                    'specifications',
                    'procedure',
                    'procedures',
                    'drawing',
                    'drawings',
                    'plan ifc',
                    'ifc',
                    'method statement',
                    'norme',
                    'standard'
                ],
                importance: 'blocking'
            },
            {
                id: 'ITP_INSPECTION_TESTS_REQUIRED',
                question:
                    'Quelles inspections et quels essais doivent être prévus pour cette activité ?',
                answerKey: 'itpInspectionsTests',
                signals: [
                    'inspection',
                    'inspections',
                    'essai',
                    'essais',
                    'test',
                    'tests',
                    'controle',
                    'controles'
                ],
                importance: 'blocking'
            },
            {
                id: 'ITP_ACCEPTANCE_CRITERIA_REQUIRED',
                question:
                    'Quels sont les critères d’acceptation applicables à chaque contrôle ou essai ?',
                answerKey: 'itpAcceptanceCriteria',
                signals: [
                    'critere acceptation',
                    'criteres acceptation',
                    'acceptance criteria',
                    'tolerance',
                    'tolerances'
                ],
                importance: 'blocking'
            },
            {
                id: 'ITP_HOLD_WITNESS_REQUIRED',
                question:
                    'Quels Hold Points, Witness Points ou points de surveillance doivent être définis ?',
                answerKey: 'itpHoldWitness',
                signals: [
                    'hold point',
                    'witness point',
                    'surveillance point'
                ],
                importance: 'important'
            },
            {
                id: 'ITP_RESPONSIBILITIES_REQUIRED',
                question:
                    'Qui exécute, inspecte, approuve et accepte les contrôles prévus dans l’ITP ?',
                answerKey: 'itpResponsibilities',
                signals: [
                    'responsable',
                    'responsabilites',
                    'inspecteur',
                    'client',
                    'consultant',
                    'qa qc'
                ],
                importance: 'important'
            }
        ]),

        wbs: Object.freeze([
            {
                id: 'WBS_SCOPE_BASIS_REQUIRED',
                question:
                    'Quel périmètre, contrat ou ensemble de livrables la WBS doit-elle représenter ?',
                answerKey: 'wbsScopeBasis',
                signals: [
                    'scope',
                    'perimetre',
                    'contrat',
                    'lot',
                    'livrable',
                    'livrables'
                ],
                importance: 'blocking'
            },
            {
                id: 'WBS_DECOMPOSITION_REQUIRED',
                question:
                    'Selon quelle logique souhaitez-vous décomposer le périmètre : ouvrages, disciplines, phases, lots ou combinaison de ces axes ?',
                answerKey: 'wbsDecomposition',
                signals: [
                    'ouvrage',
                    'ouvrages',
                    'discipline',
                    'disciplines',
                    'phase',
                    'phases',
                    'lot',
                    'lots'
                ],
                importance: 'blocking'
            },
            {
                id: 'WBS_ZONE_OUVRAGE_REQUIRED',
                question:
                    'Comment la WBS doit-elle être reliée aux zones et aux ouvrages physiques ?',
                answerKey: 'wbsZoneOuvrage',
                signals: [
                    'zone',
                    'zones',
                    'ouvrage',
                    'ouvrages'
                ],
                importance: 'important'
            },
            {
                id: 'WBS_CODIFICATION_REQUIRED',
                question:
                    'Existe-t-il une règle de codification ou une structure WBS imposée par le projet ou le client ?',
                answerKey: 'wbsCodification',
                signals: [
                    'code wbs',
                    'codification',
                    'coding',
                    'structure code'
                ],
                importance: 'important'
            },
            {
                id: 'WBS_INTEGRATION_REQUIRED',
                question:
                    'La WBS doit-elle servir de référence commune au planning, aux coûts, aux documents et à l’avancement ?',
                answerKey: 'wbsIntegration',
                signals: [
                    'planning',
                    'budget',
                    'cout',
                    'cost',
                    'document',
                    'avancement',
                    'progress'
                ],
                importance: 'important'
            }
        ]),

        riskRegister: Object.freeze([
            {
                id: 'RISK_SCOPE_REQUIRED',
                question:
                    'Quel est le périmètre du registre : entreprise, portefeuille, projet, lot, activité ou processus ?',
                answerKey: 'riskScope',
                signals: [],
                importance: 'blocking'
            },
            {
                id: 'RISK_CATEGORIES_REQUIRED',
                question:
                    'Quelles familles de risques doivent être couvertes : coût, délai, technique, HSE, qualité, achats, contrats ou organisation ?',
                answerKey: 'riskCategories',
                signals: [],
                importance: 'important'
            },
            {
                id: 'RISK_SCORING_REQUIRED',
                question:
                    'Quelle méthode d’évaluation souhaitez-vous utiliser pour la probabilité, l’impact et la criticité ?',
                answerKey: 'riskScoring',
                signals: [],
                importance: 'blocking'
            },
            {
                id: 'RISK_OWNERS_REQUIRED',
                question:
                    'Pour chaque risque, indiquez le responsable en utilisant son identifiant, par exemple : R-001 : Responsable achats.',
                answerKey: 'riskOwners',
                signals: [],
                importance: 'important'
            },
            {
                id: 'RISK_ITEMS_REQUIRED',
                question:
                    'Quels risques sont explicitement connus à ce stade ? Utilisez une ligne par risque au format : R-001 | risque: ... | cause: ... | conséquence: ... | probabilité: ... | impact: ... . Si une information est inconnue, indiquez « inconnue » : Civilina ne l’invente pas.',
                answerKey: 'riskItems',
                signals: [],
                importance: 'blocking'
            },
            {
                id: 'RISK_TREATMENT_REQUIRED',
                question:
                    'Pour chaque risque, indiquez la réponse ou action de traitement en utilisant son identifiant au format : R-001 | réponse: ... | échéance: ... | statut: ... .',
                answerKey: 'riskTreatment',
                signals: [],
                importance: 'important'
            }
        ]),

        raci: Object.freeze([
    {
        id:
            'RACI_PROCESS_SCOPE_REQUIRED',

        question:
            'Sur quel processus, projet ou périmètre faut-il construire la matrice RACI ?',

        answerKey:
            'raciProcessScope',

        signals:
            [],

        importance:
            'blocking'
    },
    {
        id:
            'RACI_ACTIVITIES_REQUIRED',

        question:
            'Quelles activités ou décisions principales doivent apparaître dans la matrice ? Utilisez si possible un identifiant stable pour chaque activité, par exemple ACT-001.',

        answerKey:
            'raciActivities',

        signals:
            [],

        importance:
            'blocking'
    },
    {
        id:
            'RACI_ROLES_REQUIRED',

        question:
            'Quels rôles, fonctions ou acteurs doivent être positionnés dans le RACI ?',

        answerKey:
            'raciRoles',

        signals:
            [],

        importance:
            'blocking'
    },
    {
        id:
            'RACI_AUTHORITY_REQUIRED',

        question:
            'Existe-t-il des règles de délégation d’autorité, des seuils d’approbation ou des contraintes de gouvernance à respecter ? S’il n’y en a pas, indiquez-le explicitement.',

        answerKey:
            'raciAuthority',

        signals:
            [],

        importance:
            'important'
    },
    {
        id:
            'RACI_ASSIGNMENTS_REQUIRED',

        question:
            'Indiquez explicitement les affectations RACI pour chaque activité, une ligne par activité, au format : ACT-001 | activité: ... | R: ... | A: ... | C: ... | I: ... . Si une affectation est inconnue, indiquez « inconnue » : Civilina ne l’invente pas.',

        answerKey:
            'raciAssignments',

        signals:
            [],

        importance:
            'blocking'
    }
]),

        dashboard: Object.freeze([
            {
                id: 'DASHBOARD_DECISION_REQUIRED',
                question:
                    'Quelles décisions ce dashboard doit-il permettre de prendre ou de préparer ?',
                answerKey: 'dashboardDecision',
                signals: [
                    'decision',
                    'decisions',
                    'arbitrage',
                    'arbitrer',
                    'attention direction'
                ],
                importance: 'blocking'
            },
            {
                id: 'DASHBOARD_AUDIENCE_REQUIRED',
                question:
                    'À qui le dashboard est-il destiné : DG, direction de projet, PMO, chantier ou autre fonction ?',
                answerKey: 'dashboardAudience',
                signals: [
                    'dg',
                    'ceo',
                    'direction',
                    'project manager',
                    'chef de projet',
                    'pmo',
                    'chantier'
                ],
                importance: 'blocking'
            },
            {
                id: 'DASHBOARD_KPI_REQUIRED',
                question:
                    'Quels KPI ou dimensions de contrôle doivent apparaître : coût, délai, avancement, risques, qualité, achats, cash ou marge ?',
                answerKey: 'dashboardKpi',
                signals: [
                    'kpi',
                    'cout',
                    'cost',
                    'delai',
                    'planning',
                    'avancement',
                    'progress',
                    'risque',
                    'risk',
                    'qualite',
                    'procurement',
                    'cash',
                    'marge'
                ],
                importance: 'blocking'
            },
            {
                id: 'DASHBOARD_DATA_SOURCE_REQUIRED',
                question:
                    'Quelles sources de données alimenteront ces indicateurs et qui en est propriétaire ?',
                answerKey: 'dashboardDataSource',
                signals: [
                    'source',
                    'sources',
                    'erp',
                    'excel',
                    'bi',
                    'data owner',
                    'proprietaire donnees'
                ],
                importance: 'important'
            },
            {
                id: 'DASHBOARD_FREQUENCY_REQUIRED',
                question:
                    'À quelle fréquence le dashboard doit-il être actualisé et à quelle date de statut ?',
                answerKey: 'dashboardFrequency',
                signals: [
                    'quotidien',
                    'daily',
                    'hebdomadaire',
                    'weekly',
                    'mensuel',
                    'monthly',
                    'date de statut'
                ],
                importance: 'important'
            }
        ]),

        workflow: Object.freeze([
            {
                id: 'WORKFLOW_SCOPE_REQUIRED',
                question:
                    'Quel processus ou objet métier doit être digitalisé par ce workflow ?',
                answerKey: 'workflowScope',
                signals: [
                    'processus',
                    'process',
                    'document',
                    'validation documentaire',
                    'achat',
                    'achats',
                    'ncr',
                    'inspection'
                ],
                importance: 'blocking'
            },
            {
                id: 'WORKFLOW_STEPS_REQUIRED',
                question:
                    'Quelles sont les étapes actuelles du processus, depuis la création jusqu’à la clôture ?',
                answerKey: 'workflowSteps',
                signals: [
                    'etape',
                    'etapes',
                    'creation',
                    'validation',
                    'approbation',
                    'cloture'
                ],
                importance: 'blocking'
            },
            {
                id: 'WORKFLOW_ACTORS_REQUIRED',
                question:
                    'Quels rôles interviennent à chaque étape et qui peut approuver, rejeter ou retourner le dossier ?',
                answerKey: 'workflowActors',
                signals: [
                    'role',
                    'roles',
                    'approbateur',
                    'approbation',
                    'rejeter',
                    'retourner'
                ],
                importance: 'blocking'
            },
            {
                id: 'WORKFLOW_RULES_REQUIRED',
                question:
                    'Quelles règles, conditions, délais ou seuils doivent piloter automatiquement le workflow ?',
                answerKey: 'workflowRules',
                signals: [
                    'regle',
                    'regles',
                    'condition',
                    'conditions',
                    'delai',
                    'seuil'
                ],
                importance: 'important'
            },
            {
                id: 'WORKFLOW_TRACEABILITY_REQUIRED',
                question:
                    'Quelles preuves, notifications et traces d’audit doivent être conservées ?',
                answerKey: 'workflowTraceability',
                signals: [
                    'audit trail',
                    'trace',
                    'traces',
                    'notification',
                    'notifications',
                    'preuve',
                    'preuves'
                ],
                importance: 'important'
            }
        ])
    });

    function unique(values) {
        return Array.from(new Set(values));
    }

    function hasAnswer(input, requirement) {
        const answers =
            input &&
            input.answers
                ? input.answers
                : {};

        const value =
            answers[requirement.answerKey];

        if (value === undefined || value === null) {
            return false;
        }

        if (
            typeof value === 'string' &&
            value.trim() === ''
        ) {
            return false;
        }

        return true;
    }

    function hasSignal(
        normalizedText,
        requirement
    ) {
        return requirement.signals.some(
            function (signal) {
                return recognizer.containsTerm(
                    normalizedText,
                    signal
                );
            }
        );
    }

    function requirementSatisfied(
        normalizedText,
        requirement,
        input
    ) {
        return (
            hasAnswer(input, requirement) ||
            hasSignal(
                normalizedText,
                requirement
            )
        );
    }

    function evaluate(
        requestedArtifact,
        input
    ) {
        if (
            !requestedArtifact ||
            !requestedArtifact.id
        ) {
            return {
                applicable: false,
                artifactId: null,
                ready: false,
                completion: 0,
                satisfiedRequirements: [],
                missingRequirements: [],
                nextRequirement: null
            };
        }

        const artifactId =
            requestedArtifact.id;

        const requirements =
            REQUIREMENT_REGISTRY[artifactId] || [];

        if (!requirements.length) {
            return {
                applicable: false,
                artifactId,
                ready: false,
                completion: 0,
                satisfiedRequirements: [],
                missingRequirements: [],
                nextRequirement: null
            };
        }

        const normalizedText =
            recognizer.normalizeText(
                input && input.freeText
                    ? input.freeText
                    : ''
            );

        const satisfiedRequirements = [];
        const missingRequirements = [];

        requirements.forEach(
            function (requirement) {
                const satisfied =
                    requirementSatisfied(
                        normalizedText,
                        requirement,
                        input || {}
                    );

                const result = {
                    id: requirement.id,
                    question: requirement.question,
                    answerKey: requirement.answerKey,
                    importance: requirement.importance,
                    satisfied
                };

                if (satisfied) {
                    satisfiedRequirements.push(result);
                } else {
                    missingRequirements.push(result);
                }
            }
        );

        /*
         * Blocking requirements come first.
         * Within the same importance level,
         * registry order is preserved.
         */
        const orderedMissing =
            missingRequirements
                .slice()
                .sort(
                    function (left, right) {
                        const priority = {
                            blocking: 0,
                            important: 1,
                            optional: 2
                        };

                        return (
                            priority[left.importance] -
                            priority[right.importance]
                        );
                    }
                );

        const completion =
            requirements.length
                ? Math.round(
                    (
                        satisfiedRequirements.length /
                        requirements.length
                    ) * 100
                )
                : 0;

        return {
            applicable: true,
            artifactId,
            ready:
                missingRequirements.length === 0,
            completion,
            satisfiedRequirements:
                unique(
                    satisfiedRequirements.map(
                        function (item) {
                            return item.id;
                        }
                    )
                ),
            missingRequirements:
                orderedMissing,
            nextRequirement:
                orderedMissing.length
                    ? orderedMissing[0]
                    : null
        };
    }

    return {
        REQUIREMENT_REGISTRY,
        evaluate
    };
});