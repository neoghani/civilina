(function (root, factory) {
    'use strict';
    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory(
        isCommonJs ? require('./civilina-ai-ontology.js') : root.CivilinaAIOntology,
        isCommonJs ? require('./civilina-ai-privacy.js') : root.CivilinaAIPrivacy
    );
    if (isCommonJs) module.exports = api;
    else root.CivilinaAIFactsV2 = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (ontology, privacy) {
    'use strict';

    const MODE_PRIORITY = ['decide', 'calculate', 'critique', 'audit', 'review', 'create', 'design', 'compare', 'prioritize', 'plan', 'troubleshoot', 'diagnose', 'explain'];
    const PERSONA_PRIORITY = ['executive', 'contractManager', 'hseManager', 'qualityManager', 'procurementManager', 'engineeringManager', 'siteManager', 'projectControls', 'pmo', 'projectManager', 'isoManager', 'digitalManager', 'peopleManager'];
    const SCOPE_PRIORITY = ['person', 'team', 'activity', 'workPackage', 'zone', 'ouvrage', 'wbs', 'project', 'program', 'portfolio', 'department', 'process', 'enterprise'];

    const EVIDENCE_PACKS = Object.freeze({
        wbs: ['structure/codes', 'levels', 'scope representation'],
        planning: ['activities', 'logic', 'dates', 'constraints', 'calendars', 'baseline/current'],
        costControl: ['budget', 'commitments', 'actual', 'forecast'],
        procurement: ['requirement dates', 'PO/delivery dates', 'lead times'],
        quality: ['ITP', 'NCR', 'inspection records'],
        contractsClaims: ['event', 'contract basis', 'notices', 'records', 'time/cost impact'],
        dataGovernance: ['KPI definition', 'data owner', 'source lineage', 'reconciliation evidence']
    });

    function unique(values) { return Array.from(new Set(values)); }
    function firstByPriority(values, priorities, fallback) {
        return priorities.find(function (value) { return values.includes(value); }) || fallback;
    }

    function addScores(scores, ids, weight) {
        (ids || []).forEach(function (id) { scores[id] = (scores[id] || 0) + weight; });
    }

    function rankScores(scores, threshold) {
        return Object.keys(scores).filter(function (id) { return scores[id] >= threshold; }).sort(function (left, right) {
            return scores[right] - scores[left] || left.localeCompare(right);
        });
    }

    function buildDimensions(recognition, input) {
        const categories = recognition.byCategory;
        const modes = categories.interactionMode || [];
        const interactionMode = firstByPriority(modes, MODE_PRIORITY, 'diagnose');
        const persona = firstByPriority(categories.persona || [], PERSONA_PRIORITY, 'unknown');
        const scopeSignals = categories.scope || [];
        const scopeLevel = firstByPriority(scopeSignals, SCOPE_PRIORITY, scopeSignals[0] || 'enterprise');
        const entitySignals = unique((categories.entity || []).concat(scopeSignals.filter(function (id) { return ontology.BUSINESS_ENTITIES.includes(id); })));
        const concepts = recognition.concepts.slice();
        const domainScores = {};
        const capabilityScores = {};
        const dimensions = [];
        const mappedEntities = [];

        concepts.forEach(function (concept) {
            const map = ontology.CONCEPT_MAP[concept];
            if (!map) return;
            addScores(domainScores, map.domains, 2);
            addScores(capabilityScores, map.capabilities, 2);
            dimensions.push.apply(dimensions, map.dimensions || []);
            mappedEntities.push.apply(mappedEntities, map.entities || []);
        });
        if (persona === 'executive') addScores(domainScores, ['executiveControl'], 4);
        if (entitySignals.some(function (id) { return ['project', 'wbs', 'workPackage', 'activity', 'ouvrage', 'zone'].includes(id); })) addScores(domainScores, ['projects'], 2);
        if ((categories.scope || []).includes('department')) addScores(domainScores, ['organisation'], 1);

        const domainRanking = rankScores(domainScores, 1);
        const primaryDomain = domainRanking[0] || null;
        const relatedDomains = domainRanking.filter(function (id) { return id !== primaryDomain && domainScores[id] >= 2; }).slice(0, 4);
        const capabilities = rankScores(capabilityScores, 2).slice(0, 8);
        const controlDimensions = unique(dimensions).filter(function (id) { return ontology.CONTROL_DIMENSIONS.includes(id); });
        const entities = unique(entitySignals.concat(mappedEntities)).filter(function (id) { return ontology.BUSINESS_ENTITIES.includes(id); });

        const symptoms = unique(categories.symptom || []);
        const suspectedCauses = recognition.suspectedConcepts.map(function (id) { return 'concept:' + id; });
        const confirmedCauses = recognition.confirmedConcepts.map(function (id) { return 'concept:' + id; });
        const answers = input.answers || {};
        if (answers.causeClarification && answers.causeClarification !== 'unknown') confirmedCauses.push('answer:' + answers.causeClarification);
        const causeUnknown = recognition.unknownCause || (symptoms.includes('delay') && confirmedCauses.length === 0);

        const facts = unique(concepts.map(function (id) { return 'concept:' + id; })
            .concat(entities.map(function (id) { return 'entity:' + id; }))
            .concat(symptoms.map(function (id) { return 'symptom:' + id; })));
        const evidence = recognition.matches.filter(function (match) { return ['concept', 'entity', 'symptom', 'persona', 'scope'].includes(match.category); }).map(function (match) {
            return { source: match.id, concept: match.value, reason: 'Signal explicite reconnu : ' + match.terms[0] + '.', strength: 'direct' };
        });

        const uncertainties = [];
        if (!primaryDomain) uncertainties.push({ id: 'DOMAIN_UNKNOWN', blocking: true, message: 'Le domaine principal reste à préciser.' });
        if (causeUnknown) uncertainties.push({ id: 'CAUSE_UNKNOWN', blocking: true, message: 'Le symptôme est identifié, mais sa cause n’est pas suffisamment établie.' });
        if (recognition.suspectedConcepts.length) uncertainties.push({ id: 'CAUSE_SUSPECTED_NOT_CONFIRMED', blocking: true, message: 'Une cause est suspectée mais pas confirmée par les éléments disponibles.' });

        const sensitiveIntent = privacy.detectSensitiveIntent(input.freeText || '');
        const peopleSignal = concepts.includes('people') || privacy.detectPeopleSignal(input.freeText || '');
        const peopleGuardrail = privacy.detectPeopleDecision(input.freeText || '') || (peopleSignal && ['decide', 'compare'].includes(interactionMode));
        const safetyCritical = (categories.flag || []).includes('safetyCritical');
        const externalVerificationRequired = (categories.flag || []).includes('externalVerification');
        const contractInterpretation = (categories.flag || []).includes('contractInterpretation');

        return {
            interactionMode,
            persona,
            decisionHorizon: (categories.horizon || [])[0] || 'unknown',
            scope: { level: scopeLevel, entities },
            concepts,
            domainScores,
            capabilityScores,
            primaryDomain,
            relatedDomains,
            capabilities,
            controlDimensions,
            facts,
            symptoms,
            suspectedCauses: unique(suspectedCauses),
            confirmedCauses: unique(confirmedCauses),
            impacts: controlDimensions.filter(function (id) { return ['time', 'cost', 'progress', 'quality', 'risk', 'safety', 'decision'].includes(id); }),
            evidence,
            uncertainties,
            sensitiveIntent,
            peopleGuardrail,
            safetyCritical,
            externalVerificationRequired,
            contractInterpretation,
            hasNumbers: recognition.hasNumbers,
            hasDocumentReference: recognition.hasDocumentReference
        };
    }

    function evidencePackFor(dimensions) {
        const concepts = dimensions.concepts;
        const priority = ['contractsClaims', 'quality', 'procurement', 'planning', 'wbs', 'costControl', 'dataGovernance'];
        const key = priority.find(function (id) { return concepts.includes(id); });
        return key ? { key, required: EVIDENCE_PACKS[key] } : null;
    }

    return { EVIDENCE_PACKS, buildDimensions, evidencePackFor };
});
