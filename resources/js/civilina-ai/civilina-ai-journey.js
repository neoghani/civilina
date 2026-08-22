(function () {
    'use strict';

    const config = window.CivilinaAIScenarios;
    const domains = window.CivilinaAIDomains;
    const core = window.CivilinaAIReasoningRuntime || window.CivilinaAIReasoning;
    const privacy = window.CivilinaAIPrivacy;
    const storageKey = 'civilinaAiJourneyV4';
    const validPhases = [
        'start',
        'conversation',
        'confirmation',
        'edit',
        'reading',
        'artifactReady',
        'recommendation',
        'account',
        'followup'
    ];

    const artifactQuestionFallbacks = {
        itpActivityScope: {
            artifactId: 'itp',
            requirementId: 'ITP_ACTIVITY_SCOPE_REQUIRED',
            label: 'Périmètre de l’activité',
            prompt: 'Quelle activité, quel ouvrage ou quelle zone doit être couvert par l’ITP ?',
            importance: 'blocking'
        },
        itpReferences: {
            artifactId: 'itp',
            requirementId: 'ITP_REFERENCE_DOCUMENTS_REQUIRED',
            label: 'Références applicables',
            prompt: 'Quels plans, spécifications, procédures ou exigences contractuelles doivent servir de référence ?',
            importance: 'blocking'
        },
        itpInspectionsTests: {
            artifactId: 'itp',
            requirementId: 'ITP_INSPECTION_TESTS_REQUIRED',
            label: 'Inspections et essais',
            prompt: 'Quelles inspections et quels essais doivent être prévus pour cette activité ?',
            importance: 'blocking'
        },
        itpAcceptanceCriteria: {
            artifactId: 'itp',
            requirementId: 'ITP_ACCEPTANCE_CRITERIA_REQUIRED',
            label: 'Critères d’acceptation',
            prompt: 'Quels sont les critères d’acceptation applicables à chaque contrôle ou essai ?',
            importance: 'blocking'
        },
        itpHoldWitness: {
            artifactId: 'itp',
            requirementId: 'ITP_HOLD_WITNESS_REQUIRED',
            label: 'Hold / Witness Points',
            prompt: 'Quels Hold Points, Witness Points ou points de surveillance doivent être définis ?',
            importance: 'important'
        },
        itpResponsibilities: {
            artifactId: 'itp',
            requirementId: 'ITP_RESPONSIBILITIES_REQUIRED',
            label: 'Responsabilités',
            prompt: 'Qui exécute, inspecte, approuve et accepte les contrôles prévus dans l’ITP ?',
            importance: 'important'
        },
        wbsScopeBasis: {
            artifactId: 'wbs',
            requirementId: 'WBS_SCOPE_BASIS_REQUIRED',
            label: 'Périmètre WBS',
            prompt: 'Quel périmètre, contrat, projet ou lot doit servir de base à la WBS ?',
            importance: 'blocking'
        },
        wbsDecomposition: {
            artifactId: 'wbs',
            requirementId: 'WBS_DECOMPOSITION_REQUIRED',
            label: 'Logique de décomposition',
            prompt: 'Selon quelle logique le périmètre doit-il être décomposé : livrables, disciplines, phases, lots ou autre ?',
            importance: 'blocking'
        },
        wbsZoneOuvrage: {
            artifactId: 'wbs',
            requirementId: 'WBS_ZONE_OUVRAGE_REQUIRED',
            label: 'Zones / ouvrages',
            prompt: 'Quelles zones ou quels ouvrages doivent être distingués sans les confondre avec la WBS ?',
            importance: 'important'
        },
        wbsCodification: {
            artifactId: 'wbs',
            requirementId: 'WBS_CODIFICATION_REQUIRED',
            label: 'Codification',
            prompt: 'Quelle règle de codification ou de numérotation doit être utilisée ?',
            importance: 'important'
        },
        wbsIntegration: {
            artifactId: 'wbs',
            requirementId: 'WBS_INTEGRATION_REQUIRED',
            label: 'Intégration Project Controls',
            prompt: 'Comment la WBS doit-elle s’intégrer au planning, aux coûts, au progrès et aux documents ?',
            importance: 'important'
        },
        riskScope: {
            artifactId: 'riskRegister',
            requirementId: 'RISK_SCOPE_REQUIRED',
            label: 'Périmètre des risques',
            prompt: 'Quel projet, processus ou périmètre doit être couvert par le registre des risques ?',
            importance: 'blocking'
        },
        riskCategories: {
            artifactId: 'riskRegister',
            requirementId: 'RISK_CATEGORIES_REQUIRED',
            label: 'Catégories de risques',
            prompt: 'Quelles catégories de risques doivent être couvertes ?',
            importance: 'important'
        },
        riskScoring: {
            artifactId: 'riskRegister',
            requirementId: 'RISK_SCORING_REQUIRED',
            label: 'Méthode de cotation',
            prompt: 'Quelle méthode de probabilité, impact et criticité doit être utilisée ?',
            importance: 'blocking'
        },
        riskOwners: {
            artifactId: 'riskRegister',
            requirementId: 'RISK_OWNERS_REQUIRED',
            label: 'Responsables des risques',
            prompt: 'Qui doit être responsable du suivi et des actions de réponse aux risques ?',
            importance: 'important'
        },
        raciProcessScope: {
            artifactId: 'raci',
            requirementId: 'RACI_PROCESS_SCOPE_REQUIRED',
            label: 'Périmètre RACI',
            prompt: 'Quel processus, projet ou périmètre doit être couvert par la matrice RACI ?',
            importance: 'blocking'
        },
        raciActivities: {
            artifactId: 'raci',
            requirementId: 'RACI_ACTIVITIES_REQUIRED',
            label: 'Activités / décisions',
            prompt: 'Quelles activités ou décisions doivent apparaître dans la matrice ?',
            importance: 'blocking'
        },
        raciRoles: {
            artifactId: 'raci',
            requirementId: 'RACI_ROLES_REQUIRED',
            label: 'Rôles',
            prompt: 'Quels rôles ou fonctions doivent être représentés dans la matrice ?',
            importance: 'blocking'
        },
        raciAuthority: {
            artifactId: 'raci',
            requirementId: 'RACI_AUTHORITY_REQUIRED',
            label: 'Autorité / validation',
            prompt: 'Quelles règles d’autorité, d’approbation ou d’escalade doivent être respectées ?',
            importance: 'important'
        },
        dashboardDecision: {
            artifactId: 'dashboard',
            requirementId: 'DASHBOARD_DECISION_REQUIRED',
            label: 'Décision à soutenir',
            prompt: 'Quelle décision ou quel besoin de pilotage le dashboard doit-il soutenir ?',
            importance: 'blocking'
        },
        dashboardAudience: {
            artifactId: 'dashboard',
            requirementId: 'DASHBOARD_AUDIENCE_REQUIRED',
            label: 'Audience',
            prompt: 'Qui utilisera ce dashboard et à quel niveau de responsabilité ?',
            importance: 'important'
        },
        dashboardKpis: {
            artifactId: 'dashboard',
            requirementId: 'DASHBOARD_KPI_REQUIRED',
            label: 'KPI',
            prompt: 'Quels KPI doivent permettre de prendre ou préparer cette décision ?',
            importance: 'blocking'
        },
        dashboardDataSources: {
            artifactId: 'dashboard',
            requirementId: 'DASHBOARD_DATA_SOURCE_REQUIRED',
            label: 'Sources de données',
            prompt: 'Quelles sources de données alimenteront les KPI du dashboard ?',
            importance: 'blocking'
        },
        dashboardFrequency: {
            artifactId: 'dashboard',
            requirementId: 'DASHBOARD_FREQUENCY_REQUIRED',
            label: 'Fréquence',
            prompt: 'À quelle fréquence les données et le dashboard doivent-ils être mis à jour ?',
            importance: 'important'
        },
        workflowScope: {
            artifactId: 'workflow',
            requirementId: 'WORKFLOW_SCOPE_REQUIRED',
            label: 'Périmètre du workflow',
            prompt: 'Quel processus ou périmètre doit être couvert par le workflow ?',
            importance: 'blocking'
        },
        workflowSteps: {
            artifactId: 'workflow',
            requirementId: 'WORKFLOW_STEPS_REQUIRED',
            label: 'Étapes',
            prompt: 'Quelles sont les principales étapes, entrées et sorties du workflow ?',
            importance: 'blocking'
        },
        workflowActors: {
            artifactId: 'workflow',
            requirementId: 'WORKFLOW_ACTORS_REQUIRED',
            label: 'Acteurs',
            prompt: 'Quels rôles ou acteurs interviennent à chaque étape ?',
            importance: 'blocking'
        },
        workflowRules: {
            artifactId: 'workflow',
            requirementId: 'WORKFLOW_RULES_REQUIRED',
            label: 'Règles / validations',
            prompt: 'Quelles règles, validations, délais ou escalades doivent être appliqués ?',
            importance: 'important'
        },
        workflowTraceability: {
            artifactId: 'workflow',
            requirementId: 'WORKFLOW_TRACEABILITY_REQUIRED',
            label: 'Traçabilité',
            prompt: 'Quelles preuves, données ou traces doivent être conservées pour chaque étape ?',
            importance: 'important'
        }
    };

    document.documentElement.dataset.reasoningCoreVersion = core.REASONING_CORE_VERSION || 'unknown';

    const elements = {
        navigation: document.getElementById('main-navigation'),
        menuToggle: document.querySelector('.menu-toggle'),
        commandCenter: document.getElementById('ai-command'),
        progress: document.getElementById('ai-progress'),
        phase: document.getElementById('ai-phase'),
        conversation: document.getElementById('ai-conversation'),
        suggestions: document.getElementById('ai-suggestions'),
        form: document.getElementById('ai-form'),
        input: document.getElementById('ai-need'),
        submit: document.getElementById('ai-submit'),
        note: document.getElementById('form-note'),
        reset: document.getElementById('ai-reset'),
        outcome: document.getElementById('ai-outcome')
    };

    let state = createInitialState();
    let pendingArtifactPersistence = null;

    function createInitialState() {
        return {
            version: 4,
            scenario: null,
            entryIntent: null,
            primaryDomain: null,
            relatedDomains: [],
            capabilities: [],
            phase: 'start',
            step: 0,
            questionCount: 0,
            answers: {},
            answerLabels: {},
            derivedTextFacts: [],
            initialText: '',
            messages: [],
            activeQuestion: null,
            editingField: null,
            reasoning: emptyReasoning(),
            artifactWorkflow: null,
            sensitiveIntent: false,
            peopleGuardrail: false,
            expertSuggested: false
        };
    }

    function emptyReasoning() {
        return {
            entryIntent: null,
            primaryDomain: null,
            relatedDomains: [],
            capabilities: [],
            domainSignals: {},
            capabilitySignals: {},
            confidence: { domains: {}, capabilities: {} },
            facts: [],
            evidence: [],
            uncertainties: [],
            contradictions: [],
            priorities: [],
            recommendations: { immediate: [], explore: [] },
            sensitiveIntent: false,
            peopleGuardrail: false,
            expertSuggested: false
        };
    }

    function normalize(value) {
        return String(value || '').trim();
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = text;
        return element;
    }

    function closeMenu() {
        elements.navigation.classList.remove('is-open');
        elements.menuToggle.setAttribute('aria-expanded', 'false');
        elements.menuToggle.setAttribute('aria-label', 'Ouvrir le menu');
    }

    function currentScenario() {
        return state.entryIntent && config.scenarios[state.entryIntent] ? config.scenarios[state.entryIntent] : null;
    }

    function addMessage(role, text) {
        state.messages.push({ role: role, text: text });
    }

    function getV2Reasoning(reasoning) {
        const source = reasoning || state.reasoning;
        return source && source.v2 ? source.v2 : null;
    }

    function getRequestedArtifact(reasoning) {
        const v2 = getV2Reasoning(reasoning);
        return v2 && v2.requestedArtifact ? v2.requestedArtifact : null;
    }

    function getArtifactReadiness(reasoning) {
        const v2 = getV2Reasoning(reasoning);
        return v2 && v2.artifactReadiness ? v2.artifactReadiness : null;
    }

    function isArtifactWorkflowActive(reasoning) {
        const artifact = getRequestedArtifact(reasoning);
        const readiness = getArtifactReadiness(reasoning);
        return Boolean(artifact && readiness && readiness.applicable);
    }

    function isArtifactReady(reasoning) {
        const readiness = getArtifactReadiness(reasoning);
        return Boolean(isArtifactWorkflowActive(reasoning) && readiness.ready);
    }

    /*
     * Sprint 2.8 — direct explanatory intent.
     *
     * Mentioning an artifact while asking for an explanation must never
     * enter its creation workflow. When V2 classifies the request as
     * EXPLAIN and exposes an artifact mention, the Journey can answer
     * immediately without falling back to the generic questionnaire.
     */
    function directExplanationFromReasoning(reasoning) {
        const v2 = getV2Reasoning(reasoning);

        if (!v2 || v2.interactionMode !== 'explain' || isArtifactWorkflowActive(reasoning)) {
            return null;
        }

        const mentions = Array.isArray(v2.artifactMentions)
            ? v2.artifactMentions
            : [];

        const firstMention = mentions.length ? mentions[0] : null;
        const artifactId = typeof firstMention === 'string'
            ? firstMention
            : (firstMention && firstMention.id ? firstMention.id : null);

        if (!artifactId) return null;

        const explanations = {
            itp: {
                title: 'Qu’est-ce qu’un ITP ?',
                body: 'Un ITP (Inspection & Test Plan) est un plan de contrôle qualité qui structure, pour une activité donnée, les inspections et essais à réaliser, les références applicables, les critères d’acceptation, les points Hold / Witness, les responsabilités et les preuves à conserver.',
                purpose: 'Il sert à définir avant l’exécution qui contrôle quoi, quand, selon quelle exigence et comment la conformité sera tracée.',
                next: 'Si vous souhaitez ensuite en construire un, formulez par exemple : « Crée un ITP pour le bétonnage de la zone X. »'
            },
            wbs: {
                title: 'Qu’est-ce qu’une WBS ?',
                body: 'Une WBS (Work Breakdown Structure) est une décomposition structurée du périmètre d’un projet en éléments maîtrisables. Elle organise le travail selon une logique explicite, par exemple par livrables, disciplines, phases ou lots.',
                purpose: 'Elle sert de référence commune pour structurer le planning, les coûts, le progrès, les responsabilités et les documents sans confondre la hiérarchie WBS avec les dimensions de contexte comme les zones ou les ouvrages.',
                next: 'Si vous souhaitez en construire une, formulez par exemple : « Crée une WBS pour le projet SP-01. »'
            },
            riskRegister: {
                title: 'Qu’est-ce qu’un registre des risques ?',
                body: 'Un registre des risques centralise les risques identifiés, leur évaluation, leurs responsables, les réponses prévues et leur état de suivi.',
                purpose: 'Il sert à rendre les risques visibles, priorisables et pilotables dans le temps.',
                next: 'Pour en créer un, vous pouvez demander : « Crée un registre des risques pour mon projet. »'
            },
            raci: {
                title: 'Qu’est-ce qu’une matrice RACI ?',
                body: 'Une matrice RACI clarifie, pour chaque activité ou décision, qui est Responsable, qui est Approbateur, qui doit être Consulté et qui doit être Informé.',
                purpose: 'Elle sert à réduire les ambiguïtés de responsabilité et à rendre les circuits de décision explicites.',
                next: 'Pour en construire une, vous pouvez demander : « Crée une matrice RACI pour ce processus. »'
            },
            dashboard: {
                title: 'Qu’est-ce qu’un dashboard de pilotage ?',
                body: 'Un dashboard de pilotage rassemble des indicateurs ciblés pour soutenir une décision ou un besoin de management précis.',
                purpose: 'Il relie les KPI, leurs sources de données, leur fréquence de mise à jour et leur audience à une finalité de pilotage claire.',
                next: 'Pour en concevoir un, vous pouvez demander : « Crée un dashboard pour piloter mon projet. »'
            },
            workflow: {
                title: 'Qu’est-ce qu’un workflow ?',
                body: 'Un workflow décrit un enchaînement structuré d’étapes, d’acteurs, de règles, de validations et de traces pour exécuter un processus.',
                purpose: 'Il sert à rendre le processus explicite, répétable, contrôlable et traçable.',
                next: 'Pour en construire un, vous pouvez demander : « Crée un workflow pour notre processus de validation. »'
            }
        };

        return explanations[artifactId] || null;
    }

    /*
     * Sprint 2.8 — multi-artifact clarification.
     *
     * V2 owns the ambiguity decision. The Journey only renders the
     * authoritative selection question and feeds the chosen artifact id
     * back through answers.artifactSelection.
     */
    /*
     * Sprint 2.8 — dependency-aware artifact orchestration.
     *
     * When V2 resolves WBS + ITP through the WBS_BEFORE_ITP rule,
     * the Journey explains the sequencing once, then starts the WBS
     * requirement flow directly.
     */
    function artifactDependencyIntroFromReasoning(reasoning) {
        const v2 = getV2Reasoning(reasoning);
        const decision = v2 && v2.artifactDependencyDecision
            ? v2.artifactDependencyDecision
            : null;

        if (!decision || decision.rule !== 'WBS_BEFORE_ITP') {
            return '';
        }

        return 'La WBS constitue la structure de référence du projet. ' +
            'Je la construis donc en premier, puis l’ITP pourra être rattaché aux activités ou Work Packages concernés. ';
    }

    function artifactAmbiguityQuestionFromReasoning(reasoning) {
        const v2 = getV2Reasoning(reasoning);

        if (!v2 || !v2.artifactAmbiguity) {
            return null;
        }

        const mentions = Array.isArray(v2.artifactMentions)
            ? v2.artifactMentions.filter(function (item) {
                return item && item.id;
            })
            : [];

        if (mentions.length < 2) {
            return null;
        }

        const shortLabels = {
            itp: 'ITP',
            wbs: 'WBS',
            riskRegister: 'Registre des risques',
            raci: 'RACI',
            dashboard: 'Dashboard',
            workflow: 'Workflow'
        };

        const authoritative =
            v2.nextBestQuestion &&
            v2.nextBestQuestion.source === 'artifactAmbiguity'
                ? v2.nextBestQuestion
                : null;

        return {
            id: 'artifactSelection',
            requirementId: null,
            prompt: authoritative && (authoritative.text || authoritative.prompt)
                ? (authoritative.text || authoritative.prompt)
                : 'Quel livrable souhaitez-vous construire en premier ?',
            suggestions: mentions.map(function (artifact) {
                return [
                    shortLabels[artifact.id] || artifact.label || artifact.id,
                    artifact.id
                ];
            }),
            source: 'artifactAmbiguity',
            importance: 'blocking'
        };
    }

    function cloneRequirement(requirement) {
        if (!requirement) return null;
        return {
            id: requirement.id || null,
            answerKey: requirement.answerKey || null,
            question: requirement.question || '',
            importance: requirement.importance || 'important',
            satisfied: Boolean(requirement.satisfied)
        };
    }

    function artifactWorkflowFromReasoning(reasoning) {
        const v2 = getV2Reasoning(reasoning);
        if (!v2 || !v2.requestedArtifact || !v2.artifactReadiness || !v2.artifactReadiness.applicable) {
            return null;
        }

        const readiness = v2.artifactReadiness;

        return {
            interactionMode: v2.interactionMode || null,
            requestedArtifact: {
                id: v2.requestedArtifact.id || null,
                label: v2.requestedArtifact.label || 'Livrable',
                family: v2.requestedArtifact.family || null,
                confidence: v2.requestedArtifact.confidence || null
            },
            artifactReadiness: {
                applicable: true,
                artifactId: readiness.artifactId || v2.requestedArtifact.id || null,
                ready: Boolean(readiness.ready),
                completion: Number.isFinite(Number(readiness.completion)) ? Number(readiness.completion) : 0,
                satisfiedRequirements: Array.isArray(readiness.satisfiedRequirements)
                    ? readiness.satisfiedRequirements.slice()
                    : [],
                missingRequirements: Array.isArray(readiness.missingRequirements)
                    ? readiness.missingRequirements.map(cloneRequirement)
                    : [],
                nextRequirement: cloneRequirement(readiness.nextRequirement)
            }
        };
    }

    function workflowCompletion(snapshot) {
        if (!snapshot || !snapshot.artifactReadiness) return 0;
        return Number(snapshot.artifactReadiness.completion) || 0;
    }

    function chooseArtifactWorkflow(previousSnapshot, runtimeSnapshot) {
        if (!previousSnapshot) return runtimeSnapshot;
        if (!runtimeSnapshot) return previousSnapshot;

        const previousId = previousSnapshot.requestedArtifact && previousSnapshot.requestedArtifact.id;
        const runtimeId = runtimeSnapshot.requestedArtifact && runtimeSnapshot.requestedArtifact.id;

        if (previousId && runtimeId && previousId !== runtimeId) {
            return runtimeSnapshot;
        }

        /*
         * After a privacy-safe restore, initialText is intentionally empty.
         * Prefer the persisted structural workflow if the runtime can only
         * reconstruct a less complete version of the same artifact.
         */
        if (!state.initialText && workflowCompletion(previousSnapshot) > workflowCompletion(runtimeSnapshot)) {
            return previousSnapshot;
        }

        return runtimeSnapshot;
    }

    function ensureV2Container() {
        if (!state.reasoning || typeof state.reasoning !== 'object') {
            state.reasoning = emptyReasoning();
        }
        if (!state.reasoning.v2 || typeof state.reasoning.v2 !== 'object') {
            state.reasoning.v2 = {};
        }
        return state.reasoning.v2;
    }

    function applyArtifactWorkflowSnapshot(snapshot) {
        if (!snapshot || !snapshot.requestedArtifact || !snapshot.artifactReadiness) return;
        const v2 = ensureV2Container();
        v2.interactionMode = snapshot.interactionMode || v2.interactionMode || null;
        v2.requestedArtifact = Object.assign({}, snapshot.requestedArtifact);
        v2.artifactReadiness = {
            applicable: true,
            artifactId: snapshot.artifactReadiness.artifactId || snapshot.requestedArtifact.id,
            ready: Boolean(snapshot.artifactReadiness.ready),
            completion: Number(snapshot.artifactReadiness.completion) || 0,
            satisfiedRequirements: (snapshot.artifactReadiness.satisfiedRequirements || []).slice(),
            missingRequirements: (snapshot.artifactReadiness.missingRequirements || []).map(cloneRequirement),
            nextRequirement: cloneRequirement(snapshot.artifactReadiness.nextRequirement)
        };
    }

    function externalArtifactQuestion(field) {
        const engine = window.CivilinaAIArtifactRequirements;
        const registry = engine && engine.REQUIREMENT_REGISTRY ? engine.REQUIREMENT_REGISTRY : null;
        if (!registry) return null;

        const artifactIds = Array.isArray(registry) ? [] : Object.keys(registry);

        for (let i = 0; i < artifactIds.length; i += 1) {
            const artifactId = artifactIds[i];
            const collection = registry[artifactId];
            const requirements = Array.isArray(collection)
                ? collection
                : (collection && Array.isArray(collection.requirements) ? collection.requirements : []);
            const requirement = requirements.find(function (item) { return item && item.answerKey === field; });

            if (requirement) {
                return {
                    id: field,
                    requirementId: requirement.id || null,
                    artifactId: artifactId,
                    prompt: requirement.question || field,
                    suggestions: [],
                    source: 'artifactRequirement',
                    importance: requirement.importance || 'important'
                };
            }
        }

        return null;
    }

    function fallbackArtifactQuestion(field) {
        const fallback = artifactQuestionFallbacks[field];
        if (!fallback) return null;
        return {
            id: field,
            requirementId: fallback.requirementId,
            artifactId: fallback.artifactId,
            prompt: fallback.prompt,
            suggestions: [],
            source: 'artifactRequirement',
            importance: fallback.importance || 'important'
        };
    }

    function artifactQuestionDefinition(field) {
        return externalArtifactQuestion(field) || fallbackArtifactQuestion(field);
    }

    function artifactFieldLabel(field) {
        const fallback = artifactQuestionFallbacks[field];
        if (fallback && fallback.label) return fallback.label;
        const question = artifactQuestionDefinition(field);
        return question ? shortQuestionLabel(question.prompt) : null;
    }

    function advanceArtifactWorkflow(question) {
        if (!question || question.source !== 'artifactRequirement' || !question.requirementId) return;

        const snapshot = state.artifactWorkflow || artifactWorkflowFromReasoning(state.reasoning);
        if (!snapshot || !snapshot.artifactReadiness) return;

        const readiness = snapshot.artifactReadiness;
        const satisfied = new Set(readiness.satisfiedRequirements || []);
        satisfied.add(question.requirementId);

        const missing = (readiness.missingRequirements || []).filter(function (item) {
            return item && item.id !== question.requirementId;
        });

        const total = satisfied.size + missing.length;
        const nextRequirement = missing.find(function (item) { return item.importance === 'blocking'; }) || missing[0] || null;

        readiness.satisfiedRequirements = Array.from(satisfied);
        readiness.missingRequirements = missing;
        readiness.nextRequirement = cloneRequirement(nextRequirement);
        readiness.ready = missing.length === 0;
        readiness.completion = total > 0 ? Math.round((satisfied.size / total) * 100) : 100;

        state.artifactWorkflow = snapshot;
        applyArtifactWorkflowSnapshot(snapshot);
    }

    function reopenArtifactRequirement(question) {
        if (!question || question.source !== 'artifactRequirement' || !question.requirementId) return;

        const snapshot = state.artifactWorkflow || artifactWorkflowFromReasoning(state.reasoning);
        if (!snapshot || !snapshot.artifactReadiness) return;

        const readiness = snapshot.artifactReadiness;
        const satisfied = (readiness.satisfiedRequirements || []).filter(function (id) {
            return id !== question.requirementId;
        });
        const missing = (readiness.missingRequirements || []).map(cloneRequirement);

        if (!missing.some(function (item) { return item && item.id === question.requirementId; })) {
            missing.unshift({
                id: question.requirementId,
                answerKey: question.id,
                question: question.prompt,
                importance: question.importance || 'important',
                satisfied: false
            });
        }

        const total = satisfied.length + missing.length;
        readiness.satisfiedRequirements = satisfied;
        readiness.missingRequirements = missing;
        readiness.nextRequirement = cloneRequirement(missing[0]);
        readiness.ready = false;
        readiness.completion = total > 0 ? Math.round((satisfied.length / total) * 100) : 0;

        state.artifactWorkflow = snapshot;
        applyArtifactWorkflowSnapshot(snapshot);
    }

    function sanitizeStoredAnswerLabels(safeState) {
        const labels = {};
        Object.keys(state.answerLabels || {}).forEach(function (key) {
            const value = state.answers ? state.answers[key] : null;
            const isStructured = typeof value === 'string' && /^[a-z0-9-]{1,48}$/i.test(value);
            labels[key] = isStructured ? state.answerLabels[key] : 'Réponse libre non conservée après actualisation';
        });
        safeState.answerLabels = labels;
        return safeState;
    }

    function recalculate() {
        const previousWorkflow = state.artifactWorkflow;

        state.reasoning = core.evaluate({
            entryIntent: state.entryIntent,
            answers: state.answers,
            freeText: state.initialText,
            seedFacts: state.derivedTextFacts
        });

        const runtimeWorkflow = artifactWorkflowFromReasoning(state.reasoning);
        state.artifactWorkflow = chooseArtifactWorkflow(previousWorkflow, runtimeWorkflow);

        if (state.artifactWorkflow) {
            applyArtifactWorkflowSnapshot(state.artifactWorkflow);
        }

        state.entryIntent = state.reasoning.entryIntent;
        state.scenario = state.entryIntent;
        state.primaryDomain = state.reasoning.primaryDomain;
        state.relatedDomains = state.reasoning.relatedDomains;
        state.capabilities = state.reasoning.capabilities;
        state.sensitiveIntent = state.sensitiveIntent || state.reasoning.sensitiveIntent;
        state.peopleGuardrail = state.reasoning.peopleGuardrail;
        state.expertSuggested = state.reasoning.expertSuggested;
    }

    function persist() {
        try {
            const safeState = sanitizeStoredAnswerLabels(privacy.buildSafeStorageState(state));
            const workflow = state.artifactWorkflow || artifactWorkflowFromReasoning(state.reasoning);
            if (workflow) safeState.artifactWorkflow = workflow;
            sessionStorage.setItem(storageKey, JSON.stringify(safeState));
        } catch (error) {
            /* Storage must never block the current journey. */
        }
    }

    function restore() {
        try {
            const raw = sessionStorage.getItem(storageKey);
            if (!raw) return;

            const parsed = JSON.parse(raw);
            if (!parsed || parsed.version !== 4 || !validPhases.includes(parsed.phase)) return;

            state = Object.assign(createInitialState(), parsed, {
                initialText: '',
                activeQuestion: null,
                editingField: null,
                messages: Array.isArray(parsed.messages) ? parsed.messages : [],
                artifactWorkflow: parsed.artifactWorkflow || null
            });

            recalculate();

            if (state.phase === 'conversation') {
                if (isArtifactWorkflowActive()) {
                    state.activeQuestion = artifactRequirementQuestion();
                    if (!state.activeQuestion) {
                        state.phase = isArtifactReady() ? 'confirmation' : 'confirmation';
                    }
                } else {
                    prepareNextQuestion(false);
                }
            }

            if (state.phase === 'artifactReady' && !isArtifactReady()) {
                state.phase = isArtifactWorkflowActive() ? 'confirmation' : 'start';
            }
        } catch (error) {
            sessionStorage.removeItem(storageKey);
            state = createInitialState();
        }
    }

    function renderMessages() {
        elements.conversation.innerHTML = '';
        const compactPhases = ['reading', 'confirmation', 'artifactReady'];
        const visibleMessages = compactPhases.includes(state.phase) ? state.messages.slice(-4) : state.messages.slice(-8);

        visibleMessages.forEach(function (message, index) {
            const article = createElement('article', 'ai-message ai-message-' + message.role);
            if (index === visibleMessages.length - 1) article.classList.add('is-new');
            article.append(
                createElement('span', 'ai-message-label', message.role === 'assistant' ? 'Civilina AI' : 'Vous'),
                createElement('p', null, message.text)
            );
            elements.conversation.appendChild(article);
        });

        elements.conversation.scrollTop = elements.conversation.scrollHeight;
    }

    function renderSuggestions() {
        elements.suggestions.innerHTML = '';
        let options = [];

        if (state.phase === 'start') {
            options = config.initialOptions.map(function (option) {
                return { label: option.label, value: option.value, scenario: option.scenario };
            });
        } else if (state.phase === 'conversation' && state.activeQuestion) {
            const suggestions = Array.isArray(state.activeQuestion.suggestions) ? state.activeQuestion.suggestions : [];
            options = suggestions.map(function (item) {
                return { label: item[0], value: item[0], answer: item[1] };
            });
        }

        options.forEach(function (option) {
            const button = createElement('button', 'suggestion', option.label);
            button.type = 'button';
            button.dataset.value = option.value;
            if (option.scenario) button.dataset.scenario = option.scenario;
            if (option.answer) button.dataset.answer = option.answer;
            elements.suggestions.appendChild(button);
        });

        elements.suggestions.hidden = options.length === 0;
    }

    function progressValue() {
        if (state.phase === 'start') return 10;
        if (state.phase === 'conversation') return Math.min(62, 20 + state.questionCount * 10);
        if (['confirmation', 'edit'].includes(state.phase)) return 64;
        if (state.phase === 'reading' && directExplanationFromReasoning()) return 34;
        if (state.phase === 'reading') return 72;
        if (state.phase === 'artifactReady') return 86;
        if (state.phase === 'recommendation') return 86;
        return 100;
    }

    function renderProgress() {
        const labels = {
            start: 'Comprendre',
            conversation: 'Comprendre',
            confirmation: 'Analyser',
            edit: 'Analyser',
            reading: 'Analyser',
            artifactReady: 'Construire',
            recommendation: 'Recommander',
            account: 'Recommander',
            followup: 'Recommander'
        };

        const value = progressValue();
        const directExplanation = state.phase === 'reading' && directExplanationFromReasoning();
        elements.phase.textContent = directExplanation ? 'Comprendre' : (labels[state.phase] || 'Comprendre');
        elements.progress.style.setProperty('--ai-progress', value + '%');
        elements.progress.setAttribute('aria-valuenow', String(value));
        elements.progress.querySelectorAll('span').forEach(function (item) {
            item.classList.toggle('is-active', value >= Number(item.dataset.progress));
        });
    }

    function renderForm() {
        const interactive = state.phase === 'start' || state.phase === 'conversation';
        elements.form.hidden = !interactive;
        elements.input.placeholder = state.phase === 'conversation' ? 'Votre réponse…' : 'Décrivez votre besoin…';
        elements.submit.textContent = state.phase === 'conversation' ? 'Répondre' : 'Commencer';

        if (interactive) {
            elements.note.textContent = 'Vos réponses restent dans cette session du navigateur. Aucun envoi vers Civilina n’a lieu avant votre choix explicite « Enregistrer dans Civilina ».';
        } else if (isArtifactWorkflowActive()) {
            elements.note.textContent = 'Le livrable reste local tant que vous ne choisissez pas « Enregistrer dans Civilina ».';
        } else {
            elements.note.textContent = 'Diagnostic conservé dans cette session uniquement.';
        }
    }

    function appendDomainSummary(section) {
        const summary = createElement('div', 'domain-summary');
        const primary = createElement('div', 'domain-primary');
        primary.append(
            createElement('span', 'result-label', 'Besoin principal'),
            createElement('strong', null, state.primaryDomain ? domains.getDomainLabel(state.primaryDomain) : 'À clarifier')
        );
        summary.appendChild(primary);
        if (state.relatedDomains.length) {
            const related = createElement('div', 'domain-related');
            related.appendChild(createElement('span', 'result-label', 'Sujets associés'));
            const tags = createElement('div', 'domain-tags');
            state.relatedDomains.forEach(function (domainId) {
                tags.appendChild(createElement('span', null, domains.getDomainLabel(domainId)));
            });
            related.appendChild(tags);
            summary.appendChild(related);
        }
        if (state.capabilities.length) {
            const capabilityBlock = createElement('div', 'domain-capabilities');
            capabilityBlock.appendChild(createElement('span', 'result-label', 'Capacités mobilisées'));
            const capabilityTags = createElement('div', 'capability-tags');
            state.capabilities.forEach(function (capabilityId) {
                capabilityTags.appendChild(createElement('span', null, domains.getCapabilityLabel(capabilityId)));
            });
            capabilityBlock.appendChild(capabilityTags);
            summary.appendChild(capabilityBlock);
        }
        section.appendChild(summary);
    }

    function appendReasoningAlerts(section) {
        state.reasoning.contradictions.forEach(function (item) {
            const alert = createElement('div', 'reasoning-alert reasoning-alert-neutral');
            alert.append(createElement('strong', null, 'Point à clarifier'), createElement('p', null, item.message));
            section.appendChild(alert);
        });
        if (state.sensitiveIntent) {
            const alert = createElement('div', 'reasoning-alert');
            alert.append(
                createElement('strong', null, 'Information sensible détectée'),
                createElement('p', null, 'Aucun document n’est transmis ici. Un espace professionnel confidentiel pourra être préparé avant tout échange sensible.')
            );
            section.appendChild(alert);
        }
        state.reasoning.uncertainties.filter(function (item) {
            return item.blocking && item.id !== 'people-evidence-incomplete';
        }).forEach(function (item) {
            const alert = createElement('div', 'reasoning-alert reasoning-alert-neutral');
            alert.append(createElement('strong', null, 'Information à préciser'), createElement('p', null, item.message));
            section.appendChild(alert);
        });
        if (state.peopleGuardrail) {
            const alert = createElement('div', 'reasoning-alert people-note');
            const action = createElement('button', 'text-action', 'Structurer les critères de décision');
            action.type = 'button';
            action.dataset.action = 'structure-people-criteria';
            alert.append(
                createElement('strong', null, 'Décision humaine à encadrer'),
                createElement('p', null, 'Civilina peut aider à structurer la décision, mais ne peut pas désigner une personne à partir d’un seul profil ou indicateur. La décision doit considérer les compétences, l’expérience, la performance, les responsabilités, la disponibilité et les exigences du poste.'),
                action
            );
            section.appendChild(alert);
        }
    }

    function renderConfirmation() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'confirmation';

        const artifact = getRequestedArtifact();
        const readiness = getArtifactReadiness();
        const artifactFlow = isArtifactWorkflowActive();

        section.append(
            createElement('span', 'journey-kicker', artifactFlow ? 'Confirmation du livrable' : 'Confirmation du contexte'),
            createElement('h3', null, artifactFlow && artifact ? artifact.label + ' — contexte recueilli' : 'Civilina a compris :')
        );

        const context = createElement('dl', 'context-list');

        if (artifactFlow && state.initialText) {
            const requestRow = createElement('div', 'context-row');
            requestRow.append(
                createElement('dt', null, 'Demande initiale'),
                createElement('dd', null, state.initialText)
            );
            context.appendChild(requestRow);
        }

        if (artifactFlow && readiness) {
            const completionRow = createElement('div', 'context-row');
            completionRow.append(
                createElement('dt', null, 'Complétude'),
                createElement('dd', null, (Number(readiness.completion) || 0) + ' %')
            );
            context.appendChild(completionRow);
        }

        const fields = Object.keys(state.answerLabels);
        const visibleFields = artifactFlow ? fields : fields.slice(0, 5);

        visibleFields.forEach(function (field) {
            const row = createElement('div', 'context-row');
            const question = findQuestion(field);
            row.append(
                createElement('dt', null, questionLabel(field, question)),
                createElement('dd', null, state.answerLabels[field])
            );
            context.appendChild(row);
        });

        section.appendChild(context);
        appendReasoningAlerts(section);

        const actions = createElement('div', 'journey-actions');
        const confirm = createElement('button', 'btn btn-navy journey-primary', 'C’est correct');
        confirm.type = 'button';
        confirm.dataset.action = 'confirm-context';
        const edit = createElement('button', 'btn btn-outline journey-primary', 'Modifier');
        edit.type = 'button';
        edit.dataset.action = 'show-edit';
        actions.append(confirm, edit);
        section.appendChild(actions);

        return section;
    }

    function renderEdit() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'edit';
        section.append(createElement('span', 'journey-kicker', 'Modifier le contexte'), createElement('h3', null, 'Quelle réponse souhaitez-vous corriger ?'));
        const list = createElement('div', 'edit-answer-list');
        Object.keys(state.answerLabels).forEach(function (field) {
            const question = findQuestion(field);
            const button = createElement('button', 'edit-answer', questionLabel(field, question) + ' : ' + state.answerLabels[field]);
            button.type = 'button';
            button.dataset.action = 'edit-answer';
            button.dataset.field = field;
            list.appendChild(button);
        });
        section.appendChild(list);
        const back = createElement('button', 'text-action', 'Retour à la confirmation');
        back.type = 'button';
        back.dataset.action = 'show-confirmation';
        section.appendChild(back);
        return section;
    }

    function renderReading() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'reading';

        const directExplanation = directExplanationFromReasoning();

        if (directExplanation) {
            section.dataset.view = 'explanation';
            section.append(
                createElement('span', 'journey-kicker', 'Explication Civilina'),
                createElement('h3', null, directExplanation.title),
                createElement('p', 'journey-context', directExplanation.body),
                createElement('p', 'journey-context', directExplanation.purpose),
                createElement('p', 'uncertainty-note', directExplanation.next)
            );
            return section;
        }

        section.append(createElement('span', 'journey-kicker', 'Première lecture Civilina'), createElement('h3', null, 'Votre situation en quelques points'));
        appendDomainSummary(section);
        appendReasoningAlerts(section);
        if (state.reasoning.priorities.length) {
            section.appendChild(createElement('h3', 'journey-subtitle', 'Priorités identifiées'));
            const list = createElement('ol', 'reasoned-list');
            state.reasoning.priorities.slice(0, 3).forEach(function (priority) {
                const item = createElement('li');
                item.append(createElement('strong', null, priority.title), createElement('p', null, priority.reason));
                list.appendChild(item);
            });
            section.appendChild(list);
        } else if (state.reasoning.uncertainties.length) {
            section.appendChild(createElement('p', 'uncertainty-note', state.reasoning.uncertainties[0].message));
        }
        const action = createElement('button', 'btn btn-navy journey-primary', 'Voir mon environnement recommandé');
        action.type = 'button';
        action.dataset.action = 'show-recommendation';
        section.appendChild(action);
        return section;
    }

    function appendRecommendations(section, title, recommendations) {
        if (!recommendations.length) return;
        section.appendChild(createElement('h3', 'journey-subtitle', title));
        const list = createElement('div', 'evidence-list');
        recommendations.forEach(function (recommendation) {
            const item = createElement('article', 'evidence-item');
            item.append(createElement('strong', null, recommendation.label), createElement('p', null, recommendation.evidence[0].reason));
            list.appendChild(item);
        });
        section.appendChild(list);
    }

    function renderRecommendation() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'recommendation';
        section.append(createElement('span', 'journey-kicker', 'Votre environnement Civilina recommandé'), createElement('h3', null, 'Des pistes ciblées, fondées sur vos réponses'));
        appendRecommendations(section, 'Priorités immédiates', state.reasoning.recommendations.immediate);
        appendRecommendations(section, 'À explorer ensuite', state.reasoning.recommendations.explore);
        if (!state.reasoning.recommendations.immediate.length && !state.reasoning.recommendations.explore.length) {
            section.appendChild(createElement('p', 'uncertainty-note', state.peopleGuardrail
                ? 'Aucune personne ni affectation n’est recommandée sur la base d’un profil ou d’un style de travail.'
                : 'Je n’ai pas encore assez d’éléments pour formuler une recommandation utile.'));
        }
        appendReasoningAlerts(section);
        const action = createElement('button', 'btn btn-navy journey-primary', 'Poursuivre avec Civilina');
        action.type = 'button';
        action.dataset.action = 'show-account';
        section.appendChild(action);
        return section;
    }

    function renderAccount() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'account';
        section.append(
            createElement('span', 'journey-kicker', 'Poursuivre le diagnostic'),
            createElement('h3', null, 'Sauvegardez votre diagnostic pour poursuivre avec Civilina.'),
            createElement('p', 'account-roadmap', 'Connexion Microsoft et Google prévue. Le compte e-mail sera relié à Civilina Core.')
        );
        const actions = createElement('div', 'account-choice-list account-choice-compact');
        const email = createElement('button', 'btn btn-navy', 'Continuer avec l’e-mail');
        email.type = 'button';
        email.disabled = true;
        actions.appendChild(email);
        section.appendChild(actions);
        if (state.sensitiveIntent || state.expertSuggested) {
            const followup = createElement('button', 'text-action', state.sensitiveIntent ? 'Informations sensibles' : 'Compléter avec un expert');
            followup.type = 'button';
            followup.dataset.action = 'show-followup';
            section.appendChild(followup);
        }
        return section;
    }

    function buildHandoffSummary() {
        const immediate = state.reasoning.recommendations.immediate.map(function (item) { return item.label; }).join(', ');
        return [
            'Besoin principal : ' + (state.primaryDomain ? domains.getDomainLabel(state.primaryDomain) : 'à clarifier'),
            'Sujets associés : ' + (state.relatedDomains.map(domains.getDomainLabel).join(', ') || 'aucun sujet associé confirmé'),
            'Capacités mobilisées : ' + (state.capabilities.map(domains.getCapabilityLabel).join(', ') || 'à préciser'),
            'Priorités dérivées : ' + (immediate || 'à approfondir'),
            'Résumé : première lecture Civilina fondée sur des catégories, sans reprise du texte libre.'
        ].join('\n');
    }

    function renderFollowup() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'followup';
        if (state.sensitiveIntent) {
            section.append(
                createElement('span', 'journey-kicker', 'Informations sensibles'),
                createElement('h3', null, 'Un espace protégé avant tout document interne.'),
                createElement('p', 'journey-context', 'Civilina pourra ouvrir un espace professionnel confidentiel et mettre en place un NDA avant une transmission. Aucun document n’est transmis dans cette démonstration.')
            );
        }
        if (state.expertSuggested) {
            section.append(
                createElement('span', 'journey-kicker journey-kicker-spaced', 'Civilina AI + expertise humaine'),
                createElement('h3', null, 'Un regard métier peut compléter cette première lecture.'),
                createElement('p', 'journey-context', 'Avec votre accord, un expert Civilina peut reprendre uniquement le contexte synthétique que vous choisissez de transmettre.'),
                createElement('pre', 'handoff-summary', buildHandoffSummary())
            );
            const expert = createElement('a', 'btn btn-navy journey-primary', 'Parler à un expert');
            expert.href = 'mailto:abdelghani.bouhouche@civilina.com?subject=' + encodeURIComponent('Diagnostic Civilina') + '&body=' + encodeURIComponent(buildHandoffSummary());
            section.appendChild(expert);
        }
        const back = createElement('button', 'text-action', 'Retour à la sauvegarde');
        back.type = 'button';
        back.dataset.action = 'show-account';
        section.appendChild(back);
        return section;
    }
    function appendArtifactPersistence(
        section,
        artifact,
        draft
    ) {
        if (
            !section ||
            !artifact ||
            !draft ||
            typeof draft !== 'object'
        ) {
            return;
        }

        const draftTitle =
            typeof draft.title === 'string'
                ? draft.title.trim()
                : '';

        const title =
            draftTitle ||
            (
                artifact.label ||
                'Livrable Civilina'
            ) +
                ' — Brouillon Civilina';

        pendingArtifactPersistence = {
            type:
                draft.type ||
                draft.artifactType ||
                artifact.id,

            title: title,

            payload: draft,

            provenance: {
                origin:
                    'civilina_ai',

                artifact_id:
                    artifact.id ||
                    null,

                generation_mode:
                    'deterministic_builder',

                journey_phase:
                    'artifactReady',

                traceability:
                    draft.traceability ||
                    null,

                builder_provenance:
                    draft.provenance ||
                    null
            },

            source:
                'civilina_ai'
        };

        const wrapper =
            createElement(
                'div',
                'artifact-persistence'
            );

        const organizationContext =
            createElement(
                'div',
                'artifact-organization-context'
            );

        const organizationLabel =
            createElement(
                'label',
                'journey-context',
                'Organisation d’enregistrement'
            );

        organizationLabel.htmlFor =
            'civilina-artifact-organization';

        const organizationSelect =
            document.createElement(
                'select'
            );

        organizationSelect.id =
            'civilina-artifact-organization';

        organizationSelect.className =
            'ai-input';

        organizationSelect.dataset.organizationSelector =
            'true';

        organizationSelect.disabled =
            true;

        const loadingOption =
            document.createElement(
                'option'
            );

        loadingOption.value =
            '';

        loadingOption.textContent =
            'Chargement des organisations…';

        organizationSelect.appendChild(
            loadingOption
        );

        const organizationStatus =
            createElement(
                'p',
                'journey-context',
                'Vérification de l’organisation active…'
            );

        organizationStatus.dataset.organizationStatus =
            'true';

        organizationContext.append(
            organizationLabel,
            organizationSelect,
            organizationStatus
        );

        const button =
            createElement(
                'button',
                'btn btn-navy journey-primary',
                'Enregistrer dans Civilina'
            );

        button.type =
            'button';

        button.dataset.action =
            'persist-artifact';

        /*
         * The artifact cannot be persisted until the tenant
         * context is unambiguous.
         */
        button.disabled =
            true;

        const status =
            createElement(
                'p',
                'journey-context',
                'Ce brouillon n’est pas encore enregistré dans Civilina.'
            );

        status.dataset.persistenceStatus =
            'true';

        wrapper.append(
            organizationContext,
            button,
            status
        );

        section.appendChild(
            wrapper
        );

        const persistence =
            window.CivilinaArtifactPersistence;

        if (
            !persistence ||
            typeof persistence.discoverOrganizations !==
                'function' ||
            typeof persistence.setActiveOrganizationPublicId !==
                'function'
        ) {
            organizationStatus.textContent =
                'Le contexte d’organisation Civilina est indisponible.';

            return;
        }

        const selectOrganization =
            function (
                publicId,
                organizationName
            ) {
                const normalizedPublicId =
                    String(
                        publicId || ''
                    ).trim();

                if (!normalizedPublicId) {
                    persistence.setActiveOrganizationPublicId(
                        null
                    );

                    button.disabled =
                        true;

                    organizationStatus.textContent =
                        'Choisissez l’organisation dans laquelle enregistrer ce livrable.';

                    return;
                }

                persistence.setActiveOrganizationPublicId(
                    normalizedPublicId
                );

                button.disabled =
                    false;

                organizationStatus.textContent =
                    'Organisation active : ' +
                    (
                        organizationName ||
                        normalizedPublicId
                    ) +
                    '.';
            };

        organizationSelect.addEventListener(
            'change',
            function () {
                const selected =
                    organizationSelect
                        .options[
                            organizationSelect
                                .selectedIndex
                        ];

                selectOrganization(
                    organizationSelect.value,
                    selected &&
                    selected.dataset.organizationName
                        ? selected.dataset.organizationName
                        : selected
                            ? selected.textContent
                            : null
                );
            }
        );

        persistence
            .discoverOrganizations(
                window.fetch
            )
            .then(
                function (
                    organizations
                ) {
                    organizationSelect.innerHTML =
                        '';

                    if (
                        !Array.isArray(
                            organizations
                        ) ||
                        organizations.length === 0
                    ) {
                        const emptyOption =
                            document.createElement(
                                'option'
                            );

                        emptyOption.value =
                            '';

                        emptyOption.textContent =
                            'Aucune organisation disponible';

                        organizationSelect.appendChild(
                            emptyOption
                        );

                        organizationSelect.disabled =
                            true;

                        button.disabled =
                            true;

                        organizationStatus.textContent =
                            'Aucune organisation active n’est disponible pour cet enregistrement.';

                        return;
                    }

                    const activePublicId =
                        typeof persistence.getActiveOrganizationPublicId ===
                            'function'
                            ? persistence.getActiveOrganizationPublicId()
                            : null;

                    if (
                        organizations.length > 1
                    ) {
                        const placeholder =
                            document.createElement(
                                'option'
                            );

                        placeholder.value =
                            '';

                        placeholder.textContent =
                            'Choisir une organisation';

                        organizationSelect.appendChild(
                            placeholder
                        );
                    }

                    organizations.forEach(
                        function (
                            organization
                        ) {
                            const option =
                                document.createElement(
                                    'option'
                                );

                            option.value =
                                organization.public_id;

                            option.textContent =
                                organization.name ||
                                organization.slug ||
                                organization.public_id;

                            option.dataset.organizationName =
                                option.textContent;

                            organizationSelect.appendChild(
                                option
                            );
                        }
                    );

                    organizationSelect.disabled =
                        false;

                    const selectedOrganization =
                        organizations.find(
                            function (
                                organization
                            ) {
                                return (
                                    activePublicId &&
                                    organization.public_id ===
                                        activePublicId
                                );
                            }
                        ) ||
                        (
                            organizations.length === 1
                                ? organizations[0]
                                : null
                        );

                    if (
                        selectedOrganization
                    ) {
                        organizationSelect.value =
                            selectedOrganization.public_id;

                        selectOrganization(
                            selectedOrganization.public_id,
                            selectedOrganization.name ||
                            selectedOrganization.slug ||
                            selectedOrganization.public_id
                        );

                        return;
                    }

                    organizationSelect.value =
                        '';

                    button.disabled =
                        true;

                    organizationStatus.textContent =
                        'Plusieurs organisations sont disponibles : choisissez explicitement l’organisation d’enregistrement.';
                }
            )
            .catch(
                function (
                    error
                ) {
                    organizationSelect.innerHTML =
                        '';

                    const errorOption =
                        document.createElement(
                            'option'
                        );

                    errorOption.value =
                        '';

                    errorOption.textContent =
                        'Organisation indisponible';

                    organizationSelect.appendChild(
                        errorOption
                    );

                    organizationSelect.disabled =
                        true;

                    button.disabled =
                        true;

                    organizationStatus.textContent =
                        error &&
                        error.message
                            ? error.message
                            : 'Impossible de charger les organisations Civilina.';
                }
            );
    }

    function renderArtifactReady() {
    const section = createElement(
        'section',
        'journey-panel'
    );

    section.dataset.view =
        'artifact-ready';

    const artifact =
        getRequestedArtifact();

    const readiness =
        getArtifactReadiness();

    const artifactLabel =
        artifact &&
        artifact.label
            ? artifact.label
            : 'Livrable';

    const completion =
        readiness &&
        Number.isFinite(
            Number(
                readiness.completion
            )
        )
            ? Number(
                readiness.completion
            )
            : 100;

    section.append(
        createElement(
            'span',
            'journey-kicker',
            'Livrable prêt à construire'
        ),
        createElement(
            'h3',
            null,
            artifactLabel +
                ' prêt à être préparé'
        ),
        createElement(
            'p',
            'journey-context',
            'Les informations nécessaires ont été recueillies et confirmées.'
        )
    );

    const context =
        createElement(
            'dl',
            'context-list'
        );

    const artifactRow =
        createElement(
            'div',
            'context-row'
        );

    artifactRow.append(
        createElement(
            'dt',
            null,
            'Livrable'
        ),
        createElement(
            'dd',
            null,
            artifactLabel
        )
    );

    const completionRow =
        createElement(
            'div',
            'context-row'
        );

    completionRow.append(
        createElement(
            'dt',
            null,
            'Complétude'
        ),
        createElement(
            'dd',
            null,
            completion + ' %'
        )
    );

    const statusRow =
        createElement(
            'div',
            'context-row'
        );

    statusRow.append(
        createElement(
            'dt',
            null,
            'État'
        ),
        createElement(
            'dd',
            null,
            readiness &&
            readiness.ready
                ? 'Informations suffisantes pour préparer le brouillon'
                : 'Informations à compléter'
        )
    );

    context.append(
        artifactRow,
        completionRow,
        statusRow
    );

    section.appendChild(
        context
    );

    /*
     * Sprint 2.5
     * Build a real deterministic ITP draft
     * when the requested artifact is an ITP.
     */
    if (
        artifact &&
        artifact.id === 'itp' &&
        window.CivilinaAIITPBuilder
    ) {
        const buildResult =
            window.CivilinaAIITPBuilder.build({
                initialText:
                    state.initialText,

                answers:
                    state.answers
            });

        if (
            buildResult &&
            buildResult.ready &&
            buildResult.artifact
        ) {
            const draft =
                buildResult.artifact;

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Brouillon ITP structuré'
                )
            );

            const draftSummary =
                createElement(
                    'dl',
                    'context-list'
                );

            const activityRow =
                createElement(
                    'div',
                    'context-row'
                );

            activityRow.append(
                createElement(
                    'dt',
                    null,
                    'Activité'
                ),
                createElement(
                    'dd',
                    null,
                    draft.context.activity
                )
            );

            const revisionRow =
                createElement(
                    'div',
                    'context-row'
                );

            revisionRow.append(
                createElement(
                    'dt',
                    null,
                    'Révision'
                ),
                createElement(
                    'dd',
                    null,
                    draft.revision
                )
            );

            draftSummary.append(
                activityRow,
                revisionRow
            );

            section.appendChild(
                draftSummary
            );

            /*
             * References
             */
            if (
                Array.isArray(
                    draft.references
                ) &&
                draft.references.length
            ) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'Références applicables'
                    )
                );

                const references =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                draft.references.forEach(
                    function (
                        reference,
                        index
                    ) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.append(
                            createElement(
                                'strong',
                                null,
                                'Référence ' +
                                    (
                                        index +
                                        1
                                    )
                            ),
                            createElement(
                                'p',
                                null,
                                reference
                            )
                        );

                        references.appendChild(
                            item
                        );
                    }
                );

                section.appendChild(
                    references
                );
            }

            /*
             * Inspections and tests
             */
            /*
 * Sprint 2.6
 * Structured ITP rows
 */
