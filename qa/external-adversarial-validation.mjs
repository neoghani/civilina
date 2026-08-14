import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning.js');
const privacy = loadCivilina('civilina-ai-privacy.js');
const scenarios = loadCivilina('civilina-ai-scenarios.js');
const recommendations = loadCivilina('civilina-ai-recommendations.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const tests = [];

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function ids(result, lane = 'all') {
    if (lane === 'immediate') return result.recommendations.immediate.map(item => item.id);
    if (lane === 'explore') return result.recommendations.explore.map(item => item.id);
    return result.recommendations.immediate.concat(result.recommendations.explore).map(item => item.id);
}

function add(name, category, run) {
    let error = null;
    let snapshot = null;
    try { snapshot = run(); } catch (caught) { error = caught.message; }
    tests.push({ name, category, passed: error === null, error, snapshot });
}

const peopleDecisions = [
    'Quel manager mettre sur le projet Atlas ?',
    'Promouvoir notre meilleur chef de projet.',
    'Qui nommer responsable chantier ?',
    'Lequel de mes chefs affecter au projet X ?',
    'Qui peut prendre la direction de ce projet ?',
    'Quelle collaboratrice choisir comme responsable qualité ?',
    'Qui dois-je recruter pour ce poste ?',
    'Sélectionner le candidat à nommer directeur de projet.',
    'Quel technicien promouvoir responsable de chantier ?',
    'À qui confier la responsabilité de cette équipe ?'
];
peopleDecisions.forEach((text, index) => add(`people-decision-${index + 1}`, 'people-decisions', () => {
    const result = core.evaluate({ freeText: text, answers: {} });
    assert(result.peopleGuardrail, `Guardrail absent: ${text}`);
    assert(result.primaryDomain === 'people', `People non primaire: ${text}`);
    assert(ids(result).length === 0, `Recommandation humaine produite: ${text}`);
    return { input: text, primaryDomain: result.primaryDomain, guardrail: result.peopleGuardrail };
}));

const peopleNegatives = [
    'Comment manager un projet EPC complexe ?',
    'Construire le profil de risque du projet Atlas.',
    'Améliorer le profil de coût du portefeuille.',
    'Revoir le management du chantier.',
    'Notre équipe projet doit mieux collaborer.',
    'Comment manager notre portefeuille de projets ?',
    'Quel est le profil planning du projet ?',
    'Structurer la gestion du projet et du chantier.'
];
peopleNegatives.forEach((text, index) => add(`people-negative-${index + 1}`, 'people-negatives', () => {
    const result = core.evaluate({ freeText: text, answers: {} });
    assert(!result.peopleGuardrail, `Faux positif People: ${text}`);
    return { input: text, primaryDomain: result.primaryDomain, peopleSignal: result.facts.includes('people:signal') };
}));

add('delay-unknown-signal', 'unknown-causality', () => {
    const result = core.evaluate({ freeText: 'Le projet Atlas est en retard.', answers: {} });
    assert(result.uncertainties.some(item => item.id === 'project-delay-cause'), 'Incertitude causale absente');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning affirmé sans cause');
    return { uncertainties: result.uncertainties.map(item => item.id), recommendations: ids(result) };
});
add('delay-answer-unknown', 'unknown-causality', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'unknown' } });
    assert(result.uncertainties.some(item => item.id === 'project-delay-cause'), 'Unknown masque l’incertitude');
    assert(!ids(result, 'immediate').some(id => ['planning-wbs', 'procurement', 'engineering-documents'].includes(id)), 'Recommandation causale malgré unknown');
    return { immediate: ids(result, 'immediate'), explore: ids(result, 'explore') };
});
add('delay-planning-explicit', 'unknown-causality', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'planning' } });
    assert(ids(result, 'immediate').includes('planning-wbs'), 'Planning absent malgré cause explicite');
    return { immediate: ids(result, 'immediate') };
});
add('delay-procurement-explicit', 'unknown-causality', () => {
    const result = core.evaluate({ freeText: 'Le projet est en retard à cause des approvisionnements.', answers: {} });
    assert(ids(result, 'immediate').includes('procurement'), 'Procurement absent');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning ajouté automatiquement');
    return { immediate: ids(result, 'immediate') };
});
add('delay-engineering-explicit', 'unknown-causality', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'engineering' } });
    assert(ids(result, 'immediate').includes('engineering-documents'), 'Engineering absent');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning injustifié');
    return { immediate: ids(result, 'immediate') };
});
add('delay-approvals-explicit', 'unknown-causality', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'approvals' } });
    assert(ids(result, 'immediate').includes('decision-workflows'), 'Workflow de décision absent');
    return { immediate: ids(result, 'immediate') };
});
add('delay-subcontractor-stays-uncertain', 'unknown-causality', () => {
    const result = core.evaluate({ freeText: 'Le projet est en retard à cause du sous-traitant.', answers: {} });
    assert(result.uncertainties.some(item => item.id === 'subcontractor-delay-nature'), 'Nature du blocage non demandée');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning déduit du sous-traitant');
    assert(!result.capabilities.includes('procurement'), 'Procurement affiché par simple contexte sous-traitant');
    return { uncertainties: result.uncertainties.map(item => item.id), capabilities: result.capabilities };
});
add('delay-multiple-remains-blocking', 'unknown-causality', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'multiple' } });
    assert(result.uncertainties.some(item => item.id === 'project-delay-multiple'), 'Causes multiples non bloquantes');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning choisi parmi plusieurs causes');
    return { uncertainties: result.uncertainties.map(item => item.id), immediate: ids(result, 'immediate') };
});

