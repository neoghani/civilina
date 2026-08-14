(function (root, factory) {
    'use strict';

    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory(
        isCommonJs ? require('./civilina-ai-domains.js') : root.CivilinaAIDomains,
        isCommonJs ? require('./civilina-ai-recommendations.js') : root.CivilinaAIRecommendations,
        isCommonJs ? require('./civilina-ai-privacy.js') : root.CivilinaAIPrivacy
    );
    if (isCommonJs) module.exports = api;
    else root.CivilinaAIReasoning = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (domainApi, recommendationApi, privacyApi) {
    'use strict';

    const D = domainApi.BUSINESS_DOMAIN_IDS;
    const C = domainApi.CAPABILITY_IDS;
    const REASONING_CORE_VERSION = '1.0.0';

    const FACT_SIGNAL_WEIGHTS = Object.freeze({
        'domain:projects': { domains: { projects: 2 } },
        'domain:organisation': { domains: { organisation: 3 } },
        'domain:managementSystems': { domains: { managementSystems: 3 }, capabilities: { quality: 1 } },
        'domain:digitalisation': { domains: { digitalisation: 3 }, capabilities: { data: 1 } },
        'domain:people': { domains: { people: 3 } },
        'activity:epc': { domains: { projects: 1 }, capabilities: { engineering: 1 } },
        'activity:industry': { domains: { projects: 1 } },
        'project:portfolio': { domains: { projects: 2 }, capabilities: { projectControls: 1, data: 1 } },
        'project:fragmented-tools': { domains: { projects: 1, digitalisation: 1 }, capabilities: { projectControls: 2, data: 2 } },
        'project:cost-delay': { domains: { projects: 3 }, capabilities: { projectControls: 3 } },
        'project:delay': { domains: { projects: 3 }, capabilities: { projectControls: 2 } },
        'delay:planning-cause': { domains: { projects: 2 }, capabilities: { projectControls: 3 } },
        'delay:resource-cause': { domains: { projects: 2 }, capabilities: { projectControls: 2 } },
        'delay:engineering-cause': { domains: { projects: 2 }, capabilities: { engineering: 3 } },
        'delay:approval-cause': { domains: { projects: 1, organisation: 2 }, capabilities: { data: 2 } },
        'delay:subcontractor-context': { domains: { projects: 2 }, capabilities: { projectControls: 1, procurement: 1 } },
        'delay:multiple-causes': { domains: { projects: 2 }, capabilities: { projectControls: 2, data: 2 } },
        'delay:unknown': { domains: { projects: 2 }, capabilities: { projectControls: 1, data: 1 } },
        'project:executive-reporting': { domains: { projects: 2 }, capabilities: { projectControls: 2, data: 3 } },
        'project:resources': { domains: { projects: 2 }, capabilities: { projectControls: 2 } },
        'engineering:documents-risk': { domains: { projects: 2 }, capabilities: { engineering: 3 } },
        'procurement:delay': { domains: { projects: 2 }, capabilities: { procurement: 3 } },
        'quality:qa-qc-issue': { domains: { managementSystems: 1 }, capabilities: { quality: 3 } },
        'organisation:roles-unclear': { domains: { organisation: 3, people: 1 } },
        'organisation:approvals-unclear': { domains: { organisation: 3, digitalisation: 2 }, capabilities: { data: 1 } },
        'organisation:process-variable': { domains: { organisation: 3 } },
        'organisation:reporting': { domains: { organisation: 2, digitalisation: 2 }, capabilities: { data: 2 } },
        'organisation:process-structured': { domains: { organisation: 2 } },
        'management:system': { domains: { managementSystems: 3 }, capabilities: { quality: 2 } },
        'management:fragmented': { domains: { managementSystems: 2, organisation: 1 }, capabilities: { quality: 2 } },
        'management:documents': { domains: { managementSystems: 2 }, capabilities: { quality: 3, data: 1 } },
        'management:audits-actions': { domains: { managementSystems: 2 }, capabilities: { quality: 3, data: 1 } },
        'management:indicators': { domains: { managementSystems: 2, digitalisation: 2 }, capabilities: { quality: 2, data: 2 } },
        'digital:process': { domains: { digitalisation: 3 }, capabilities: { data: 1 } },
        'digital:approvals': { domains: { digitalisation: 3, organisation: 4 }, capabilities: { data: 2 } },
        'digital:documents': { domains: { digitalisation: 2, managementSystems: 3 }, capabilities: { data: 2, quality: 1 } },
        'digital:reporting': { domains: { digitalisation: 3 }, capabilities: { data: 3 } },
        'digital:reentry': { domains: { digitalisation: 3 }, capabilities: { data: 2 } },
        'digital:traceability': { domains: { digitalisation: 2 }, capabilities: { data: 2, quality: 1 } },
        'procurement:process': { domains: { digitalisation: 1, projects: 1 }, capabilities: { procurement: 3 } },
        'people:signal': { domains: { people: 3 } },
        'people:decision': { domains: { people: 5 } },
        'people:project-context': { domains: { projects: 1 } }
    });

    const QUESTION_IMPACT = Object.freeze({
        projects: { difficulty: 100, tracking: 86, portfolio: 68, activity: 46 },
        organisation: { issue: 100, objective: 90, decisions: 82, size: 38 },
        managementSystems: { difficulty: 100, maturity: 92, reference: 76, tools: 70 },
        digitalisation: { process: 100, difficulty: 94, objective: 84, tools: 76 },
        people: { peopleObjective: 100, peopleCriteria: 92, peopleScope: 72 }
    });

    function normalizeText(text) {
        return String(text || '').normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLocaleLowerCase('fr');
    }

    function addFact(facts, fact) {
        if (fact && !facts.includes(fact)) facts.push(fact);
    }

    function factsFromText(text) {
        const value = normalizeText(text);
        const facts = [];
        if (/\biso\s*(9001|10006|14001|22000|45001)?\b|systeme de management|management system/.test(value)) {
            addFact(facts, 'domain:managementSystems');
            addFact(facts, 'management:system');
        }
        if (/\bprojets?\b|\bepc\b|\bchantier\b|\bifc\b|\bdrawings?\b|technical submittals?/.test(value)) addFact(facts, 'domain:projects');
        const hasDelay = /\bretards?\b|\bdelais?\b|planning\s+(?:en\s+)?retard|planification\s+(?:insuffisante|defaillante)/.test(value);
        if (hasDelay) addFact(facts, 'project:delay');
        if (/\bcouts?\b|maitrise des couts|cost control/.test(value)) addFact(facts, 'project:cost-delay');
        if (/\bapprovisionnements?\b|\bprocurement\b|achats? critiques?/.test(value)) {
            addFact(facts, hasDelay ? 'procurement:delay' : 'procurement:process');
        }
        if (hasDelay && /\b(planification|sequencement|planning)\b.{0,35}\b(cause|origine|provoque|explique)|\b(cause|origine)\b.{0,35}\b(planification|sequencement|planning)\b/.test(value)) addFact(facts, 'delay:planning-cause');
        if (hasDelay && /\b(ressources?|effectifs?|capacite)\b.{0,35}\b(cause|origine|provoque|manque)|\bmanque\s+de\s+(?:ressources?|effectifs?|capacite)\b/.test(value)) addFact(facts, 'delay:resource-cause');
        if (hasDelay && /\b(etudes?|engineering|validations? techniques?|approbations? techniques?)\b.{0,35}\b(cause|origine|retard)|\bretards?\b.{0,35}\b(etudes?|engineering|validations? techniques?)\b/.test(value)) addFact(facts, 'delay:engineering-cause');
        if (hasDelay && /\b(decisions?|approbations?|validations?)\b.{0,35}\b(lentes?|retard|cause)|\bretards?\b.{0,35}\b(decisions?|approbations?|validations?)\b/.test(value)) addFact(facts, 'delay:approval-cause');
        if (hasDelay && /\b(sous[- ]?traitants?|subcontractors?|sous[- ]?traitance)\b/.test(value)) addFact(facts, 'delay:subcontractor-context');
        if (/\bdigitalis|automatis|workflow|erp\b|donnees dispersees/.test(value)) addFact(facts, 'domain:digitalisation');
        if (/\bresponsabilit|roles? (peu |pas )?clairs?|qui valide|validation.*(flou|clair)/.test(value)) addFact(facts, 'organisation:roles-unclear');
        if (/\borganisation|processus|methodes? de travail/.test(value)) addFact(facts, 'domain:organisation');
        if (/processus (?:sont )?(?:bien )?(?:structures?|formalises?)/.test(value)) addFact(facts, 'organisation:process-structured');
        const managementDocuments = /\bdocument control\b|\bcontrole documentaire\b|\bmaitrise documentaire\b|\bgestion documentaire\b|\bversions? documentaires?\b|\bdocuments? qualite\b|\bdocumentation qualite\b|\bged\b/.test(value);
        const technicalDocuments = /\bplans? ifc\b|\bdrawings?\b|\btechnical submittals?\b|\bdocuments? techniques?\b|\bdossiers? techniques?\b/.test(value);
        if (managementDocuments) addFact(facts, 'management:documents');
        if (technicalDocuments || /\brisques? et documents?\b/.test(value)) addFact(facts, 'engineering:documents-risk');
        if (managementDocuments && /\bdigitalis|centralis|dematerialis|workflow|ged\b/.test(value)) addFact(facts, 'digital:documents');
        if (/\bqa\/?qc|controle qualite|non[- ]conformit/.test(value)) addFact(facts, 'quality:qa-qc-issue');
        if (privacyApi.detectPeopleSignal(text)) {
            addFact(facts, 'domain:people');
            addFact(facts, 'people:signal');
        }
        if (privacyApi.detectPeopleDecision(text)) addFact(facts, 'people:decision');
        if (/\bchef de chantier\b/.test(value) && privacyApi.detectPeopleSignal(text)) addFact(facts, 'people:project-context');
        return facts;
    }

    function deriveFacts(entryIntent, answers, freeText, seedFacts) {
        const facts = Array.isArray(seedFacts) ? seedFacts.slice() : [];
        factsFromText(freeText).forEach(function (fact) { addFact(facts, fact); });
        const value = function (field) { return answers ? answers[field] : undefined; };
        if (entryIntent) addFact(facts, 'domain:' + entryIntent);

        if (value('activity') === 'epc') addFact(facts, 'activity:epc');
        if (value('activity') === 'industry') addFact(facts, 'activity:industry');
        if (['medium', 'large', 'mixed'].includes(value('portfolio'))) addFact(facts, 'project:portfolio');
        if (['excel', 'multiple-tools', 'informal'].includes(value('tracking'))) addFact(facts, 'project:fragmented-tools');
        if (entryIntent === D.PROJECTS) {
            if (value('difficulty') === 'cost-schedule') addFact(facts, 'project:cost-delay');
            if (value('difficulty') === 'executive-reporting') addFact(facts, 'project:executive-reporting');
            if (value('difficulty') === 'resources') addFact(facts, 'project:resources');
            if (value('difficulty') === 'risks-documents') addFact(facts, 'engineering:documents-risk');
        }
        if (value('delayCause')) addFact(facts, 'project:delay');
        if (value('delayCause') === 'planning') addFact(facts, 'delay:planning-cause');
        if (value('delayCause') === 'procurement') addFact(facts, 'procurement:delay');
        if (value('delayCause') === 'resources') addFact(facts, 'delay:resource-cause');
        if (value('delayCause') === 'engineering') addFact(facts, 'delay:engineering-cause');
        if (value('delayCause') === 'approvals') addFact(facts, 'delay:approval-cause');
        if (value('delayCause') === 'subcontracting') addFact(facts, 'delay:subcontractor-context');
        if (['both', 'multiple'].includes(value('delayCause'))) addFact(facts, 'delay:multiple-causes');
        if (value('delayCause') === 'unknown') addFact(facts, 'delay:unknown');
        if (value('subcontractorBlockage') === 'capacity') addFact(facts, 'delay:resource-cause');
        if (value('subcontractorBlockage') === 'procurement-interface') addFact(facts, 'procurement:delay');
        if (value('subcontractorBlockage') === 'technical-approval') addFact(facts, 'delay:engineering-cause');
        if (value('subcontractorBlockage') === 'quality') addFact(facts, 'quality:qa-qc-issue');

        if (entryIntent === D.ORGANISATION) {
            if (value('issue') === 'roles') addFact(facts, 'organisation:roles-unclear');
            if (value('issue') === 'approvals') addFact(facts, 'organisation:approvals-unclear');
            if (value('issue') === 'processes') addFact(facts, 'organisation:process-variable');
            if (value('issue') === 'reporting') addFact(facts, 'organisation:reporting');
            if (['email', 'verbal'].includes(value('decisions'))) addFact(facts, 'organisation:approvals-unclear');
            if (value('objective') === 'clarify-roles') addFact(facts, 'organisation:roles-unclear');
            if (value('objective') === 'standardize') addFact(facts, 'organisation:process-variable');
            if (value('objective') === 'management-dashboard') addFact(facts, 'organisation:reporting');
        }

        if (entryIntent === D.MANAGEMENT_SYSTEMS) {
            if (value('reference')) addFact(facts, 'management:system');
            if (['documented', 'fragmented'].includes(value('maturity'))) addFact(facts, 'management:fragmented');
            if (value('difficulty') === 'documents') addFact(facts, 'management:documents');
            if (value('difficulty') === 'audits-actions') addFact(facts, 'management:audits-actions');
            if (value('difficulty') === 'indicators') addFact(facts, 'management:indicators');
            if (value('difficulty') === 'roles') addFact(facts, 'organisation:roles-unclear');
        }

        if (entryIntent === D.DIGITALISATION) {
            if (value('process')) addFact(facts, 'digital:process');
            if (value('process') === 'approvals') addFact(facts, 'digital:approvals');
            if (value('process') === 'documents') addFact(facts, 'digital:documents');
            if (value('process') === 'reporting') addFact(facts, 'digital:reporting');
            if (value('process') === 'procurement') addFact(facts, 'procurement:process');
            if (value('difficulty') === 'reentry') addFact(facts, 'digital:reentry');
            if (['traceability', 'delays'].includes(value('difficulty'))) addFact(facts, 'digital:traceability');
            if (value('objective') === 'workflow') addFact(facts, 'digital:approvals');
            if (value('objective') === 'centralize') addFact(facts, 'digital:documents');
            if (value('objective') === 'dashboard') addFact(facts, 'digital:reporting');
            if (value('objective') === 'automation') addFact(facts, 'digital:reentry');
        }

        if (entryIntent === D.PEOPLE) {
            if (value('peopleObjective') === 'staffing-decision') addFact(facts, 'people:decision');
            else if (value('peopleObjective')) addFact(facts, 'people:signal');
        }
        if (value('processMaturity') === 'structured') addFact(facts, 'organisation:process-structured');
        if (value('responsibilityClarity') === 'low') addFact(facts, 'organisation:roles-unclear');
        if (value('qualityIssue') === 'qa-qc') addFact(facts, 'quality:qa-qc-issue');
        if (facts.includes('project:cost-delay') && !facts.includes('engineering:documents-risk') && !facts.includes('procurement:delay')) addFact(facts, 'project:cost-only');
        return facts;
    }

    function emptySignals(registry) {
        return Object.keys(registry).reduce(function (result, key) { result[key] = 0; return result; }, {});
    }

    function evaluateSignals(entryIntent, facts) {
        const domainSignals = emptySignals(domainApi.BUSINESS_DOMAINS);
        const capabilitySignals = emptySignals(domainApi.CAPABILITIES);
        if (entryIntent && domainApi.BUSINESS_DOMAINS[entryIntent]) domainSignals[entryIntent] += 3;
        facts.forEach(function (fact) {
            const weights = FACT_SIGNAL_WEIGHTS[fact] || {};
            Object.keys(weights.domains || {}).forEach(function (id) { domainSignals[id] += weights.domains[id]; });
            Object.keys(weights.capabilities || {}).forEach(function (id) { capabilitySignals[id] += weights.capabilities[id]; });
        });
        return { domains: domainSignals, capabilities: capabilitySignals };
    }

    function confidenceFor(signals) {
        const highest = Math.max.apply(null, Object.values(signals).concat([1]));
        return Object.keys(signals).reduce(function (result, id) {
            result[id] = Math.min(1, Number((signals[id] / Math.max(8, highest)).toFixed(2)));
            return result;
        }, {});
    }

    function evaluateConfidence(signals) {
        return { domains: confidenceFor(signals.domains), capabilities: confidenceFor(signals.capabilities) };
    }

    function evaluateDomains(domainSignals, entryIntent) {
        const ranking = Object.keys(domainSignals).sort(function (left, right) { return domainSignals[right] - domainSignals[left]; });
        const primaryDomain = ranking[0] && domainSignals[ranking[0]] >= 2 ? ranking[0] : (entryIntent || null);
        const primaryScore = primaryDomain ? domainSignals[primaryDomain] : 0;
        const relatedDomains = ranking.filter(function (id) {
            return id !== primaryDomain && domainSignals[id] >= 2 && domainSignals[id] >= primaryScore * 0.25;
        }).slice(0, 3);
        return { primaryDomain: primaryDomain, relatedDomains: relatedDomains };
    }

    function evaluateCapabilities(capabilitySignals, evidence, recommendations) {
        const evidencedCapabilities = new Set();
        (evidence || []).forEach(function (item) {
            const definition = recommendationApi.RECOMMENDATIONS[item.supports];
            if (definition && definition.capability) evidencedCapabilities.add(definition.capability);
        });
        const recommendedCapabilities = new Set();
        const ranked = recommendations ? recommendations.immediate.concat(recommendations.explore) : [];
        ranked.forEach(function (item) { if (item.capability) recommendedCapabilities.add(item.capability); });
        return Object.keys(capabilitySignals).filter(function (id) {
            return capabilitySignals[id] >= 2 || evidencedCapabilities.has(id) || recommendedCapabilities.has(id);
        }).sort(function (left, right) {
            return capabilitySignals[right] - capabilitySignals[left];
        }).slice(0, 4);
    }

    function detectUncertainties(entryIntent, answers, facts, domains) {
        const uncertainties = [];
        if (!entryIntent || !domains.primaryDomain) uncertainties.push({ id: 'domain-clarification', message: 'Je n’ai pas encore assez d’éléments pour identifier le sujet principal.' });
        const projectDelay = facts.includes('project:delay') || facts.includes('project:cost-delay');
        const explicitCause = facts.some(function (fact) {
            return ['delay:planning-cause', 'delay:resource-cause', 'delay:engineering-cause', 'delay:approval-cause', 'procurement:delay'].includes(fact);
        }) || answers.difficulty === 'cost-schedule';
        const crossDomainProcurementCheck = entryIntent === D.MANAGEMENT_SYSTEMS && facts.includes('procurement:delay') && !answers.delayCause;
        if (projectDelay && (!explicitCause || crossDomainProcurementCheck || facts.includes('delay:unknown'))) {
            uncertainties.push({ id: 'project-delay-cause', blocking: true, message: 'Le retard est un signal. Sa cause principale doit être précisée avant une recommandation causale.' });
        }
        if (facts.includes('delay:multiple-causes')) {
            uncertainties.push({ id: 'project-delay-multiple', blocking: true, message: 'Plusieurs causes sont évoquées ; leur contribution relative doit être clarifiée avant arbitrage.' });
        }
        if (facts.includes('delay:subcontractor-context') && !answers.subcontractorBlockage) {
            uncertainties.push({ id: 'subcontractor-delay-nature', blocking: true, message: 'Le sous-traitant est cité, mais la nature du blocage reste à identifier.' });
        }
        if (entryIntent === D.PEOPLE && answers.peopleCriteria === 'incomplete') {
            uncertainties.push({ id: 'people-evidence-incomplete', blocking: true, message: 'Les éléments objectifs restent incomplets pour éclairer une décision concernant une personne.' });
        }
        return uncertainties;
    }

    function detectContradictions(answers, facts) {
        const contradictions = [];
        if (facts.includes('organisation:process-structured') && facts.includes('organisation:roles-unclear')) {
            contradictions.push({ id: 'structured-process-unclear-responsibilities', message: 'Un point mérite d’être clarifié : vos processus sont décrits comme structurés, mais les responsabilités de validation semblent encore peu définies.' });
        }
        if (answers.maturity === 'improving' && facts.includes('management:fragmented')) {
            contradictions.push({ id: 'improving-yet-fragmented', message: 'Le système est présenté comme engagé dans l’amélioration continue, mais son fonctionnement semble encore fragmenté.' });
        }
        return contradictions;
    }

    function shouldSuggestExpert(domains, uncertainties, contradictions, peopleGuardrail, facts) {
        const strongCrossDomain = domains.relatedDomains.length >= 2 && (
            facts.includes('management:system') || facts.includes('engineering:documents-risk') || facts.includes('digital:process')
        );
        return Boolean(peopleGuardrail || contradictions.length || (uncertainties.length && domains.relatedDomains.length >= 2) || strongCrossDomain);
    }

    function detectEntryIntent(text) {
        const value = normalizeText(text);
        if (privacyApi.detectPeopleSignal(text)) return D.PEOPLE;
        if (/\biso\b|systeme de management|management system/.test(value)) return D.MANAGEMENT_SYSTEMS;
        if (/\bdigitalis|automatis|workflow/.test(value)) return D.DIGITALISATION;
        if (/\borganisation|responsabilit|roles?\b|processus/.test(value)) return D.ORGANISATION;
        if (/\bprojets?\b|\bepc\b|\bchantier\b|project control|planning|portefeuille|\bretards?\b|\bdelais?\b|\bifc\b|\bdrawings?\b|documents? techniques?/.test(value)) return D.PROJECTS;
        return null;
    }

    function dynamicQuestions(reasoning, answers) {
        const ids = (reasoning.uncertainties || []).map(function (item) { return item.id; });
        const result = [];
        if (ids.includes('domain-clarification') && !answers.domainClarification) {
            result.push({
                id: 'domainClarification',
                prompt: 'Quel sujet souhaitez-vous mieux maîtriser en premier ?',
                suggestions: [['Mes projets', D.PROJECTS], ['Mon organisation', D.ORGANISATION], ['Mon système ISO / qualité', D.MANAGEMENT_SYSTEMS], ['Mes processus digitaux', D.DIGITALISATION], ['Une décision concernant des personnes', D.PEOPLE]],
                riskImportance: 90
            });
        }
        if (ids.includes('project-delay-cause') && !answers.delayCause) {
            const facts = reasoning.facts || [];
            let suggestions = [['Planning / séquencement', 'planning'], ['Approvisionnement', 'procurement'], ['Ressources', 'resources'], ['Causes multiples', 'multiple'], ['Je ne sais pas encore', 'unknown']];
            if (facts.includes('delay:engineering-cause') || facts.includes('engineering:documents-risk')) {
                suggestions = [['Études / validations techniques', 'engineering'], ['Planning / séquencement', 'planning'], ['Décisions / approbations', 'approvals'], ['Causes multiples', 'multiple'], ['Je ne sais pas encore', 'unknown']];
            } else if (facts.includes('organisation:approvals-unclear')) {
                suggestions = [['Décisions / approbations', 'approvals'], ['Planning / séquencement', 'planning'], ['Ressources', 'resources'], ['Causes multiples', 'multiple'], ['Je ne sais pas encore', 'unknown']];
            }
            result.push({
                id: 'delayCause',
                prompt: 'Qu’est-ce qui provoque actuellement le plus de retard ?',
                suggestions: suggestions,
                riskImportance: 100
            });
        }
        if (ids.includes('subcontractor-delay-nature') && !answers.subcontractorBlockage) {
            result.push({
                id: 'subcontractorBlockage',
                prompt: 'Quel type de blocage observez-vous chez le sous-traitant ?',
                suggestions: [['Capacité / ressources', 'capacity'], ['Interface achats', 'procurement-interface'], ['Validation technique', 'technical-approval'], ['Qualité d’exécution', 'quality'], ['Je ne sais pas encore', 'unknown']],
                riskImportance: 110
            });
        }
        return result;
    }

    function questionDecisionValue(entryIntent, question, reasoning, answers) {
        const base = (QUESTION_IMPACT[entryIntent] && QUESTION_IMPACT[entryIntent][question.id]) || 10;
        const uncertaintyIds = new Set((reasoning.uncertainties || []).map(function (item) { return item.id; }));
        let uncertaintyReduction = 0;
        let recommendationDiscrimination = Math.round(base * 0.55);
        let domainDiscrimination = Math.round(base * 0.25);
        let riskImportance = question.riskImportance || Math.round(base * 0.2);
        const alreadyKnownPenalty = Object.prototype.hasOwnProperty.call(answers || {}, question.id) ? 1000 : 0;

        if (question.id === 'domainClarification' && uncertaintyIds.has('domain-clarification')) uncertaintyReduction = 180;
        if (question.id === 'delayCause' && uncertaintyIds.has('project-delay-cause')) uncertaintyReduction = 170;
        if (question.id === 'subcontractorBlockage' && uncertaintyIds.has('subcontractor-delay-nature')) uncertaintyReduction = 190;
        if (['delayCause', 'subcontractorBlockage', 'difficulty', 'issue', 'process'].includes(question.id)) recommendationDiscrimination += 35;
        if (question.id === 'domainClarification') domainDiscrimination += 90;
        const capabilities = reasoning.capabilitySignals || {};
        if (question.id === 'tracking' && (capabilities.data || 0) >= 2) recommendationDiscrimination += 18;
        if (question.id === 'tools' && (capabilities.data || 0) >= 2) recommendationDiscrimination += 16;
        return {
            uncertaintyReduction: uncertaintyReduction,
            recommendationDiscrimination: recommendationDiscrimination,
            domainDiscrimination: domainDiscrimination,
            riskImportance: riskImportance,
            alreadyKnownPenalty: alreadyKnownPenalty,
            total: uncertaintyReduction + recommendationDiscrimination + domainDiscrimination + riskImportance - alreadyKnownPenalty
        };
    }

    function questionImpact(entryIntent, question, reasoning, answers) {
        return questionDecisionValue(entryIntent, question, reasoning, answers || {}).total;
    }

    function hasEnoughInformation(reasoning, answers) {
        const blocking = (reasoning.uncertainties || []).some(function (item) { return item.blocking !== false; });
        const immediate = (reasoning.recommendations && reasoning.recommendations.immediate) || [];
        const sufficientlyJustified = immediate.length > 0 && immediate.every(function (item) { return item.score >= 2 && item.evidence.length > 0; });
        const answeredCount = Object.keys(answers || {}).length;
        const factDepth = (reasoning.facts || []).length;
        return Boolean(reasoning.primaryDomain && !blocking && !(reasoning.contradictions || []).length && sufficientlyJustified && (answeredCount >= 4 || factDepth >= 7));
    }

    function nextBestQuestion(input) {
        const answers = input.answers || {};
        if (hasEnoughInformation(input.reasoning, answers)) return null;
        const candidates = dynamicQuestions(input.reasoning, answers);
        (input.questions || []).forEach(function (question) {
            if (!Object.prototype.hasOwnProperty.call(answers, question.id)) {
                candidates.push(Object.assign({}, question));
            }
        });
        candidates.forEach(function (question) {
            question.decisionValue = questionDecisionValue(input.entryIntent, question, input.reasoning, answers);
            question.decisionImpact = question.decisionValue.total;
        });
        candidates.sort(function (left, right) { return right.decisionImpact - left.decisionImpact || left.id.localeCompare(right.id); });
        return candidates[0] || null;
    }

    function evaluate(input) {
        const answers = Object.assign({}, input.answers || {});
        let entryIntent = input.entryIntent || detectEntryIntent(input.freeText || '');
        if (answers.domainClarification && domainApi.ENTRY_DOMAINS.includes(answers.domainClarification)) entryIntent = answers.domainClarification;
        const facts = deriveFacts(entryIntent, answers, input.freeText || '', input.seedFacts || []);
        const signals = evaluateSignals(entryIntent, facts);
        const confidence = evaluateConfidence(signals);
        const domains = evaluateDomains(signals.domains, entryIntent);
        const evidence = recommendationApi.evaluateEvidence(facts);
        const uncertainties = detectUncertainties(entryIntent, answers, facts, domains);
        const contradictions = detectContradictions(answers, facts);
        const sensitiveIntent = privacyApi.detectSensitiveIntent(input.freeText || '');
        const peopleGuardrail = privacyApi.detectPeopleDecision(input.freeText || '') || facts.includes('people:decision');
        const recommendations = recommendationApi.rankRecommendations(facts, evidence, uncertainties, peopleGuardrail);
        const capabilities = evaluateCapabilities(signals.capabilities, evidence, recommendations);
        const priorities = recommendations.immediate.map(function (recommendation) {
            return { title: recommendation.label, reason: recommendation.evidence[0].reason };
        });
        return {
            reasoningCoreVersion: REASONING_CORE_VERSION,
            entryIntent: entryIntent,
            primaryDomain: domains.primaryDomain,
            relatedDomains: domains.relatedDomains,
            capabilities: capabilities,
            domainSignals: signals.domains,
            capabilitySignals: signals.capabilities,
            confidence: confidence,
            facts: facts,
            evidence: evidence,
            uncertainties: uncertainties,
            contradictions: contradictions,
            priorities: priorities,
            recommendations: recommendations,
            sensitiveIntent: sensitiveIntent,
            peopleGuardrail: peopleGuardrail,
            expertSuggested: shouldSuggestExpert(domains, uncertainties, contradictions, peopleGuardrail, facts)
        };
    }

    return {
        REASONING_CORE_VERSION: REASONING_CORE_VERSION,
        FACT_SIGNAL_WEIGHTS: FACT_SIGNAL_WEIGHTS,
        QUESTION_IMPACT: QUESTION_IMPACT,
        normalizeText: normalizeText,
        factsFromText: factsFromText,
        deriveFacts: deriveFacts,
        evaluateSignals: evaluateSignals,
        evaluateConfidence: evaluateConfidence,
        evaluateDomains: evaluateDomains,
        evaluateCapabilities: evaluateCapabilities,
        detectUncertainties: detectUncertainties,
        detectContradictions: detectContradictions,
        detectEntryIntent: detectEntryIntent,
        questionDecisionValue: questionDecisionValue,
        questionImpact: questionImpact,
        hasEnoughInformation: hasEnoughInformation,
        nextBestQuestion: nextBestQuestion,
        evaluate: evaluate
    };
});