const rows =
    Array.isArray(
        draft.rows
    )
        ? draft.rows
        : [];

if (rows.length) {
    section.appendChild(
        createElement(
            'h3',
            'journey-subtitle',
            'Plan d’inspection structuré'
        )
    );

    const rowsList =
        createElement(
            'div',
            'evidence-list'
        );

    rows.forEach(
        function (row) {
            const item =
                createElement(
                    'article',
                    'evidence-item'
                );

            item.classList.add(
                'itp-structured-row'
            );

            /*
             * Row header
             */
            item.append(
                createElement(
                    'strong',
                    null,
                    row.code +
                        ' — ' +
                        row.inspectionTest
                )
            );

            const details =
                createElement(
                    'dl',
                    'context-list'
                );

            /*
             * Inspection / Test
             */
            const inspectionRow =
                createElement(
                    'div',
                    'context-row'
                );

            inspectionRow.append(
                createElement(
                    'dt',
                    null,
                    'Inspection / Essai'
                ),
                createElement(
                    'dd',
                    null,
                    row.inspectionTest
                )
            );

            /*
             * Method
             */
            const methodRow =
                createElement(
                    'div',
                    'context-row'
                );

            methodRow.append(
                createElement(
                    'dt',
                    null,
                    'Méthode'
                ),
                createElement(
                    'dd',
                    null,
                    row.method ||
                        'À définir'
                )
            );

            /*
             * Frequency
             */
            const frequencyRow =
                createElement(
                    'div',
                    'context-row'
                );

            frequencyRow.append(
                createElement(
                    'dt',
                    null,
                    'Fréquence'
                ),
                createElement(
                    'dd',
                    null,
                    row.frequency ||
                        'À définir'
                )
            );

            /*
             * Acceptance criteria
             */
            const criteriaRow =
                createElement(
                    'div',
                    'context-row'
                );

            criteriaRow.append(
                createElement(
                    'dt',
                    null,
                    'Critère d’acceptation'
                ),
                createElement(
                    'dd',
                    null,
                    row.acceptanceCriteria ||
                        'À définir'
                )
            );

            /*
             * Control point
             */
            const controlPointRow =
                createElement(
                    'div',
                    'context-row'
                );

            let controlPointLabel =
                'À définir';

            if (
                row.controlPoint ===
                'HOLD'
            ) {
                controlPointLabel =
                    'Hold Point (HP)';
            }

            if (
                row.controlPoint ===
                'WITNESS'
            ) {
                controlPointLabel =
                    'Witness Point (WP)';
            }

            if (
                row.controlPoint ===
                'SURVEILLANCE'
            ) {
                controlPointLabel =
                    'Point de surveillance';
            }

            controlPointRow.append(
                createElement(
                    'dt',
                    null,
                    'Point de contrôle'
                ),
                createElement(
                    'dd',
                    null,
                    controlPointLabel
                )
            );

            /*
             * Control point statement
             */
            if (
                row.controlPointStatement
            ) {
                const statementRow =
                    createElement(
                        'div',
                        'context-row'
                    );

                statementRow.append(
                    createElement(
                        'dt',
                        null,
                        'Exigence associée'
                    ),
                    createElement(
                        'dd',
                        null,
                        row.controlPointStatement
                    )
                );

                details.appendChild(
                    statementRow
                );
            }

            /*
             * Responsibilities
             */
            const executeRow =
                createElement(
                    'div',
                    'context-row'
                );

            executeRow.append(
                createElement(
                    'dt',
                    null,
                    'Exécute'
                ),
                createElement(
                    'dd',
                    null,
                    row.execute ||
                        'À définir'
                )
            );

            const inspectRow =
                createElement(
                    'div',
                    'context-row'
                );

            inspectRow.append(
                createElement(
                    'dt',
                    null,
                    'Inspecte'
                ),
                createElement(
                    'dd',
                    null,
                    row.inspect ||
                        'À définir'
                )
            );

            const approveRow =
                createElement(
                    'div',
                    'context-row'
                );

            approveRow.append(
                createElement(
                    'dt',
                    null,
                    'Approuve'
                ),
                createElement(
                    'dd',
                    null,
                    row.approve ||
                        'À définir'
                )
            );

            const acceptRow =
                createElement(
                    'div',
                    'context-row'
                );

            acceptRow.append(
                createElement(
                    'dt',
                    null,
                    'Accepte'
                ),
                createElement(
                    'dd',
                    null,
                    row.accept ||
                        'À définir'
                )
            );

            /*
             * Quality record
             */
            const recordRow =
                createElement(
                    'div',
                    'context-row'
                );

            recordRow.append(
                createElement(
                    'dt',
                    null,
                    'Enregistrement / preuve'
                ),
                createElement(
                    'dd',
                    null,
                    row.record ||
                        'À définir'
                )
            );

            /*
             * Status
             */
            const statusRow =
                createElement(
                    'div',
                    'context-row'
                );

            statusRow.append(
                createElement(
                    'dt',
                    null,
                    'État'
                ),
                createElement(
                    'dd',
                    null,
                    row.status ===
                        'STRUCTURED'
                        ? 'Structuré'
                        : 'À enrichir'
                )
            );

            details.append(
                inspectionRow,
                methodRow,
                frequencyRow,
                criteriaRow,
                controlPointRow,
                executeRow,
                inspectRow,
                approveRow,
                acceptRow,
                recordRow,
                statusRow
            );

            item.appendChild(
                details
            );

            /*
             * Missing fields
             */
            if (
                Array.isArray(
                    row.missingFields
                ) &&
                row.missingFields.length
            ) {
                const missingLabels = {
                    method:
                        'méthode',

                    frequency:
                        'fréquence',

                    acceptanceCriteria:
                        'critère d’acceptation',

                    controlPoint:
                        'point de contrôle',

                    execute:
                        'exécutant',

                    inspect:
                        'inspection',

                    approve:
                        'approbation',

                    accept:
                        'acceptation',

                    record:
                        'enregistrement qualité'
                };

                const missingText =
                    row.missingFields
                        .map(
                            function (
                                field
                            ) {
                                return (
                                    missingLabels[
                                        field
                                    ] ||
                                    field
                                );
                            }
                        )
                        .join(', ');

                item.appendChild(
                    createElement(
                        'p',
                        'uncertainty-note',
                        'À compléter : ' +
                            missingText +
                            '.'
                    )
                );
            }

            rowsList.appendChild(
                item
            );
        }
    );

    section.appendChild(
        rowsList
    );
}