const managementDocumentCases = [
    'Digitaliser le contrôle documentaire qualité.',
    'Améliorer la maîtrise documentaire ISO 9001.',
    'Structurer la gestion documentaire et les versions documentaires.',
    'Centraliser les documents qualité dans une GED.',
    'La documentation qualité doit être digitalisée.',
    'Mettre en place un document control pour le SMQ.'
];
managementDocumentCases.forEach((text, index) => add(`management-document-${index + 1}`, 'document-semantics', () => {
    const result = core.evaluate({ freeText: text, answers: {} });
    assert(ids(result).includes('document-control'), `Document Control absent: ${text}`);
    assert(result.capabilities.includes('quality'), `Quality absente: ${text}`);
    return { input: text, primaryDomain: result.primaryDomain, relatedDomains: result.relatedDomains, capabilities: result.capabilities };
}));
['Plans IFC et drawings à maîtriser.', 'Les technical submittals du projet sont en retard.'].forEach((text, index) => add(`technical-document-${index + 1}`, 'document-semantics', () => {
    const result = core.evaluate({ freeText: text, answers: {} });
    assert(ids(result).includes('engineering-documents'), 'Engineering Documents absent');
    assert(!ids(result).includes('document-control'), 'Document Control qualité ajouté aux documents techniques');
    assert(result.capabilities.includes('engineering'), 'Capability Engineering absente');
    return { input: text, recommendations: ids(result), capabilities: result.capabilities };
}));

add('epc-alone-hides-engineering', 'capability-threshold', () => {
    const result = core.evaluate({ entryIntent: 'projects', answers: { activity: 'epc' } });
    assert(!result.capabilities.includes('engineering'), 'Engineering affichée par simple contexte EPC');
    return result.capabilities;
});
add('engineering-evidence-displays-capability', 'capability-threshold', () => {
    const result = core.evaluate({ freeText: 'Plans IFC et drawings à maîtriser.', answers: {} });
    assert(result.capabilities.includes('engineering'), 'Engineering masquée malgré preuve');
    return result.capabilities;
});
add('digital-context-alone-hides-data', 'capability-threshold', () => {
    const result = core.evaluate({ freeText: 'Je veux digitaliser.', answers: {} });
    assert(!result.capabilities.includes('data'), 'Data affichée avec signal faible seulement');
    return result.capabilities;
});
add('quality-evidence-displays-capability', 'capability-threshold', () => {
    const result = core.evaluate({ freeText: 'Structurer notre système ISO 9001.', answers: {} });
    assert(result.capabilities.includes('quality'), 'Quality masquée malgré preuve');
    return result.capabilities;
});

