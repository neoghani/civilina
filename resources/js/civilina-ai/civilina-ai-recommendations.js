(function (root, factory) {
    'use strict';

    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory(
        isCommonJs ? require('./civilina-ai-domains.js') : root.CivilinaAIDomains,
        isCommonJs ? require('./civilina-ai-rules.js') : root.CivilinaAIRules
    );
    if (isCommonJs) module.exports = api;
    else root.CivilinaAIRecommendations = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (domainApi, ruleApi) {
    'use strict';

    const D = domainApi.BUSINESS_DOMAIN_IDS;
    const C = domainApi.CAPABILITY_IDS;
    const R = domainApi.RECOMMENDATION_IDS;
    const MAX_IMMEDIATE = 3;
    const MAX_EXPLORE = 2;

    const RECOMMENDATIONS = Object.freeze({
        'cost-control': { label: 'Cost Control', businessDomain: D.PROJECTS, capability: C.PROJECT_CONTROLS },
        'planning-wbs': { label: 'Planning & WBS', businessDomain: D.PROJECTS, capability: C.PROJECT_CONTROLS },
        'executive-reporting': { label: 'Executive Reporting', businessDomain: D.PROJECTS, capability: C.DATA, family: 'reporting' },
        'engineering-documents': { label: 'Engineering & Documents', businessDomain: D.PROJECTS, capability: C.ENGINEERING, family: 'documents' },
        procurement: { label: 'Procurement', businessDomain: D.PROJECTS, capability: C.PROCUREMENT },
        'qa-qc': { label: 'QA/QC', businessDomain: D.MANAGEMENT_SYSTEMS, capability: C.QUALITY },
        'roles-responsibilities': { label: 'Roles & Responsibilities', businessDomain: D.ORGANISATION, capability: null },
        'process-management': { label: 'Process Management', businessDomain: D.ORGANISATION, capability: null },
        'decision-workflows': { label: 'Decision Workflows', businessDomain: D.ORGANISATION, capability: C.DATA },
        'management-dashboard': { label: 'Management Dashboard', businessDomain: D.DIGITALISATION, capability: C.DATA, family: 'reporting' },
        'document-control': { label: 'Document Control', businessDomain: D.MANAGEMENT_SYSTEMS, capability: C.QUALITY, family: 'documents' },
        'quality-management': { label: 'Quality Management', businessDomain: D.MANAGEMENT_SYSTEMS, capability: C.QUALITY },
        'audit-actions': { label: 'Audit & Actions', businessDomain: D.MANAGEMENT_SYSTEMS, capability: C.QUALITY },
        'digital-workflows': { label: 'Digital Workflows', businessDomain: D.DIGITALISATION, capability: C.DATA },
        automation: { label: 'Automation', businessDomain: D.DIGITALISATION, capability: C.DATA },
        'data-reporting': { label: 'Data & Reporting', businessDomain: D.DIGITALISATION, capability: C.DATA, family: 'reporting' }
    });

    const EVIDENCE_DEFINITIONS = Object.freeze({
        'project:portfolio': [{ supports: R.EXECUTIVE_REPORTING, weight: 1, reason: 'La taille ou la diversité du portefeuille appelle une lecture consolidée.' }],
        'project:fragmented-tools': [
            { supports: R.EXECUTIVE_REPORTING, weight: 2, reason: 'Vos informations de pilotage sont réparties entre plusieurs outils.' },
            { supports: R.DATA_REPORTING, weight: 2, reason: 'La dispersion des outils limite la consolidation des données.' }
        ],
        'project:cost-delay': [
            { supports: R.COST_CONTROL, weight: 3, reason: 'La maîtrise des coûts et délais fait partie de vos principales difficultés déclarées.' },
            { supports: R.PLANNING_WBS, weight: 2, reason: 'Le besoin déclaré porte conjointement sur les coûts et la maîtrise du calendrier.' },
            { supports: R.EXECUTIVE_REPORTING, weight: 2, reason: 'L’arbitrage coûts-délais demande une lecture consolidée pour la décision.' }
        ],
        'project:delay': [{ supports: R.EXECUTIVE_REPORTING, weight: 1, reason: 'Un retard est signalé, mais sa cause doit encore être qualifiée avant toute action causale.' }],
        'delay:planning-cause': [{ supports: R.PLANNING_WBS, weight: 3, reason: 'La planification ou le séquencement est identifié comme cause principale du retard.' }],
        'delay:resource-cause': [{ supports: R.PLANNING_WBS, weight: 2, reason: 'La disponibilité ou l’arbitrage des ressources est identifié comme cause du retard.' }],
        'delay:engineering-cause': [{ supports: R.ENGINEERING_DOCUMENTS, weight: 3, reason: 'Les études ou validations techniques sont identifiées comme cause du retard.' }],
        'delay:approval-cause': [{ supports: R.DECISION_WORKFLOWS, weight: 3, reason: 'Les décisions ou approbations sont identifiées comme cause du retard.' }],
        'delay:multiple-causes': [{ supports: R.EXECUTIVE_REPORTING, weight: 2, reason: 'Plusieurs familles de causes demandent une lecture consolidée avant arbitrage.' }],
        'delay:unknown': [{ supports: R.EXECUTIVE_REPORTING, weight: 1, reason: 'La cause reste inconnue ; une première lecture factuelle est nécessaire avant recommandation.' }],
        'project:executive-reporting': [{ supports: R.EXECUTIVE_REPORTING, weight: 3, reason: 'Le reporting de direction constitue un besoin explicite.' }],
        'project:resources': [{ supports: R.PLANNING_WBS, weight: 2, reason: 'L’arbitrage des ressources et priorités demande une planification structurée.' }],
        'engineering:documents-risk': [{ supports: R.ENGINEERING_DOCUMENTS, weight: 3, reason: 'Les documents techniques ou risques d’ingénierie ont été identifiés comme difficulté.' }],
        'procurement:delay': [{ supports: R.PROCUREMENT, weight: 3, reason: 'Les approvisionnements critiques sont identifiés comme cause directe des retards.' }],
        'procurement:process': [{ supports: R.PROCUREMENT, weight: 2, reason: 'Le processus achats / procurement est la cible explicite.' }],
        'quality:qa-qc-issue': [{ supports: R.QA_QC, weight: 3, reason: 'Un besoin QA/QC explicite a été signalé.' }],
        'organisation:roles-unclear': [
            { supports: R.ROLES, weight: 3, reason: 'Les responsabilités et validations sont décrites comme peu claires.' },
            { supports: R.PROCESS, weight: 1, reason: 'La clarté des responsabilités conditionne l’exécution des processus.' }
        ],
        'organisation:approvals-unclear': [
            { supports: R.DECISION_WORKFLOWS, weight: 3, reason: 'Les validations sont lentes ou insuffisamment traçables.' },
            { supports: R.DIGITAL_WORKFLOWS, weight: 1, reason: 'Le circuit de validation peut être clarifié avant sa digitalisation.' }
        ],
        'organisation:process-variable': [{ supports: R.PROCESS, weight: 3, reason: 'Le fonctionnement varie selon les équipes ou les situations.' }],
        'organisation:reporting': [{ supports: R.MANAGEMENT_DASHBOARD, weight: 3, reason: 'Une lecture de direction commune fait partie du résultat attendu.' }],
        'management:system': [{ supports: R.QUALITY_MANAGEMENT, weight: 2, reason: 'La structuration du système de management est un besoin déclaré.' }],
        'management:fragmented': [{ supports: R.PROCESS, weight: 2, reason: 'Le système est décrit comme fragmenté ou peu utilisé opérationnellement.' }],
        'management:documents': [{ supports: R.DOCUMENT_CONTROL, weight: 3, reason: 'La maîtrise des documents et des versions est un besoin explicite.' }],
        'management:audits-actions': [{ supports: R.AUDIT_ACTIONS, weight: 3, reason: 'Les audits et le suivi des actions demandent une structuration.' }],
        'management:indicators': [{ supports: R.MANAGEMENT_DASHBOARD, weight: 2, reason: 'Les indicateurs du système doivent être reliés au pilotage.' }],
        'digital:approvals': [{ supports: R.DIGITAL_WORKFLOWS, weight: 2, reason: 'Le processus de validation est la cible de digitalisation déclarée.' }],
        'digital:documents': [{ supports: R.DOCUMENT_CONTROL, weight: 2, reason: 'La centralisation des documents est un résultat recherché.' }],
        'digital:reporting': [{ supports: R.DATA_REPORTING, weight: 3, reason: 'Le besoin porte sur la consolidation et la lecture des données.' }],
        'digital:reentry': [{ supports: R.AUTOMATION, weight: 3, reason: 'La double saisie est une source explicite de perte de temps.' }],
        'digital:traceability': [{ supports: R.DIGITAL_WORKFLOWS, weight: 2, reason: 'Le manque de traçabilité est une difficulté déclarée.' }]
    });

    const RULES = Object.freeze([
        { id: R.COST_CONTROL, any: [{ field: 'facts', includes: 'project:cost-delay' }] },
        { id: R.PLANNING_WBS, any: [{ field: 'facts', includes: 'project:cost-delay' }, { field: 'facts', includes: 'delay:planning-cause' }, { field: 'facts', includes: 'delay:resource-cause' }, { field: 'facts', includes: 'project:resources' }] },
        { id: R.EXECUTIVE_REPORTING, any: [{ field: 'facts', includes: 'project:executive-reporting' }, { field: 'facts', includes: 'project:fragmented-tools' }, { field: 'facts', includes: 'project:portfolio' }, { field: 'facts', includes: 'project:delay' }, { field: 'facts', includes: 'delay:multiple-causes' }, { field: 'facts', includes: 'delay:unknown' }] },
        { id: R.ENGINEERING_DOCUMENTS, any: [{ field: 'facts', includes: 'engineering:documents-risk' }, { field: 'facts', includes: 'delay:engineering-cause' }], none: [{ field: 'facts', includes: 'project:cost-only' }] },
        { id: R.PROCUREMENT, any: [{ field: 'facts', includes: 'procurement:delay' }, { field: 'facts', includes: 'procurement:process' }] },
        { id: R.QA_QC, any: [{ field: 'facts', includes: 'quality:qa-qc-issue' }] },
        { id: R.ROLES, any: [{ field: 'facts', includes: 'organisation:roles-unclear' }] },
        { id: R.PROCESS, any: [{ field: 'facts', includes: 'organisation:process-variable' }, { field: 'facts', includes: 'management:fragmented' }, { field: 'facts', includes: 'organisation:roles-unclear' }] },
        { id: R.DECISION_WORKFLOWS, any: [{ field: 'facts', includes: 'organisation:approvals-unclear' }, { field: 'facts', includes: 'delay:approval-cause' }] },
        { id: R.MANAGEMENT_DASHBOARD, any: [{ field: 'facts', includes: 'organisation:reporting' }, { field: 'facts', includes: 'management:indicators' }] },
        { id: R.DOCUMENT_CONTROL, any: [{ field: 'facts', includes: 'management:documents' }, { field: 'facts', includes: 'digital:documents' }] },
        { id: R.QUALITY_MANAGEMENT, any: [{ field: 'facts', includes: 'management:system' }] },
        { id: R.AUDIT_ACTIONS, any: [{ field: 'facts', includes: 'management:audits-actions' }] },
        { id: R.DIGITAL_WORKFLOWS, any: [{ field: 'facts', includes: 'digital:approvals' }, { field: 'facts', includes: 'digital:traceability' }, { field: 'facts', includes: 'organisation:approvals-unclear' }] },
        { id: R.AUTOMATION, any: [{ field: 'facts', includes: 'digital:reentry' }] },
        { id: R.DATA_REPORTING, any: [{ field: 'facts', includes: 'digital:reporting' }, { field: 'facts', includes: 'project:fragmented-tools' }] }
    ]);

    function evaluateEvidence(facts) {
        const evidence = [];
        facts.forEach(function (fact) {
            (EVIDENCE_DEFINITIONS[fact] || []).forEach(function (definition) {
                evidence.push({ source: fact, supports: definition.supports, weight: definition.weight, reason: definition.reason });
            });
        });
        return evidence;
    }

    function blockingFor(recommendationId, uncertainties) {
        const ids = new Set(uncertainties.map(function (item) { return item.id; }));
        const causalProjectRecommendations = [R.PLANNING_WBS, R.PROCUREMENT, R.ENGINEERING_DOCUMENTS, R.DECISION_WORKFLOWS];
        return causalProjectRecommendations.includes(recommendationId)
            && (ids.has('project-delay-cause') || ids.has('project-delay-multiple') || ids.has('subcontractor-delay-nature'));
    }

    function rankRecommendations(facts, evidence, uncertainties, peopleGuardrail) {
        if (peopleGuardrail) return { immediate: [], explore: [] };
        const candidates = ruleApi.matchingRules({ facts: facts }, RULES).map(function (rule) {
            const recommendationEvidence = evidence.filter(function (item) { return item.supports === rule.id; });
            if (!recommendationEvidence.length) return null;
            const evidenceScore = recommendationEvidence.reduce(function (total, item) { return total + item.weight; }, 0);
            let strength = evidenceScore >= 3 || recommendationEvidence.length >= 2 ? 'high' : (evidenceScore >= 2 ? 'medium' : 'low');
            if (blockingFor(rule.id, uncertainties)) strength = 'low';
            const definition = RECOMMENDATIONS[rule.id];
            return {
                id: rule.id,
                label: definition.label,
                businessDomain: definition.businessDomain,
                capability: definition.capability,
                family: definition.family || null,
                strength: strength,
                evidence: recommendationEvidence.map(function (item) { return { source: item.source, reason: item.reason }; }),
                score: evidenceScore
            };
        }).filter(Boolean).sort(function (left, right) {
            const strengthRank = { high: 3, medium: 2, low: 1 };
            return strengthRank[right.strength] - strengthRank[left.strength] || right.score - left.score || left.label.localeCompare(right.label);
        });

        const immediate = [];
        const deferred = [];
        const usedFamilies = new Set();
        candidates.forEach(function (candidate) {
            const familyConflict = candidate.family && usedFamilies.has(candidate.family);
            if (candidate.strength !== 'low' && candidate.score >= 2 && immediate.length < MAX_IMMEDIATE && !familyConflict) {
                immediate.push(candidate);
                if (candidate.family) usedFamilies.add(candidate.family);
            } else {
                deferred.push(candidate);
            }
        });
        return { immediate: immediate, explore: deferred.slice(0, MAX_EXPLORE) };
    }

    return {
        MAX_IMMEDIATE: MAX_IMMEDIATE,
        MAX_EXPLORE: MAX_EXPLORE,
        RECOMMENDATIONS: RECOMMENDATIONS,
        EVIDENCE_DEFINITIONS: EVIDENCE_DEFINITIONS,
        RULES: RULES,
        evaluateEvidence: evaluateEvidence,
        rankRecommendations: rankRecommendations
    };
});