/*
 * Sprint 2.6
 * Acceptance criteria preserved but not safely associated
 * with a specific inspection/test row.
 */
const unassociatedCriteria =
    draft.inspectionPlan &&
    Array.isArray(
        draft.inspectionPlan
            .unassociatedAcceptanceCriteria
    )
        ? draft.inspectionPlan
            .unassociatedAcceptanceCriteria
        : [];

if (unassociatedCriteria.length) {
    section.appendChild(
        createElement(
            'h3',
            'journey-subtitle',
            'Critères d’acceptation à associer'
        )
    );

    section.appendChild(
        createElement(
            'p',
            'journey-context',
            'Ces critères ont été fournis et conservés, mais Civilina ne les associe pas automatiquement à une ligne lorsque le lien n’est pas suffisamment certain.'
        )
    );

    const criteriaList =
        createElement(
            'div',
            'evidence-list'
        );

    unassociatedCriteria.forEach(
        function (criterion, index) {
            const item =
                createElement(
                    'div',
                    'evidence-item'
                );

            item.append(
                createElement(
                    'strong',
                    null,
                    'Critère non associé ' +
                        (index + 1)
                ),
                createElement(
                    'p',
                    null,
                    criterion
                )
            );

            criteriaList.appendChild(
                item
            );
        }
    );

    section.appendChild(
        criteriaList
    );
}
            /*
             * Required enrichment
             */
            const enrichment =
                draft.enrichment &&
                Array.isArray(
                    draft
                        .enrichment
                        .required
                )
                    ? draft
                        .enrichment
                        .required
                    : [];

            if (
                enrichment.length
            ) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'À structurer ensuite'
                    )
                );

                const enrichmentList =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                enrichment.forEach(
                    function (
                        requirement
                    ) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.append(
                            createElement(
                                'strong',
                                null,
                                requirement.label
                            )
                        );

                        enrichmentList
                            .appendChild(
                                item
                            );
                    }
                );

                section.appendChild(
                    enrichmentList
                );
            }

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Brouillon déterministe construit uniquement à partir des informations validées. Aucune exigence technique n’a été inventée.'
                )
            );

            appendArtifactPersistence(
                section,
                artifact,
                draft
            );

            return section;
        }
    }

    /*
     * Sprint 2.7
     * Build a deterministic WBS draft when requested.
     */
    if (
        artifact &&
        artifact.id === 'wbs' &&
        window.CivilinaAIWBSBuilder
    ) {
        const buildResult =
            window.CivilinaAIWBSBuilder.build({
                initialText:
                    state.initialText,

                answers:
                    state.answers
            });

        if (
            buildResult &&
            buildResult.ready &&
            buildResult.artifact
        ) {
            const draft =
                buildResult.artifact;

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Brouillon WBS structuré'
                )
            );

            const summary =
                createElement(
                    'dl',
                    'context-list'
                );

            const scopeRow =
                createElement(
                    'div',
                    'context-row'
                );

            scopeRow.append(
                createElement(
                    'dt',
                    null,
                    'Périmètre'
                ),
                createElement(
                    'dd',
                    null,
                    draft.scope &&
                    draft.scope.statement
                        ? draft.scope.statement
                        : 'À définir'
                )
            );

            const revisionRow =
                createElement(
                    'div',
                    'context-row'
                );

            revisionRow.append(
                createElement(
                    'dt',
                    null,
                    'Révision'
                ),
                createElement(
                    'dd',
                    null,
                    draft.revision ||
                        'DRAFT-01'
                )
            );

            const decompositionRow =
                createElement(
                    'div',
                    'context-row'
                );

            decompositionRow.append(
                createElement(
                    'dt',
                    null,
                    'Logique de décomposition'
                ),
                createElement(
                    'dd',
                    null,
                    draft.decomposition &&
                    draft.decomposition.statement
                        ? draft.decomposition.statement
                        : 'À définir'
                )
            );

            const codificationRow =
                createElement(
                    'div',
                    'context-row'
                );

            codificationRow.append(
                createElement(
                    'dt',
                    null,
                    'Codification'
                ),
                createElement(
                    'dd',
                    null,
                    draft.codification &&
                    draft.codification.rule
                        ? draft.codification.rule
                        : 'À définir'
                )
            );

            summary.append(
                scopeRow,
                revisionRow,
                decompositionRow,
                codificationRow
            );

            section.appendChild(
                summary
            );

            const zonesOuvrages =
                draft.context &&
                Array.isArray(
                    draft.context.zonesOuvrages
                )
                    ? draft.context.zonesOuvrages
                    : [];

            if (zonesOuvrages.length) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'Zones / ouvrages'
                    )
                );

                section.appendChild(
                    createElement(
                        'p',
                        'journey-context',
                        'Dimension contextuelle conservée séparément de la hiérarchie WBS.'
                    )
                );

                const zoneList =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                zonesOuvrages.forEach(
                    function (value, index) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.append(
                            createElement(
                                'strong',
                                null,
                                'Zone / ouvrage ' +
                                    (index + 1)
                            ),
                            createElement(
                                'p',
                                null,
                                value
                            )
                        );

                        zoneList.appendChild(
                            item
                        );
                    }
                );

                section.appendChild(
                    zoneList
                );
            }

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Intégration Project Controls'
                )
            );

            const integration =
                draft.integration || {};

            const integrationList =
                createElement(
                    'dl',
                    'context-list'
                );

            [
                ['Planning', 'planning'],
                ['Coûts', 'cost'],
                ['Progrès', 'progress'],
                ['Documents', 'documents']
            ].forEach(
                function (definition) {
                    const row =
                        createElement(
                            'div',
                            'context-row'
                        );

                    row.append(
                        createElement(
                            'dt',
                            null,
                            definition[0]
                        ),
                        createElement(
                            'dd',
                            null,
                            integration[
                                definition[1]
                            ]
                                ? 'Prévu'
                                : 'Non confirmé'
                        )
                    );

                    integrationList.appendChild(
                        row
                    );
                }
            );

            section.appendChild(
                integrationList
            );

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Hiérarchie WBS'
                )
            );

            const hierarchy =
                draft.hierarchy || {};

            const dimensions =
                Array.isArray(
                    hierarchy.declaredDimensions
                )
                    ? hierarchy.declaredDimensions
                    : [];

            const dimensionLabels = {
                deliverable:
                    'Livrables',
                ouvrage:
                    'Ouvrages',
                zone:
                    'Zones',
                discipline:
                    'Disciplines',
                phase:
                    'Phases',
                lot:
                    'Lots',
                'work-package':
                    'Work Packages',
                system:
                    'Systèmes'
            };

            section.appendChild(
                createElement(
                    'p',
                    'journey-context',
                    dimensions.length
                        ? 'Dimensions déclarées : ' +
                            dimensions
                                .map(function (id) {
                                    return dimensionLabels[id] || id;
                                })
                                .join(' → ') +
                            '. Les nœuds réels restent à structurer.'
                        : 'Les niveaux et nœuds réels restent à structurer à partir du périmètre confirmé.'
                )
            );

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Aucun Work Package ni nœud WBS spécifique n’a été inventé.'
                )
            );

            const enrichment =
                draft.enrichment &&
                Array.isArray(
                    draft.enrichment.required
                )
                    ? draft.enrichment.required
                    : [];

            if (enrichment.length) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'À structurer ensuite'
                    )
                );

                const enrichmentList =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                enrichment.forEach(
                    function (requirement) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.appendChild(
                            createElement(
                                'strong',
                                null,
                                requirement.label
                            )
                        );

                        enrichmentList.appendChild(
                            item
                        );
                    }
                );

                section.appendChild(
                    enrichmentList
                );
            }

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Brouillon déterministe construit uniquement à partir du contexte validé. Zone / Ouvrage reste une dimension distincte de la WBS.'
                )
            );

            appendArtifactPersistence(
                section,
                artifact,
                draft
            );

            return section;
        }
    }
    /*
     * Sprint 2.9
     * Build a deterministic Risk Register draft
     * from Journey answers through the safe bridge.
     */
    if (
        artifact &&
        artifact.id === 'riskRegister' &&
        window.CivilinaAIRiskRegisterJourneyBridge &&
        window.CivilinaAIRiskRegisterBuilder
    ) {
        const prepared =
            window
                .CivilinaAIRiskRegisterJourneyBridge
                .prepare({
                    initialText:
                        state.initialText,

                    answers:
                        state.answers
                });

        const buildResult =
            prepared &&
            prepared.ready
                ? window
                    .CivilinaAIRiskRegisterBuilder
                    .build(
                        prepared.builderInput
                    )
                : null;

        if (
            buildResult &&
            buildResult.ready &&
            buildResult.artifact
        ) {
            const draft =
                buildResult.artifact;

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Registre des risques structuré'
                )
            );

            const summary =
                createElement(
                    'dl',
                    'context-list'
                );

            const scopeRow =
                createElement(
                    'div',
                    'context-row'
                );

            scopeRow.append(
                createElement(
                    'dt',
                    null,
                    'Périmètre'
                ),
                createElement(
                    'dd',
                    null,
                    draft.context &&
                    draft.context.scope
                        ? draft.context.scope
                        : 'À définir'
                )
            );

            const scoringRow =
                createElement(
                    'div',
                    'context-row'
                );

            scoringRow.append(
                createElement(
                    'dt',
                    null,
                    'Cotation'
                ),
                createElement(
                    'dd',
                    null,
                    draft.scoring &&
                    draft.scoring.method
                        ? draft.scoring.method
                        : 'À définir'
                )
            );

            const revisionRow =
                createElement(
                    'div',
                    'context-row'
                );

            revisionRow.append(
                createElement(
                    'dt',
                    null,
                    'Révision'
                ),
                createElement(
                    'dd',
                    null,
                    draft.revision ||
                        'DRAFT-01'
                )
            );

            summary.append(
                scopeRow,
                scoringRow,
                revisionRow
            );

            section.appendChild(
                summary
            );

            const rows =
                Array.isArray(
                    draft.rows
                )
                    ? draft.rows
                    : [];

            if (rows.length) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'Risques identifiés'
                    )
                );

                const list =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                rows.forEach(
                    function (row) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        const title =
                            (
                                row.id
                                    ? row.id + ' — '
                                    : ''
                            ) +
                            (
                                row.risk ||
                                'Risque à préciser'
                            );

                        item.appendChild(
                            createElement(
                                'strong',
                                null,
                                title
                            )
                        );

                        const details = [];

                        if (row.category) {
                            details.push(
                                'Catégorie : ' +
                                    row.category
                            );
                        }

                        if (row.cause) {
                            details.push(
                                'Cause : ' +
                                    row.cause
                            );
                        }

                        if (row.consequence) {
                            details.push(
                                'Conséquence : ' +
                                    row.consequence
                            );
                        }

                        if (
                            row.probability !==
                                null &&
                            row.probability !==
                                undefined
                        ) {
                            details.push(
                                'Probabilité : ' +
                                    row.probability
                            );
                        }

                        if (
                            row.impact !==
                                null &&
                            row.impact !==
                                undefined
                        ) {
                            details.push(
                                'Impact : ' +
                                    row.impact
                            );
                        }

                        if (
                            row.criticality !==
                                null &&
                            row.criticality !==
                                undefined
                        ) {
                            details.push(
                                'Criticité : ' +
                                    row.criticality
                            );
                        }

                        if (row.owner) {
                            details.push(
                                'Responsable : ' +
                                    row.owner
                            );
                        }

                        if (row.response) {
                            details.push(
                                'Réponse : ' +
                                    row.response
                            );
                        }

                        if (row.dueDate) {
                            details.push(
                                'Échéance : ' +
                                    row.dueDate
                            );
                        }

                        if (row.status) {
                            details.push(
                                'Statut : ' +
                                    row.status
                            );
                        }

                        details.forEach(
                            function (detail) {
                                item.appendChild(
                                    createElement(
                                        'p',
                                        null,
                                        detail
                                    )
                                );
                            }
                        );

                        if (
                            Array.isArray(
                                row.missingFields
                            ) &&
                            row.missingFields.length
                        ) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    'uncertainty-note',
                                    'À compléter : ' +
                                        row
                                            .missingFields
                                            .join(', ')
                                )
                            );
                        }

                        list.appendChild(
                            item
                        );
                    }
                );

                section.appendChild(
                    list
                );
            }

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Registre construit uniquement à partir des risques et informations explicitement fournis. Aucune probabilité, cause, responsabilité ou action manquante n’est inventée.'
                )
            );

            appendArtifactPersistence(
                section,
                artifact,
                draft
            );

            return section;
        }
    }
        /*
     * Sprint 2.9
     * Risk Register Journey -> Bridge -> Builder.
     */



        
        /*
     * Sprint 2.9
     * RACI Journey -> Bridge -> Builder.
     */
    if (
        artifact &&
        artifact.id === 'raci' &&
        window.CivilinaAIRACIJourneyBridge &&
        window.CivilinaAIRACIBuilder
    ) {
        const prepared =
            window.CivilinaAIRACIJourneyBridge.prepare({
                initialText:
                    state.initialText,

                answers:
                    state.answers
            });

        const buildResult =
            prepared &&
            prepared.ready
                ? window.CivilinaAIRACIBuilder.build(
                    prepared.builderInput
                )
                : null;

        if (
            buildResult &&
            buildResult.ready &&
            buildResult.artifact
        ) {
            const draft =
                buildResult.artifact;

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Matrice RACI structurée'
                )
            );

            const summary =
                createElement(
                    'dl',
                    'context-list'
                );

            const scopeRow =
                createElement(
                    'div',
                    'context-row'
                );

            scopeRow.append(
                createElement(
                    'dt',
                    null,
                    'Périmètre'
                ),
                createElement(
                    'dd',
                    null,
                    draft.context &&
                    draft.context.processScope
                        ? draft.context.processScope
                        : 'À définir'
                )
            );

            const authorityRow =
                createElement(
                    'div',
                    'context-row'
                );

            authorityRow.append(
                createElement(
                    'dt',
                    null,
                    'Autorité / gouvernance'
                ),
                createElement(
                    'dd',
                    null,
                    draft.authority &&
                    draft.authority.statement
                        ? draft.authority.statement
                        : 'À définir'
                )
            );

            const revisionRow =
                createElement(
                    'div',
                    'context-row'
                );

            revisionRow.append(
                createElement(
                    'dt',
                    null,
                    'Révision'
                ),
                createElement(
                    'dd',
                    null,
                    draft.revision ||
                        'DRAFT-01'
                )
            );

            summary.append(
                scopeRow,
                authorityRow,
                revisionRow
            );

            section.appendChild(
                summary
            );

            const rows =
                Array.isArray(
                    draft.rows
                )
                    ? draft.rows
                    : [];

            if (rows.length) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'Affectations RACI'
                    )
                );

                const list =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                rows.forEach(
                    function (row) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.appendChild(
                            createElement(
                                'strong',
                                null,
                                (
                                    row.id
                                        ? row.id + ' — '
                                        : ''
                                ) +
                                (
                                    row.activity ||
                                    'Activité à préciser'
                                )
                            )
                        );

                        const roleLine =
                            function (
                                code,
                                label,
                                values
                            ) {
                                if (
                                    !Array.isArray(
                                        values
                                    ) ||
                                    !values.length
                                ) {
                                    return;
                                }

                                item.appendChild(
                                    createElement(
                                        'p',
                                        null,
                                        code +
                                            ' — ' +
                                            label +
                                            ' : ' +
                                            values.join(', ')
                                    )
                                );
                            };

                        roleLine(
                            'R',
                            'Responsible',
                            row.responsible
                        );

                        roleLine(
                            'A',
                            'Accountable',
                            row.accountable
                        );

                        roleLine(
                            'C',
                            'Consulted',
                            row.consulted
                        );

                        roleLine(
                            'I',
                            'Informed',
                            row.informed
                        );

                        if (
                            Array.isArray(
                                row.missingFields
                            ) &&
                            row.missingFields.length
                        ) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    'uncertainty-note',
                                    'À compléter : ' +
                                        row.missingFields.join(', ')
                                )
                            );
                        }

                        list.appendChild(
                            item
                        );
                    }
                );

                section.appendChild(
                    list
                );
            }

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Matrice construite uniquement à partir des activités, rôles et affectations explicitement fournis. Civilina ne déduit aucune responsabilité manquante.'
                )
            );

            appendArtifactPersistence(
                section,
                artifact,
                draft
            );

            return section;
        }
    }
        /*
     * Sprint 2.9
     * Workflow Journey -> Bridge -> Builder.
     */
    if (
        artifact &&
        artifact.id === 'workflow' &&
        window.CivilinaAIWorkflowJourneyBridge &&
        window.CivilinaAIWorkflowBuilder
    ) {
        const prepared =
            window.CivilinaAIWorkflowJourneyBridge.prepare({
                initialText: state.initialText,
                answers: state.answers
            });

        const buildResult =
            prepared &&
            prepared.ready
                ? window.CivilinaAIWorkflowBuilder.build(
                    prepared.builderInput
                )
                : null;

        if (
            buildResult &&
            buildResult.ready &&
            buildResult.artifact
        ) {
            const draft = buildResult.artifact;

            section.appendChild(
                createElement(
                    'h3',
                    'journey-subtitle',
                    'Workflow structuré'
                )
            );

            const summary =
                createElement(
                    'dl',
                    'context-list'
                );

            const scopeRow =
                createElement(
                    'div',
                    'context-row'
                );

            scopeRow.append(
                createElement(
                    'dt',
                    null,
                    'Périmètre'
                ),
                createElement(
                    'dd',
                    null,
                    draft.context &&
                    draft.context.scope
                        ? draft.context.scope
                        : 'À définir'
                )
            );

            const rulesRow =
                createElement(
                    'div',
                    'context-row'
                );

            rulesRow.append(
                createElement(
                    'dt',
                    null,
                    'Règles'
                ),
                createElement(
                    'dd',
                    null,
                    draft.rules &&
                    draft.rules.statement
                        ? draft.rules.statement
                        : 'À définir'
                )
            );

            const revisionRow =
                createElement(
                    'div',
                    'context-row'
                );

            revisionRow.append(
                createElement(
                    'dt',
                    null,
                    'Révision'
                ),
                createElement(
                    'dd',
                    null,
                    draft.revision || 'DRAFT-01'
                )
            );

            summary.append(
                scopeRow,
                rulesRow,
                revisionRow
            );

            section.appendChild(summary);

            const rows =
                Array.isArray(draft.rows)
                    ? draft.rows
                    : [];

            if (rows.length) {
                section.appendChild(
                    createElement(
                        'h3',
                        'journey-subtitle',
                        'Étapes du workflow'
                    )
                );

                const list =
                    createElement(
                        'div',
                        'evidence-list'
                    );

                rows.forEach(
                    function (row) {
                        const item =
                            createElement(
                                'article',
                                'evidence-item'
                            );

                        item.appendChild(
                            createElement(
                                'strong',
                                null,
                                (
                                    row.id
                                        ? row.id + ' — '
                                        : ''
                                ) +
                                (
                                    row.name ||
                                    'Étape à préciser'
                                )
                            )
                        );

                        if (row.actor) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    null,
                                    'Acteur : ' +
                                        row.actor
                                )
                            );
                        }

                        if (row.action) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    null,
                                    'Action : ' +
                                        row.action
                                )
                            );
                        }

                        item.appendChild(
                            createElement(
                                'p',
                                null,
                                'Transition : ' +
                                    (
                                        row.terminal
                                            ? 'FIN'
                                            : (
                                                row.nextStep ||
                                                'À compléter'
                                            )
                                    )
                            )
                        );

                        if (row.outcome) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    null,
                                    'Sortie : ' +
                                        row.outcome
                                )
                            );
                        }

                        if (
                            Array.isArray(
                                row.missingFields
                            ) &&
                            row.missingFields.length
                        ) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    'uncertainty-note',
                                    'À compléter : ' +
                                        row.missingFields.join(', ')
                                )
                            );
                        }

                        list.appendChild(item);
                    }
                );

                section.appendChild(list);
            }

            if (
                draft.traceabilityRequirements &&
                draft.traceabilityRequirements.statement
            ) {
                section.appendChild(
                    createElement(
                        'p',
                        'uncertainty-note',
                        'Traçabilité demandée : ' +
                            draft
                                .traceabilityRequirements
                                .statement
                    )
                );
            }

            section.appendChild(
                createElement(
                    'p',
                    'uncertainty-note',
                    'Workflow construit uniquement à partir des étapes, acteurs, transitions et règles explicitement fournis. Civilina n’invente aucune transition ni responsabilité.'
                )
            );

            appendArtifactPersistence(
                section,
                artifact,
                draft
            );

            return section;
        }
    }
    /*
 * Sprint 2.9
 * Dashboard Journey -> Bridge -> Builder.
 */