add('cross-digital-quality-documents', 'cross-domain', () => {
    const result = core.evaluate({ freeText: 'Digitaliser la maîtrise documentaire qualité.', answers: {} });
    assert(result.primaryDomain === 'digitalisation', 'Digitalisation non primaire');
    assert(result.relatedDomains.includes('managementSystems'), 'Management Systems non associé');
    return { primary: result.primaryDomain, related: result.relatedDomains };
});
add('cross-iso-project-delay', 'cross-domain', () => {
    const result = core.evaluate({ entryIntent: 'managementSystems', freeText: 'ISO 9001 et projet en retard.', answers: {} });
    assert(result.relatedDomains.includes('projects'), 'Projects non associé');
    return { primary: result.primaryDomain, related: result.relatedDomains };
});
add('cross-people-project-decision', 'cross-domain', () => {
    const result = core.evaluate({ entryIntent: 'projects', freeText: 'Qui nommer responsable du projet ?', answers: {} });
    assert(result.entryIntent === 'projects' && result.primaryDomain === 'people', 'People ne domine pas la décision humaine');
    return { entry: result.entryIntent, primary: result.primaryDomain };
});
add('subcontractor-is-not-domain', 'cross-domain', () => {
    const result = core.evaluate({ freeText: 'Retard du sous-traitant sur le projet.', answers: {} });
    assert(!result.relatedDomains.includes('subcontractor'), 'Sous-traitant créé comme domaine');
    return { primary: result.primaryDomain, related: result.relatedDomains };
});

const sensitiveInputs = [
    'Je vais partager notre business plan.',
    'Puis-je transmettre nos prévisions financières ?',
    'Je souhaite envoyer le P&L et le compte de résultat.',
    'Je vais joindre le bilan et le cash-flow.',
    'Je peux uploader le BPU et le DQE.',
    'Je vais communiquer une offre fournisseur.',
    'Je souhaite transmettre le devis fournisseur.',
    'Je vais fournir des données personnelles nominatives.'
];
sensitiveInputs.forEach((text, index) => add(`privacy-positive-${index + 1}`, 'privacy', () => {
    assert(privacy.detectSensitiveIntent(text), `Sensibilité non détectée: ${text}`);
    return { input: text, sensitive: true };
}));
['Nous devons préparer un business plan.', 'Analyser les prévisions financières sans les transmettre.', 'Structurer le processus fournisseurs.', 'Améliorer le cash-flow prévisionnel.'].forEach((text, index) => add(`privacy-negative-${index + 1}`, 'privacy', () => {
    assert(!privacy.detectSensitiveIntent(text), `Faux positif sensible: ${text}`);
    return { input: text, sensitive: false };
}));

add('ranking-cost-delay-three-priorities', 'ranking', () => {
    const result = core.evaluate({ entryIntent: 'projects', answers: { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'cost-schedule' } });
    const immediate = ids(result, 'immediate');
    ['cost-control', 'planning-wbs', 'executive-reporting'].forEach(id => assert(immediate.includes(id), `${id} absent`));
    ['engineering-documents', 'procurement', 'qa-qc'].forEach(id => assert(!immediate.includes(id), `${id} injustifié`));
    return immediate;
});
add('ranking-procurement-before-planning', 'ranking', () => {
    const result = core.evaluate({ freeText: 'Retard du projet causé par les approvisionnements.', answers: {} });
    assert(ids(result, 'immediate')[0] === 'procurement', 'Procurement non prioritaire');
    assert(!ids(result, 'immediate').includes('planning-wbs'), 'Planning automatique');
    return ids(result, 'immediate');
});
add('ranking-no-evidence-no-recommendation', 'ranking', () => {
    const ranked = recommendations.rankRecommendations(['domain:projects'], [], [], false);
    assert(ranked.immediate.length === 0 && ranked.explore.length === 0, 'Recommandation sans preuve');
    return ranked;
});
add('ranking-every-item-has-proof', 'ranking', () => {
    const result = core.evaluate({ freeText: 'Digitaliser la maîtrise documentaire qualité.', answers: {} });
    assert(result.recommendations.immediate.concat(result.recommendations.explore).every(item => item.evidence.length && item.evidence.every(e => e.source && e.reason)), 'Preuve ou justification manquante');
    return ids(result);
});
add('ranking-limits', 'ranking', () => {
    const result = core.evaluate({ entryIntent: 'projects', answers: { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'cost-schedule' }, seedFacts: ['engineering:documents-risk', 'procurement:delay', 'quality:qa-qc-issue'] });
    assert(result.recommendations.immediate.length <= 3 && result.recommendations.explore.length <= 2, 'Limites 3+2 dépassées');
    return { immediate: ids(result, 'immediate'), explore: ids(result, 'explore') };
});
add('ranking-blocked-causal-goes-explore', 'ranking', () => {
    const result = core.evaluate({ freeText: 'Le projet et ses plans IFC sont en retard.', answers: {} });
    assert(!ids(result, 'immediate').includes('engineering-documents'), 'Recommandation causale Immediate malgré blocage');
    return { immediate: ids(result, 'immediate'), explore: ids(result, 'explore') };
});

