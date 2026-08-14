(function (root, factory) {
    'use strict';
    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory(isCommonJs ? require('./civilina-ai-ontology.js') : root.CivilinaAIOntology);
    if (isCommonJs) module.exports = api;
    else root.CivilinaAIQuestionsV2 = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (ontology) {
    'use strict';

    const QUESTION_CATALOG = Object.freeze([
        { id: 'domain-objective', text: 'Quel résultat métier souhaitez-vous obtenir en priorité ?', resolves: ['DOMAIN_UNKNOWN'], uncertaintyReduction: 5, causalDiscrimination: 2, decisionImpact: 5, riskImportance: 2, artifactReadiness: 2, userBurden: 1 },
        { id: 'observed-symptom', text: 'Quel symptôme observable vous conduit à demander cette analyse ?', resolves: ['SYMPTOM_UNKNOWN'], uncertaintyReduction: 4, causalDiscrimination: 4, decisionImpact: 4, riskImportance: 3, artifactReadiness: 2, userBurden: 1 },
        { id: 'cause-evidence', text: 'Quels faits permettent de confirmer la cause, au-delà d’une simple hypothèse ?', resolves: ['CAUSE_UNKNOWN', 'CAUSE_SUSPECTED_NOT_CONFIRMED'], uncertaintyReduction: 5, causalDiscrimination: 5, decisionImpact: 5, riskImportance: 4, artifactReadiness: 2, userBurden: 2 },
        { id: 'decision-required', text: 'Quelle décision devez-vous préparer, et pour quelle échéance ?', resolves: ['DECISION_UNKNOWN'], uncertaintyReduction: 3, causalDiscrimination: 2, decisionImpact: 5, riskImportance: 3, artifactReadiness: 3, userBurden: 1 },
        { id: 'review-document', text: 'Pouvez-vous préciser le document, la version et les critères à examiner ?', resolves: ['DOCUMENT_REQUIRED'], uncertaintyReduction: 4, causalDiscrimination: 3, decisionImpact: 4, riskImportance: 3, artifactReadiness: 5, userBurden: 2 },
        { id: 'calculation-inputs', text: 'Quelles valeurs, unités, périodes et hypothèses doivent être utilisées pour le calcul ?', resolves: ['CALCULATION_DATA_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 2, decisionImpact: 5, riskImportance: 4, artifactReadiness: 5, userBurden: 2 },
        { id: 'creation-audience', text: 'À qui le livrable est-il destiné et quelle décision doit-il soutenir ?', resolves: ['CREATION_AUDIENCE_REQUIRED'], uncertaintyReduction: 3, causalDiscrimination: 1, decisionImpact: 5, riskImportance: 2, artifactReadiness: 5, userBurden: 1 },
        { id: 'creation-format', text: 'Quel format, niveau de détail et horizon attendez-vous pour ce livrable ?', resolves: ['CREATION_FORMAT_REQUIRED'], uncertaintyReduction: 3, causalDiscrimination: 1, decisionImpact: 4, riskImportance: 2, artifactReadiness: 5, userBurden: 1 },
        { id: 'wbs-alignment', text: 'La WBS représente-t-elle le périmètre à produire, et comment est-elle reliée aux zones et ouvrages ?', resolves: ['WBS_ALIGNMENT_REQUIRED'], uncertaintyReduction: 4, causalDiscrimination: 4, decisionImpact: 4, riskImportance: 3, artifactReadiness: 3, userBurden: 2 },
        { id: 'progress-basis', text: 'Parlez-vous d’avancement planifié, exécuté, accepté, valorisé ou prévisionnel ?', resolves: ['PROGRESS_BASIS_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 5, decisionImpact: 5, riskImportance: 4, artifactReadiness: 3, userBurden: 1 },
        { id: 'evm-period', text: 'Quelle est la date de statut et disposez-vous de PV, EV et AC sur un même périmètre ?', resolves: ['EVM_DATA_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 4, decisionImpact: 5, riskImportance: 4, artifactReadiness: 5, userBurden: 2 },
        { id: 'contract-basis', text: 'Quel événement, quelle clause, quelle notification et quelles preuves contemporaines encadrent la situation ?', resolves: ['CONTRACT_BASIS_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 5, decisionImpact: 5, riskImportance: 5, artifactReadiness: 4, userBurden: 3 },
        { id: 'safety-control', text: 'Le danger est-il immédiatement maîtrisé et quelles mesures de protection sont actuellement en place ?', resolves: ['SAFETY_CONTROL_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 3, decisionImpact: 5, riskImportance: 5, artifactReadiness: 2, userBurden: 1 },
        { id: 'people-criteria', text: 'Quels critères professionnels vérifiables, indépendants du MBTI, doivent guider cette décision ?', resolves: ['PEOPLE_CRITERIA_REQUIRED'], uncertaintyReduction: 4, causalDiscrimination: 3, decisionImpact: 5, riskImportance: 5, artifactReadiness: 4, userBurden: 2 },
        { id: 'data-source', text: 'Quelle source fait autorité, qui en est responsable et comment les écarts sont-ils réconciliés ?', resolves: ['DATA_LINEAGE_REQUIRED'], uncertaintyReduction: 5, causalDiscrimination: 4, decisionImpact: 5, riskImportance: 4, artifactReadiness: 4, userBurden: 2 }
    ]);

    function unique(values) { return Array.from(new Set(values)); }

    function openIssues(dimensions) {
        const issues = (dimensions.uncertainties || []).map(function (item) { return item.id; });
        if (!dimensions.primaryDomain) issues.push('DOMAIN_UNKNOWN');
        if (!dimensions.symptoms.length && ['diagnose', 'troubleshoot'].includes(dimensions.interactionMode)) issues.push('SYMPTOM_UNKNOWN');
        if (!dimensions.controlDimensions.includes('decision')) issues.push('DECISION_UNKNOWN');
        if (['review', 'critique', 'audit'].includes(dimensions.interactionMode) && !dimensions.hasDocumentReference) issues.push('DOCUMENT_REQUIRED');
        if (dimensions.interactionMode === 'calculate' && !dimensions.hasNumbers) issues.push('CALCULATION_DATA_REQUIRED');
        if (['create', 'design'].includes(dimensions.interactionMode)) {
            issues.push('CREATION_AUDIENCE_REQUIRED', 'CREATION_FORMAT_REQUIRED');
        }
        if (dimensions.concepts.includes('wbs') && !dimensions.scope.entities.some(function (id) { return ['zone', 'ouvrage'].includes(id); })) issues.push('WBS_ALIGNMENT_REQUIRED');
        if (dimensions.concepts.includes('progressControl') && dimensions.controlDimensions.filter(function (id) { return id === 'progress'; }).length && !dimensions.facts.some(function (fact) { return /planned|executed|accepted|valued|forecast/.test(fact); })) issues.push('PROGRESS_BASIS_REQUIRED');
        if (dimensions.concepts.includes('evm') && !dimensions.hasNumbers) issues.push('EVM_DATA_REQUIRED');
        if (dimensions.contractInterpretation) issues.push('CONTRACT_BASIS_REQUIRED');
        if (dimensions.safetyCritical) issues.push('SAFETY_CONTROL_REQUIRED');
        if (dimensions.peopleGuardrail) issues.push('PEOPLE_CRITERIA_REQUIRED');
        if (dimensions.concepts.includes('dataGovernance')) issues.push('DATA_LINEAGE_REQUIRED');
        return unique(issues);
    }

    function score(question, issueSet, knownAnswers) {
        const overlap = question.resolves.filter(function (issue) { return issueSet.has(issue); }).length;
        if (!overlap) return -Infinity;
        const alreadyKnownPenalty = (knownAnswers || {})[question.id] ? 20 : 0;
        return overlap * 8
            + question.uncertaintyReduction * 3
            + question.causalDiscrimination * 2
            + question.decisionImpact * 3
            + question.riskImportance * 2
            + question.artifactReadiness
            - question.userBurden * 2
            - alreadyKnownPenalty;
    }

    function questionBudget(dimensions) {
        const complexity = dimensions.relatedDomains.length + dimensions.uncertainties.length + dimensions.controlDimensions.length;
        if (complexity >= 10) return 7;
        if (complexity >= 6) return 5;
        return 3;
    }

    function rank(dimensions, knownAnswers) {
        const issues = openIssues(dimensions);
        const issueSet = new Set(issues);
        return QUESTION_CATALOG.map(function (question) {
            return Object.assign({}, question, { decisionValue: score(question, issueSet, knownAnswers) });
        }).filter(function (question) { return Number.isFinite(question.decisionValue); })
            .sort(function (left, right) { return right.decisionValue - left.decisionValue || left.id.localeCompare(right.id); })
            .slice(0, questionBudget(dimensions));
    }

    function select(dimensions, knownAnswers) {
        const ranked = rank(dimensions, knownAnswers);
        return ranked.length ? ranked[0] : null;
    }

    return { QUESTION_CATALOG, openIssues, questionBudget, rank, select };
});