if (
    artifact &&
    artifact.id === 'dashboard' &&
    window.CivilinaAIDashboardJourneyBridge &&
    window.CivilinaAIDashboardBuilder
) {
    const prepared =
        window.CivilinaAIDashboardJourneyBridge.prepare({
            initialText: state.initialText,
            answers: state.answers
        });

    const buildResult =
        prepared &&
        prepared.ready
            ? window.CivilinaAIDashboardBuilder.build(
                prepared.builderInput
            )
            : null;

    if (
        buildResult &&
        buildResult.ready &&
        buildResult.artifact
    ) {
        const draft =
            buildResult.artifact;

        section.appendChild(
            createElement(
                'h3',
                'journey-subtitle',
                'Dashboard de pilotage structuré'
            )
        );

        const summary =
            createElement(
                'dl',
                'context-list'
            );

        [
            [
                'Décision à soutenir',
                draft.context.decision
            ],
            [
                'Audience',
                draft.context.audience
            ],
            [
                'Actualisation',
                draft.context.frequency
            ],
            [
                'Révision',
                draft.revision
            ]
        ].forEach(function (definition) {
            const row =
                createElement(
                    'div',
                    'context-row'
                );

            row.append(
                createElement(
                    'dt',
                    null,
                    definition[0]
                ),
                createElement(
                    'dd',
                    null,
                    definition[1] ||
                        'À définir'
                )
            );

            summary.appendChild(row);
        });

        section.appendChild(summary);

        section.appendChild(
            createElement(
                'h3',
                'journey-subtitle',
                'Indicateurs'
            )
        );

        const list =
            createElement(
                'div',
                'evidence-list'
            );

        draft.rows.forEach(
            function (row) {
                const item =
                    createElement(
                        'article',
                        'evidence-item'
                    );

                item.appendChild(
                    createElement(
                        'strong',
                        null,
                        row.id +
                            ' — ' +
                            row.name
                    )
                );

                [
                    ['Valeur', row.value],
                    ['Unité', row.unit],
                    ['Cible', row.target],
                    ['Tendance', row.trend],
                    ['Source', row.source],
                    [
                        'Propriétaire des données',
                        row.dataOwner
                    ]
                ].forEach(
                    function (definition) {
                        if (
                            definition[1] !== null &&
                            definition[1] !== undefined
                        ) {
                            item.appendChild(
                                createElement(
                                    'p',
                                    null,
                                    definition[0] +
                                        ' : ' +
                                        definition[1]
                                )
                            );
                        }
                    }
                );

                if (
                    Array.isArray(
                        row.missingFields
                    ) &&
                    row.missingFields.length
                ) {
                    item.appendChild(
                        createElement(
                            'p',
                            'uncertainty-note',
                            'À compléter : ' +
                                row.missingFields.join(', ')
                        )
                    );
                }

                list.appendChild(item);
            }
        );

        section.appendChild(list);

        section.appendChild(
            createElement(
                'p',
                'uncertainty-note',
                'Dashboard construit uniquement à partir des KPI, valeurs, cibles et sources explicitement fournis. Civilina n’invente aucun indicateur ni donnée de pilotage.'
            )
        );

        appendArtifactPersistence(
            section,
            artifact,
            draft
        );

        return section;
    }
}
    /*
     * Generic artifact fallback.
     */
    if (
        Object.keys(
            state.answerLabels
        ).length
    ) {
        section.appendChild(
            createElement(
                'h3',
                'journey-subtitle',
                'Contexte confirmé'
            )
        );

        const list =
            createElement(
                'div',
                'evidence-list'
            );

        Object.keys(
            state.answerLabels
        ).forEach(
            function (field) {
                const item =
                    createElement(
                        'article',
                        'evidence-item'
                    );

                const question =
                    findQuestion(
                        field
                    );

                item.append(
                    createElement(
                        'strong',
                        null,
                        questionLabel(
                            field,
                            question
                        )
                    ),
                    createElement(
                        'p',
                        null,
                        state
                            .answerLabels[
                                field
                            ]
                    )
                );

                list.appendChild(
                    item
                );
            }
        );

        section.appendChild(
            list
        );
    }

    section.appendChild(
        createElement(
            'p',
            'uncertainty-note',
            'Prochaine étape : construire un brouillon structuré à partir des informations validées.'
        )
    );

    return section;
}

    function renderOutcome() {
        elements.outcome.innerHTML = '';
        elements.outcome.hidden = ['start', 'conversation'].includes(state.phase);

        if (state.phase === 'confirmation') elements.outcome.appendChild(renderConfirmation());
        if (state.phase === 'edit') elements.outcome.appendChild(renderEdit());
        if (state.phase === 'reading') elements.outcome.appendChild(renderReading());
        if (state.phase === 'artifactReady') elements.outcome.appendChild(renderArtifactReady());
        if (state.phase === 'recommendation') elements.outcome.appendChild(renderRecommendation());
        if (state.phase === 'account') elements.outcome.appendChild(renderAccount());
        if (state.phase === 'followup') elements.outcome.appendChild(renderFollowup());
    }

    function render() {
        renderProgress();
        renderMessages();
        renderSuggestions();
        renderForm();
        renderOutcome();
        elements.reset.hidden = state.phase === 'start' && state.messages.length <= 1;
        elements.commandCenter.dataset.phase = state.phase;
        persist();
    }

    function findQuestion(field) {
        const scenarioQuestion = currentScenario() && currentScenario().questions.find(function (question) {
            return question.id === field;
        });
        if (scenarioQuestion) return scenarioQuestion;

        const artifactQuestion = artifactQuestionDefinition(field);
        if (artifactQuestion) return artifactQuestion;

        const dynamic = {
            domainClarification: {
                id: 'domainClarification',
                prompt: 'Sujet principal',
                suggestions: [
                    ['Mes projets', 'projects'],
                    ['Mon organisation', 'organisation'],
                    ['Mon système ISO / qualité', 'managementSystems'],
                    ['Mes processus digitaux', 'digitalisation'],
                    ['Mes équipes', 'people']
                ]
            },
            delayCause: {
                id: 'delayCause',
                prompt: 'Cause principale des retards',
                suggestions: [
                    ['Planning / séquencement', 'planning'],
                    ['Approvisionnement', 'procurement'],
                    ['Ressources', 'resources'],
                    ['Causes multiples', 'multiple'],
                    ['Je ne sais pas encore', 'unknown']
                ]
            },
            subcontractorBlockage: {
                id: 'subcontractorBlockage',
                prompt: 'Nature du blocage sous-traitant',
                suggestions: [
                    ['Capacité / ressources', 'capacity'],
                    ['Interface achats', 'procurement-interface'],
                    ['Validation technique', 'technical-approval'],
                    ['Qualité d’exécution', 'quality'],
                    ['Je ne sais pas encore', 'unknown']
                ]
            },
            peopleCriteria: {
                id: 'peopleCriteria',
                prompt: 'Quels éléments objectifs sont déjà disponibles ?',
                suggestions: [
                    ['Compétences et expérience', 'skills-experience'],
                    ['Performance et responsabilités', 'performance-responsibilities'],
                    ['Exigences du poste', 'role-requirements'],
                    ['Éléments encore incomplets', 'incomplete']
                ]
            }
        };

        return dynamic[field] || null;
    }

    function shortQuestionLabel(prompt) {
        const cleaned = String(prompt || '').replace(/[?]/g, '');
        return cleaned.length > 42 ? cleaned.slice(0, 39) + '…' : cleaned;
    }

    function questionLabel(field, question) {
        const artifactLabel = artifactFieldLabel(field);
        if (artifactLabel) return artifactLabel;

        const labels = {
            activity: 'Activité',
            portfolio: 'Portefeuille',
            tracking: 'Outils de suivi',
            difficulty: 'Priorité',
            size: 'Taille',
            issue: 'Difficulté',
            decisions: 'Validations',
            objective: 'Objectif',
            reference: 'Référentiel',
            maturity: 'Maturité',
            tools: 'Outils',
            process: 'Processus',
            peopleObjective: 'Objectif humain',
            peopleScope: 'Périmètre humain',
            peopleCriteria: 'Critères de décision',
            domainClarification: 'Sujet principal',
            delayCause: 'Cause des retards',
            subcontractorBlockage: 'Blocage sous-traitant',
            artifactSelection: 'Livrable prioritaire'
        };

        return labels[field] || (question ? shortQuestionLabel(question.prompt) : field);
    }

    function artifactRequirementQuestion() {
        const readiness = getArtifactReadiness();
        const requirement = readiness ? readiness.nextRequirement : null;
        if (!requirement) return null;

        const registered = requirement.answerKey ? artifactQuestionDefinition(requirement.answerKey) : null;
        if (registered) {
            registered.requirementId = requirement.id || registered.requirementId;
            registered.prompt = requirement.question || registered.prompt;
            registered.importance = requirement.importance || registered.importance;
            return registered;
        }

        return {
            id: requirement.answerKey,
            requirementId: requirement.id,
            prompt: requirement.question,
            suggestions: [],
            source: 'artifactRequirement',
            importance: requirement.importance || 'important'
        };
    }

    function prepareNextQuestion(announce) {
        recalculate();

        /*
         * Sprint 2.8 V2-first orchestration:
         * an explanatory request is answerable immediately and must not
         * fall through to artifact requirements or the generic questionnaire.
         */
        if (directExplanationFromReasoning()) {
            state.activeQuestion = null;
            state.phase = 'reading';
            return false;
        }

        const ambiguityQuestion = artifactAmbiguityQuestionFromReasoning();

        if (ambiguityQuestion) {
            state.activeQuestion = ambiguityQuestion;
            state.phase = 'conversation';
            if (announce) addMessage('assistant', state.activeQuestion.prompt);
            return true;
        }

        const artifactReadiness = getArtifactReadiness();

        if (artifactReadiness && artifactReadiness.applicable && artifactReadiness.ready) {
            state.activeQuestion = null;
            state.phase = 'confirmation';

            if (announce) {
                const artifact = getRequestedArtifact();
                const artifactLabel = artifact && artifact.label ? artifact.label : 'livrable';
                addMessage(
                    'assistant',
                    'Merci. Les informations nécessaires pour structurer ' + artifactLabel + ' sont réunies. Vérifions rapidement le contexte avant de poursuivre.'
                );
            }

            return false;
        }

        const artifactQuestion = artifactRequirementQuestion();

        /* Artifact workflows follow their own requirement count; no legacy six-question cap. */
        if (artifactQuestion) {
            state.activeQuestion = artifactQuestion;
            state.phase = 'conversation';
            if (announce) addMessage('assistant', state.activeQuestion.prompt);
            return true;
        }

        if (artifactReadiness && artifactReadiness.applicable) {
            state.activeQuestion = null;
            state.phase = 'confirmation';

            if (announce) {
                addMessage(
                    'assistant',
                    'Merci. Vérifions rapidement les éléments recueillis avant de poursuivre la construction du livrable.'
                );
            }

            return false;
        }

        const scenario = currentScenario();
        state.activeQuestion = core.nextBestQuestion({
            entryIntent: state.entryIntent,
            answers: state.answers,
            reasoning: state.reasoning,
            questions: scenario ? scenario.questions : []
        });

        if (state.activeQuestion && state.questionCount < 6) {
            state.phase = 'conversation';
            if (announce) addMessage('assistant', state.activeQuestion.prompt);
            return true;
        }

        state.activeQuestion = null;
        state.phase = 'confirmation';
        if (announce) addMessage('assistant', 'Merci. Vérifions rapidement le contexte avant la première lecture.');
        return false;
    }

    function startJourney(entryIntent, userText) {
        state = createInitialState();
        pendingArtifactPersistence = null;
        state.entryIntent = entryIntent || core.detectEntryIntent(userText);
        state.scenario = state.entryIntent;
        state.initialText = userText;
        state.derivedTextFacts = core.factsFromText(userText);
        state.sensitiveIntent = privacy.detectSensitiveIntent(userText);
        addMessage('user', userText);
        recalculate();
        prepareNextQuestion(false);

        if (state.activeQuestion) {
            const dependencyIntro = artifactDependencyIntroFromReasoning();
            const intro = dependencyIntro || (
                isArtifactWorkflowActive()
                    ? ''
                    : (currentScenario() ? currentScenario().intro + ' ' : '')
            );
            addMessage('assistant', intro + state.activeQuestion.prompt);
        }

        render();
    }

    function mapFreeAnswer(question, value) {
        const normalized = normalize(value).toLocaleLowerCase('fr');
        const suggestions = Array.isArray(question && question.suggestions) ? question.suggestions : [];
        const exact = suggestions.find(function (item) {
            return item[0].toLocaleLowerCase('fr') === normalized;
        });
        if (exact) return exact[1];

        const keyword = suggestions.find(function (item) {
            return item[0].toLocaleLowerCase('fr').split(/\s+/).some(function (word) {
                return word.length > 4 && normalized.includes(word);
            });
        });

        return keyword ? keyword[1] : 'other';
    }

    function answerCurrentQuestion(text, mappedValue) {
        const question = state.activeQuestion;
        if (!question) return;

        if (question.source === 'artifactRequirement') {
            const answerText = String(text || '').trim();
            state.answers[question.id] = answerText;
            state.answerLabels[question.id] = answerText;
            advanceArtifactWorkflow(question);
        } else if (question.source === 'artifactAmbiguity') {
            state.answers[question.id] = mappedValue || mapFreeAnswer(question, text);
            state.answerLabels[question.id] = String(text || '').trim();
        } else {
            state.answers[question.id] = mappedValue || mapFreeAnswer(question, text);
            state.answerLabels[question.id] = mappedValue ? text : 'Réponse libre qualifiée';
        }

        state.sensitiveIntent = state.sensitiveIntent || privacy.detectSensitiveIntent(text);
        addMessage('user', text);
        state.questionCount += 1;

        const wasEditing = state.editingField;
        state.editingField = null;
        state.activeQuestion = null;
        recalculate();

        if (wasEditing) {
            state.phase = 'confirmation';
            addMessage('assistant', 'Le contexte a été recalculé à partir de votre correction.');
        } else {
            prepareNextQuestion(true);
        }

        render();
        if (state.phase !== 'conversation') elements.outcome.focus({ preventScroll: true });
    }

    function submitValue(value, dataset) {
        const text = normalize(value);
        if (!text) {
            elements.note.textContent = 'Décrivez brièvement ce que vous souhaitez améliorer.';
            elements.input.focus();
            return;
        }
        if (state.phase === 'start') startJourney(dataset && dataset.scenario ? dataset.scenario : null, text);
        else if (state.phase === 'conversation') answerCurrentQuestion(text, dataset && dataset.answer);
        elements.input.value = '';
    }

    function resetJourney() {
        sessionStorage.removeItem(storageKey);
        state = createInitialState();
        pendingArtifactPersistence = null;
        addMessage('assistant', 'Bonjour. Que souhaitez-vous améliorer ou mieux maîtriser dans votre organisation ?');
        render();
        elements.input.focus();
    }

    function prepareExternalPrompt(prompt) {
        elements.commandCenter.scrollIntoView({ behavior: 'smooth', block: 'center' });
        startJourney(core.detectEntryIntent(prompt), prompt);
    }

    function beginEdit(field) {
        const question = findQuestion(field);
        if (!question) return;

        if (question.source === 'artifactRequirement') {
            reopenArtifactRequirement(question);
        }

        delete state.answers[field];
        delete state.answerLabels[field];
        state.editingField = field;
        state.activeQuestion = question;
        state.phase = 'conversation';
        addMessage('assistant', 'Modifions ce point. ' + question.prompt);
        render();
        elements.input.focus();
    }

    elements.menuToggle.addEventListener('click', function () {
        const isOpen = elements.navigation.classList.toggle('is-open');
        elements.menuToggle.setAttribute('aria-expanded', String(isOpen));
        elements.menuToggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
    });
    elements.navigation.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
    elements.input.addEventListener('keydown', function (event) {
    if (
        event.key === 'Enter' &&
        !event.shiftKey &&
        !event.isComposing
    ) {
        event.preventDefault();
        elements.form.requestSubmit();
    }
});