add('nbq-delay-has-decision-value', 'next-best-question-v2', () => {
    const result = core.evaluate({ freeText: 'Le projet est en retard.', answers: {} });
    const question = core.nextBestQuestion({ entryIntent: 'projects', answers: {}, reasoning: result, questions: scenarios.scenarios.projects.questions });
    assert(question.id === 'delayCause', 'Cause du retard non priorisée');
    assert(question.decisionValue.uncertaintyReduction > 0 && question.decisionValue.recommendationDiscrimination > 0, 'Composantes V2 absentes');
    return { id: question.id, decisionValue: question.decisionValue };
});
add('nbq-subcontractor-most-impactful', 'next-best-question-v2', () => {
    const result = core.evaluate({ freeText: 'Le projet est en retard à cause du sous-traitant.', answers: {} });
    const question = core.nextBestQuestion({ entryIntent: 'projects', answers: {}, reasoning: result, questions: scenarios.scenarios.projects.questions });
    assert(question.id === 'subcontractorBlockage', 'Blocage sous-traitant non priorisé');
    return { id: question.id, decisionValue: question.decisionValue };
});
add('nbq-domain-clarification', 'next-best-question-v2', () => {
    const result = core.evaluate({ freeText: 'Nous devons mieux maîtriser la situation.', answers: {} });
    const question = core.nextBestQuestion({ answers: {}, reasoning: result, questions: [] });
    assert(question.id === 'domainClarification', 'Clarification domaine absente');
    return { id: question.id, decisionValue: question.decisionValue };
});
add('nbq-stops-when-sufficient', 'next-best-question-v2', () => {
    const answers = { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'cost-schedule' };
    const result = core.evaluate({ entryIntent: 'projects', answers });
    assert(core.nextBestQuestion({ entryIntent: 'projects', answers, reasoning: result, questions: scenarios.scenarios.projects.questions }) === null, 'Question posée malgré information suffisante');
    return { immediate: ids(result, 'immediate') };
});

const mutationGroups = [
    ['Quel manager mettre sur le projet Atlas ?', 'QUEL MANAGER METTRE SUR LE PROJET ATLAS ?', 'Quel mánager mettre sur le projet Atlas!!!'],
    ['Digitaliser la maîtrise documentaire qualité.', 'digitaliser la maitrise documentaire qualite', 'DIGITALISER LA MAÎTRISE DOCUMENTAIRE QUALITÉ'],
    ['Je vais partager notre business plan.', 'JE VAIS PARTAGER NOTRE BUSINESS PLAN', 'Je vais partager notre búsiness plan !'],
    ['Le projet est en retard.', 'LE PROJET EST EN RETARD', 'Le projet est en retárd...']
];
mutationGroups.forEach((variants, index) => add(`mutation-invariant-${index + 1}`, 'mutation', () => {
    const snapshots = variants.map(text => {
        const result = core.evaluate({ freeText: text, answers: {} });
        return JSON.stringify({ primary: result.primaryDomain, guardrail: result.peopleGuardrail, sensitive: result.sensitiveIntent, facts: result.facts.slice().sort(), immediate: ids(result, 'immediate').slice().sort(), uncertainties: result.uncertainties.map(item => item.id).sort() });
    });
    assert(new Set(snapshots).size === 1, `Mutation sémantique détectée: ${variants.join(' | ')}`);
    return JSON.parse(snapshots[0]);
}));