elements.form.addEventListener('submit', function (event) {
    event.preventDefault();
    submitValue(elements.input.value, null);
});
    elements.suggestions.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (button) submitValue(button.dataset.value, button.dataset);
    });
    elements.outcome.addEventListener('click', function (event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;

        const action = target.dataset.action;

        if (
            action ===
            'persist-artifact'
        ) {
            const persistence =
                window.CivilinaArtifactPersistence;

            const status =
                target.parentElement
                    ? target.parentElement.querySelector(
                        '[data-persistence-status]'
                    )
                    : null;

            if (
                !persistence ||
                typeof persistence.persistArtifact !==
                    'function'
            ) {
                if (status) {
                    status.textContent =
                        'Le service de sauvegarde Civilina est indisponible.';
                }

                return;
            }

            if (
                !pendingArtifactPersistence
            ) {
                if (status) {
                    status.textContent =
                        'Aucun livrable structuré à enregistrer.';
                }

                return;
            }

            const organizationSelect =
                target.parentElement
                    ? target.parentElement.querySelector(
                        '[data-organization-selector]'
                    )
                    : null;

            const organizationPublicId =
                organizationSelect
                    ? String(
                        organizationSelect.value || ''
                    ).trim()
                    : '';

            if (!organizationPublicId) {
                target.disabled =
                    true;

                if (status) {
                    status.textContent =
                        'Sélectionnez une organisation avant l’enregistrement.';
                }

                return;
            }

            /*
             * Defense in depth:
             * pass the tenant explicitly AND force the scoped endpoint.
             * Even an older persistence module can no longer fall back to
             * /api/artifacts for an authenticated browser save.
             */
            const organizationEndpoint =
                '/api/organizations/' +
                encodeURIComponent(
                    organizationPublicId
                ) +
                '/artifacts';

            target.disabled =
                true;

            target.textContent =
                'Enregistrement…';

            if (status) {
                status.textContent =
                    'Création du livrable dans l’organisation sélectionnée…';
            }

            persistence
                .persistArtifact(
                    pendingArtifactPersistence,
                    {
                        organizationPublicId:
                            organizationPublicId,

                        endpoint:
                            organizationEndpoint
                    }
                )
                .then(
                    function (
                        savedArtifact
                    ) {
                        const revision =
                            savedArtifact &&
                            savedArtifact.current_revision
                                ? savedArtifact
                                    .current_revision
                                    .revision_label
                                : 'DRAFT-01';

                        target.textContent =
                            'Enregistré — ' +
                            revision;

                        if (status) {
                            status.textContent =
                                'Artifact ' +
                                savedArtifact.public_id +
                                ' enregistré dans Civilina — ' +
                                revision +
                                '.';
                        }

                        pendingArtifactPersistence =
                            Object.assign(
                                {},
                                pendingArtifactPersistence,
                                {
                                    persisted:
                                        true,

                                    public_id:
                                        savedArtifact.public_id,

                                    revision:
                                        revision
                                }
                            );
                    }
                )
                .catch(
                    function (
                        error
                    ) {
                        target.disabled =
                            false;

                        target.textContent =
                            'Réessayer l’enregistrement';

                        if (status) {
                            status.textContent =
                                error &&
                                error.message
                                    ? error.message
                                    : 'Échec de l’enregistrement.';
                        }
                    }
                );

            return;
        }


        if (action === 'confirm-context') {
            if (isArtifactReady()) {
                state.phase = 'artifactReady';
            } else if (isArtifactWorkflowActive()) {
                const continued = prepareNextQuestion(true);
                if (!continued && state.phase !== 'confirmation') state.phase = 'confirmation';
            } else {
                state.phase = 'reading';
            }
        }

        if (action === 'show-edit') state.phase = 'edit';
        if (action === 'show-confirmation') state.phase = 'confirmation';
        if (action === 'edit-answer') return beginEdit(target.dataset.field);
        if (action === 'structure-people-criteria') return beginEdit('peopleCriteria');
        if (action === 'show-recommendation') state.phase = 'recommendation';
        if (action === 'show-account') state.phase = 'account';
        if (action === 'show-followup') state.phase = 'followup';

        render();
        elements.outcome.focus({ preventScroll: true });
    });
    elements.reset.addEventListener('click', resetJourney);
    document.querySelectorAll('[data-prompt]').forEach(function (control) {
        control.addEventListener('click', function () { prepareExternalPrompt(control.dataset.prompt); });
    });

    restore();
    if (state.messages.length === 0) addMessage('assistant', 'Bonjour. Que souhaitez-vous améliorer ou mieux maîtriser dans votre organisation ?');
    render();
    const currentYear = document.getElementById('current-year');
    if (currentYear) currentYear.textContent = new Date().getFullYear();

    window.CivilinaAIJourney = {
        getState: function () { return JSON.parse(JSON.stringify(state)); },
        reset: resetJourney,
        startScenario: startJourney,
        recalculate: recalculate
    };
})();