const manualExternalCases = [
    ['manual-01-manager-atlas', 'Quel manager mettre sur le projet Atlas ?', result => result.peopleGuardrail],
    ['manual-02-promote-project-chief', 'Je veux promouvoir le meilleur chef de projet.', result => result.peopleGuardrail],
    ['manual-03-manage-projects', 'Comment manager nos projets ?', result => !result.peopleGuardrail],
    ['manual-04-manage-team', 'Je veux manager mon équipe projet.', result => !result.peopleGuardrail && result.facts.includes('people:signal')],
    ['manual-05-delay-unknown', 'Mes projets sont en retard mais je ne sais pas pourquoi.', result => result.uncertainties.some(item => item.id === 'project-delay-cause') && !ids(result, 'immediate').includes('planning-wbs')],
    ['manual-06-delay-subcontractors', 'Nos projets sont en retard à cause des sous-traitants.', result => result.primaryDomain === 'projects' && result.uncertainties.some(item => item.id === 'subcontractor-delay-nature')],
    ['manual-07-quality-document-control', 'Je veux digitaliser le contrôle documentaire qualité.', result => result.primaryDomain === 'digitalisation' && result.relatedDomains.includes('managementSystems') && ids(result).includes('document-control')],
    ['manual-08-ifc-technical-documents', 'Nous avons des problèmes avec les plans IFC et documents techniques.', result => result.primaryDomain === 'projects' && result.capabilities.includes('engineering') && ids(result).includes('engineering-documents') && !ids(result).includes('document-control')],
    ['manual-09-epc-context-only', null, result => !result.capabilities.includes('engineering')],
    ['manual-10-sensitive-forecast', 'Je veux joindre notre compte de résultat et notre forecast.', result => result.sensitiveIntent]
];
manualExternalCases.forEach(([name, text, verify]) => add(name, 'manual-external', () => {
    const input = name === 'manual-09-epc-context-only' ? { entryIntent: 'projects', answers: { activity: 'epc' } } : { freeText: text, answers: {} };
    const result = core.evaluate(input);
    assert(verify(result), `Cas manuel non conforme: ${text || 'EPC context only'}`);
    return { input, primaryDomain: result.primaryDomain, capabilities: result.capabilities, immediate: ids(result, 'immediate'), uncertainties: result.uncertainties.map(item => item.id), peopleGuardrail: result.peopleGuardrail, sensitiveIntent: result.sensitiveIntent };
}));

add('mutation-evidence-removed', 'rule-mutation-sentinel', () => {
    const ranked = recommendations.rankRecommendations(['project:cost-delay'], [], [], false);
    assert(ranked.immediate.length === 0 && ranked.explore.length === 0, 'La suppression des preuves ne bloque pas le ranking');
    return { mutation: 'evidence removed', detected: true };
});
add('mutation-people-bypassed', 'rule-mutation-sentinel', () => {
    const result = core.evaluate({ freeText: 'Quel manager choisir pour un projet avec des coûts et délais ?', answers: {} });
    const bypassed = recommendations.rankRecommendations(result.facts, result.evidence, result.uncertainties, false);
    assert(ids(result).length === 0 && bypassed.immediate.length + bypassed.explore.length > 0, 'La sentinelle ne détecterait pas un bypass People');
    return { mutation: 'people guardrail bypassed', detected: true };
});
add('mutation-immediate-limit', 'rule-mutation-sentinel', () => {
    const result = core.evaluate({ entryIntent: 'projects', seedFacts: ['project:cost-delay', 'project:fragmented-tools', 'engineering:documents-risk', 'procurement:delay', 'quality:qa-qc-issue'], answers: { activity: 'epc', portfolio: 'large' } });
    assert(result.recommendations.immediate.length <= 3 && result.recommendations.explore.length <= 2, 'La sentinelle 3+2 a échoué');
    return { immediate: ids(result, 'immediate'), explore: ids(result, 'explore') };
});
add('mutation-single-authority', 'rule-mutation-sentinel', () => {
    const forbidden = ['recommendations', 'priorities', 'observations', 'expertWhen', 'peopleWhen'];
    assert(Object.values(scenarios.scenarios).every(scenario => forbidden.every(key => !(key in scenario))), 'Une seconde autorité métier est apparue dans scenarios');
    return { mutation: 'scenario recommendation inserted', detected: true };
});

const categories = tests.reduce((result, test) => {
    if (!result[test.category]) result[test.category] = { total: 0, passed: 0, failed: 0 };
    result[test.category].total += 1;
    result[test.category][test.passed ? 'passed' : 'failed'] += 1;
    return result;
}, {});
const passed = tests.filter(test => test.passed).length;
const report = {
    suite: 'Civilina External Adversarial Hardening',
    reasoningCoreVersion: core.REASONING_CORE_VERSION,
    total: tests.length,
    passed,
    failed: tests.length - passed,
    categories,
    tests
};
fs.writeFileSync(path.join(here, 'external-adversarial-validation.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({ total: report.total, passed: report.passed, failed: report.failed, categories }, null, 2));
if (report.failed) {
    tests.filter(test => !test.passed).forEach(test => console.error(`${test.name}: ${test.error}`));
    process.exitCode = 1;
}


