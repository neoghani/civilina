import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning.js');
const domains = loadCivilina('civilina-ai-domains.js');
const rules = loadCivilina('civilina-ai-rules.js');
const scenarios = loadCivilina('civilina-ai-scenarios.js');
const recommendations = loadCivilina('civilina-ai-recommendations.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const tests = [];

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function recommendationIds(result) {
    return result.recommendations.immediate.concat(result.recommendations.explore).map(item => item.id);
}

function snapshot(result) {
    return {
        entryIntent: result.entryIntent,
        primaryDomain: result.primaryDomain,
        relatedDomains: result.relatedDomains,
        capabilities: result.capabilities,
        immediateRecommendations: result.recommendations.immediate.map(item => item.id),
        exploreRecommendations: result.recommendations.explore.map(item => item.id),
        uncertainties: result.uncertainties.map(item => item.id),
        contradictions: result.contradictions.map(item => item.id),
        sensitiveIntent: result.sensitiveIntent,
        peopleGuardrail: result.peopleGuardrail,
        expertSuggested: result.expertSuggested,
    };
}

function caseOf(testName, input, verify, category = 'reasoning') {
    const result = core.evaluate(input);
    let error = null;
    try { verify(result); } catch (caught) { error = caught.message; }
    tests.push({ testName, category, input, ...snapshot(result), passed: error === null, error });
}

function directCase(testName, category, verify) {
    let error = null;
    try { verify(); } catch (caught) { error = caught.message; }
    tests.push({ testName, category, passed: error === null, error });
}

const projectCost = {
    entryIntent: 'projects',
    answers: { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'cost-schedule' },
};
const managementDocuments = {
    entryIntent: 'managementSystems',
    answers: { reference: 'integrated', maturity: 'fragmented', difficulty: 'documents', tools: 'multiple-tools' },
};
const digitalWorkflow = {
    entryIntent: 'digitalisation',
    answers: { process: 'approvals', tools: 'multiple-tools', difficulty: 'traceability', objective: 'workflow' },
};

directCase('01 exact business domain registry', 'taxonomy', () => assert(
    JSON.stringify(Object.keys(domains.BUSINESS_DOMAINS)) === JSON.stringify(['projects', 'organisation', 'managementSystems', 'digitalisation', 'people']),
    'Business Domains incorrects'
));
directCase('02 exact capability registry', 'taxonomy', () => assert(
    JSON.stringify(Object.keys(domains.CAPABILITIES)) === JSON.stringify(['projectControls', 'engineering', 'quality', 'procurement', 'data']),
    'Capabilities incorrectes'
));
directCase('03 domains and capabilities do not overlap', 'taxonomy', () => assert(
    Object.keys(domains.BUSINESS_DOMAINS).every(id => !domains.CAPABILITIES[id]),
    'Chevauchement domaine/capacite'
));
directCase('04 entry registry contains business domains only', 'taxonomy', () => assert(
    domains.ENTRY_DOMAINS.every(id => domains.BUSINESS_DOMAINS[id] && !domains.CAPABILITIES[id]),
    'Entree non metier exposee'
));
directCase('05 quality is capability only', 'taxonomy', () => assert(domains.CAPABILITIES.quality && !domains.BUSINESS_DOMAINS.quality, 'Quality redevient un domaine'));
directCase('06 project controls is capability only', 'taxonomy', () => assert(domains.CAPABILITIES.projectControls && !domains.BUSINESS_DOMAINS.projectControls, 'Project Controls redevient un domaine'));
directCase('07 scenarios contain questions only', 'authority', () => assert(
    Object.values(scenarios.scenarios).every(scenario => !('priorities' in scenario) && !('recommendations' in scenario) && !('observations' in scenario) && !('expertWhen' in scenario) && !('peopleWhen' in scenario)),
    'Une seconde source de verite subsiste dans les scenarios'
));
directCase('08 recommendation rules have single registry', 'authority', () => assert(
    recommendations.RULES.length === Object.keys(recommendations.RECOMMENDATIONS).length,
    'Registre de recommandations incoherent'
));

caseOf('09 projects primary', { entryIntent: 'projects', answers: {} }, result => assert(result.primaryDomain === 'projects', 'Projects absent'), 'domain');
caseOf('10 organisation primary', { entryIntent: 'organisation', answers: {} }, result => assert(result.primaryDomain === 'organisation', 'Organisation absente'), 'domain');
caseOf('11 management systems primary', { entryIntent: 'managementSystems', answers: {} }, result => assert(result.primaryDomain === 'managementSystems', 'Management Systems absent'), 'domain');
caseOf('12 digitalisation primary', { entryIntent: 'digitalisation', answers: {} }, result => assert(result.primaryDomain === 'digitalisation', 'Digitalisation absente'), 'domain');
caseOf('13 people primary', { entryIntent: 'people', answers: {} }, result => assert(result.primaryDomain === 'people', 'People absent'), 'domain');
caseOf('14 ISO detects management systems', { freeText: 'Nous preparons ISO 9001.', answers: {} }, result => assert(result.entryIntent === 'managementSystems', 'ISO mal classe'), 'domain');
caseOf('15 candidate profile detects people', { freeText: 'Je souhaite structurer un profil candidat.', answers: {} }, result => assert(result.entryIntent === 'people', 'People non detecte'), 'people');
caseOf('16 manage a project is not people', { freeText: 'Je dois manager un projet EPC.', answers: {} }, result => assert(result.entryIntent === 'projects' && !result.peopleGuardrail, 'Manager un projet assimile aux RH'), 'people-negative');
caseOf('17 risk profile is not people', { freeText: 'Quel est le profil de risque du projet ?', answers: {} }, result => assert(result.entryIntent === 'projects' && !result.peopleGuardrail, 'Profil de risque assimile a un candidat'), 'people-negative');
caseOf('18 professional profile is people', { freeText: 'Je veux valoriser le profil professionnel d un candidat.', answers: {} }, result => assert(result.primaryDomain === 'people', 'Profil professionnel non humain'), 'people');

caseOf('19 ISO and organisation cross-domain', { entryIntent: 'managementSystems', freeText: 'ISO 9001 avec des responsabilites peu claires.', answers: {} }, result => assert(result.primaryDomain === 'managementSystems' && result.relatedDomains.includes('organisation'), 'Couplage ISO/Organisation perdu'), 'cross-domain');
caseOf('20 ISO and projects cross-domain', { entryIntent: 'managementSystems', freeText: 'ISO 9001 pour des projets souvent en retard.', answers: {} }, result => assert(result.relatedDomains.includes('projects'), 'Couplage ISO/Projects perdu'), 'cross-domain');
caseOf('21 digital approvals and organisation', digitalWorkflow, result => assert(result.relatedDomains.includes('organisation'), 'Organisation associee absente'), 'cross-domain');
caseOf('22 fragmented project tools and digitalisation', projectCost, result => assert(result.domainSignals.digitalisation > 0, 'Signal digital absent'), 'cross-domain');
caseOf('23 management indicators and digitalisation', { entryIntent: 'managementSystems', answers: { difficulty: 'indicators' } }, result => assert(result.relatedDomains.includes('digitalisation'), 'Digitalisation associee absente'), 'cross-domain');
caseOf('24 digital documents and management systems', { entryIntent: 'digitalisation', answers: { process: 'documents', objective: 'centralize' } }, result => assert(result.relatedDomains.includes('managementSystems'), 'Management Systems associe absent'), 'cross-domain');
caseOf('25 organisation reporting and digitalisation', { entryIntent: 'organisation', answers: { issue: 'reporting', objective: 'management-dashboard' } }, result => assert(result.relatedDomains.includes('digitalisation'), 'Digitalisation associee absente'), 'cross-domain');
caseOf('26 procurement remains capability', { entryIntent: 'projects', freeText: 'Les approvisionnements critiques retardent le projet.', answers: { delayCause: 'procurement' } }, result => assert(result.capabilities.includes('procurement') && !result.relatedDomains.includes('procurement'), 'Procurement traite comme domaine'), 'cross-domain');
caseOf('27 engineering remains capability', { entryIntent: 'projects', answers: { activity: 'epc', difficulty: 'risks-documents' } }, result => assert(result.capabilities.includes('engineering') && !result.relatedDomains.includes('engineering'), 'Engineering traite comme domaine'), 'cross-domain');
caseOf('28 quality remains capability', managementDocuments, result => assert(result.capabilities.includes('quality') && !result.relatedDomains.includes('quality'), 'Quality traite comme domaine'), 'cross-domain');
caseOf('29 data remains capability', digitalWorkflow, result => assert(result.capabilities.includes('data') && !result.relatedDomains.includes('data'), 'Data traite comme domaine'), 'cross-domain');
caseOf('30 project controls remains capability', projectCost, result => assert(result.capabilities.includes('projectControls') && !result.relatedDomains.includes('projectControls'), 'Project Controls traite comme domaine'), 'cross-domain');

caseOf('31 ranking max three immediate', projectCost, result => assert(result.recommendations.immediate.length <= 3, 'Plus de 3 recommandations immediates'), 'ranking');
caseOf('32 ranking max two explore', projectCost, result => assert(result.recommendations.explore.length <= 2, 'Plus de 2 recommandations explore'), 'ranking');
caseOf('33 every project recommendation has evidence', projectCost, result => assert(result.recommendations.immediate.concat(result.recommendations.explore).every(item => item.evidence.length > 0), 'Recommandation sans preuve'), 'ranking');
caseOf('34 every reason is explicit', managementDocuments, result => assert(result.recommendations.immediate.concat(result.recommendations.explore).every(item => item.evidence.every(evidence => evidence.source && evidence.reason)), 'Justification absente'), 'ranking');
caseOf('35 EPC alone does not recommend engineering documents', { entryIntent: 'projects', answers: { activity: 'epc' } }, result => assert(!recommendationIds(result).includes('engineering-documents'), 'Engineering injustifie'), 'negative-ranking');
caseOf('36 EPC alone does not recommend procurement', { entryIntent: 'projects', answers: { activity: 'epc' } }, result => assert(!recommendationIds(result).includes('procurement'), 'Procurement injustifie'), 'negative-ranking');
caseOf('37 EPC alone does not recommend QA QC', { entryIntent: 'projects', answers: { activity: 'epc' } }, result => assert(!recommendationIds(result).includes('qa-qc'), 'QA/QC injustifie'), 'negative-ranking');
caseOf('38 cost issue does not recommend QA QC', { entryIntent: 'projects', answers: { difficulty: 'cost-schedule' } }, result => assert(!recommendationIds(result).includes('qa-qc'), 'QA/QC injustifie'), 'negative-ranking');
caseOf('39 planning delay does not recommend procurement', { entryIntent: 'projects', freeText: 'Le planning du projet est en retard.', answers: { delayCause: 'planning' } }, result => assert(!recommendationIds(result).includes('procurement'), 'Procurement injustifie'), 'negative-ranking');
caseOf('40 ISO alone does not recommend project controls', { entryIntent: 'managementSystems', freeText: 'ISO 9001.', answers: {} }, result => assert(!recommendationIds(result).some(id => ['cost-control', 'planning-wbs', 'executive-reporting'].includes(id)), 'Project Controls injustifie'), 'negative-ranking');
caseOf('41 digitalisation alone does not recommend engineering', { entryIntent: 'digitalisation', freeText: 'Digitaliser un processus.', answers: {} }, result => assert(!recommendationIds(result).includes('engineering-documents'), 'Engineering injustifie'), 'negative-ranking');
caseOf('42 people guardrail blocks recommendations', { freeText: 'Quel candidat MBTI dois-je choisir ?', answers: {} }, result => assert(recommendationIds(result).length === 0, 'Decision humaine automatisee'), 'negative-ranking');
caseOf('43 cost control has direct evidence', projectCost, result => assert(result.recommendations.immediate.some(item => item.id === 'cost-control' && item.evidence.some(evidence => evidence.source === 'project:cost-delay')), 'Cost Control sans preuve directe'), 'ranking');
caseOf('44 document control has direct evidence', managementDocuments, result => assert(recommendationIds(result).includes('document-control'), 'Document Control absent'), 'ranking');
caseOf('45 digital workflow has direct evidence', digitalWorkflow, result => assert(recommendationIds(result).includes('digital-workflows'), 'Digital Workflow absent'), 'ranking');
caseOf('46 roles recommendation has direct evidence', { entryIntent: 'organisation', answers: { issue: 'roles', objective: 'clarify-roles' } }, result => assert(recommendationIds(result).includes('roles-responsibilities'), 'Roles absent'), 'ranking');
caseOf('47 recommendation family is unique in immediate', projectCost, result => {
    const families = result.recommendations.immediate.map(item => item.family).filter(Boolean);
    assert(new Set(families).size === families.length, 'Famille dupliquee en Immediate');
}, 'ranking');
caseOf('48 recommendation metadata references valid registries', digitalWorkflow, result => assert(
    result.recommendations.immediate.concat(result.recommendations.explore).every(item => domains.BUSINESS_DOMAINS[item.businessDomain] && (!item.capability || domains.CAPABILITIES[item.capability])),
    'Metadonnees de recommandation invalides'
), 'ranking');

caseOf('49 ambiguous input asks domain clarification', { freeText: 'Nous avons du mal a piloter.', answers: {} }, result => {
    const question = core.nextBestQuestion({ answers: {}, reasoning: result, questions: [] });
    assert(question && question.id === 'domainClarification', 'Clarification de domaine absente');
}, 'next-best-question');
caseOf('50 project asks highest impact difficulty first', { entryIntent: 'projects', answers: {} }, result => {
    const question = core.nextBestQuestion({ entryIntent: 'projects', answers: {}, reasoning: result, questions: scenarios.scenarios.projects.questions });
    assert(question.id === 'difficulty', 'Question projet sans impact maximal');
}, 'next-best-question');
caseOf('51 project asks tracking after difficulty', { entryIntent: 'projects', answers: { difficulty: 'cost-schedule' } }, result => {
    const question = core.nextBestQuestion({ entryIntent: 'projects', answers: { difficulty: 'cost-schedule' }, reasoning: result, questions: scenarios.scenarios.projects.questions });
    assert(question.id === 'tracking', 'Tracking ne suit pas la priorite');
}, 'next-best-question');
caseOf('52 organisation asks issue first', { entryIntent: 'organisation', answers: {} }, result => assert(core.nextBestQuestion({ entryIntent: 'organisation', answers: {}, reasoning: result, questions: scenarios.scenarios.organisation.questions }).id === 'issue', 'Question organisation incorrecte'), 'next-best-question');
caseOf('53 management systems asks difficulty first', { entryIntent: 'managementSystems', answers: {} }, result => assert(core.nextBestQuestion({ entryIntent: 'managementSystems', answers: {}, reasoning: result, questions: scenarios.scenarios.managementSystems.questions }).id === 'difficulty', 'Question management incorrecte'), 'next-best-question');
caseOf('54 digitalisation asks process first', { entryIntent: 'digitalisation', answers: {} }, result => assert(core.nextBestQuestion({ entryIntent: 'digitalisation', answers: {}, reasoning: result, questions: scenarios.scenarios.digitalisation.questions }).id === 'process', 'Question digitalisation incorrecte'), 'next-best-question');
caseOf('55 people asks objective first', { entryIntent: 'people', answers: {} }, result => assert(core.nextBestQuestion({ entryIntent: 'people', answers: {}, reasoning: result, questions: scenarios.scenarios.people.questions }).id === 'peopleObjective', 'Question People incorrecte'), 'next-best-question');
caseOf('56 delay cause outranks scenario order', { entryIntent: 'managementSystems', freeText: 'ISO 9001 et projet en retard a cause des achats.', answers: {} }, result => assert(core.nextBestQuestion({ entryIntent: 'managementSystems', answers: {}, reasoning: result, questions: scenarios.scenarios.managementSystems.questions }).id === 'delayCause', 'Cause du retard non priorisee'), 'next-best-question');

caseOf('57 empty input remains ambiguous', { answers: {} }, result => assert(result.primaryDomain === null && result.uncertainties.some(item => item.id === 'domain-clarification'), 'Ambiguite masquee'), 'ambiguity');
caseOf('58 structured process and unclear roles contradict', { entryIntent: 'organisation', answers: { processMaturity: 'structured', responsibilityClarity: 'low' } }, result => assert(result.contradictions.some(item => item.id === 'structured-process-unclear-responsibilities'), 'Contradiction absente'), 'contradiction');
caseOf('59 improving and fragmented contradict', { entryIntent: 'managementSystems', seedFacts: ['management:fragmented'], answers: { maturity: 'improving' } }, result => assert(result.contradictions.some(item => item.id === 'improving-yet-fragmented'), 'Contradiction management absente'), 'contradiction');
caseOf('60 simple project delay does not force expert', { entryIntent: 'projects', freeText: 'Le planning du projet est en retard.', answers: { delayCause: 'planning' } }, result => assert(!result.expertSuggested, 'Expert suggere trop tot'), 'ambiguity');
caseOf('61 strong cross-domain can suggest expert', { entryIntent: 'managementSystems', freeText: 'ISO 9001, responsabilites floues et digitalisation des validations.', answers: {} }, result => assert(result.expertSuggested, 'Expert transverse absent'), 'cross-domain');
caseOf('62 answer correction removes stale cost recommendation', projectCost, before => {
    const after = core.evaluate({ entryIntent: 'projects', answers: { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'executive-reporting' } });
    assert(recommendationIds(before).includes('cost-control') && !recommendationIds(after).includes('cost-control'), 'Recommandation stale conservee');
}, 'recalculation');

caseOf('63 MBTI assignment activates guardrail', { freeText: 'J ai les MBTI de mes salaries. Qui dois-je mettre chef de chantier ?', answers: {} }, result => assert(result.peopleGuardrail, 'Guardrail MBTI inactif'), 'mbti');
caseOf('64 chantier does not override people decision', { freeText: 'J ai les MBTI de mes salaries. Qui dois-je mettre chef de chantier ?', answers: {} }, result => assert(result.primaryDomain === 'people', 'Chantier domine la decision humaine'), 'mbti');
caseOf('65 manage project stays outside people guardrail', { freeText: 'Comment manager un projet EPC complexe ?', answers: {} }, result => assert(!result.peopleGuardrail && result.primaryDomain === 'projects', 'Faux positif People'), 'people-negative');
caseOf('66 project risk profile stays outside people guardrail', { freeText: 'Construire le profil de risque du projet.', answers: {} }, result => assert(!result.peopleGuardrail && result.primaryDomain === 'projects', 'Faux positif profil candidat'), 'people-negative');
caseOf('67 MBTI discussion without decision stays advisory', { freeText: 'Utiliser le MBTI comme support de discussion dans une equipe.', answers: {} }, result => assert(result.primaryDomain === 'people' && !result.peopleGuardrail, 'Discussion MBTI bloquee a tort'), 'mbti');
caseOf('68 candidate choice activates guardrail', { freeText: 'Quel candidat choisir pour ce poste ?', answers: {} }, result => assert(result.peopleGuardrail, 'Choix candidat non bloque'), 'people');
caseOf('69 incomplete people evidence creates uncertainty', { entryIntent: 'people', answers: { peopleObjective: 'development', peopleCriteria: 'incomplete' } }, result => assert(result.uncertainties.some(item => item.id === 'people-evidence-incomplete'), 'Incertitude People absente'), 'people');
caseOf('70 staffing objective activates guardrail', { entryIntent: 'people', answers: { peopleObjective: 'staffing-decision' } }, result => assert(result.peopleGuardrail, 'Decision de staffing non encadree'), 'people');
caseOf('71 people guardrail keeps all rankings empty', { entryIntent: 'people', freeText: 'Choisir quel candidat recruter.', answers: { peopleObjective: 'staffing-decision' } }, result => assert(result.recommendations.immediate.length === 0 && result.recommendations.explore.length === 0, 'Ranking RH produit'), 'people');
caseOf('72 entry intent and primary domain remain distinct', { entryIntent: 'projects', freeText: 'Les MBTI des candidats doivent ils decider qui sera manager ?', answers: {} }, result => assert(result.entryIntent === 'projects' && result.primaryDomain === 'people', 'Intent et domaine confondus'), 'people');

directCase('73 rule ALL passes', 'rule-engine', () => assert(rules.evaluateRule({ a: 1, b: 2 }, { all: [{ field: 'a', equals: 1 }, { field: 'b', equals: 2 }] }), 'ALL echoue'));
directCase('74 rule ALL fails', 'rule-engine', () => assert(!rules.evaluateRule({ a: 1 }, { all: [{ field: 'a', equals: 2 }] }), 'ALL passe a tort'));
directCase('75 rule ANY passes', 'rule-engine', () => assert(rules.evaluateRule({ a: 1 }, { any: [{ field: 'a', equals: 1 }, { field: 'a', equals: 2 }] }), 'ANY echoue'));
directCase('76 rule ANY fails', 'rule-engine', () => assert(!rules.evaluateRule({ a: 3 }, { any: [{ field: 'a', equals: 1 }, { field: 'a', equals: 2 }] }), 'ANY passe a tort'));
directCase('77 rule NONE passes', 'rule-engine', () => assert(rules.evaluateRule({ a: 1 }, { none: [{ field: 'a', equals: 2 }] }), 'NONE echoue'));
directCase('78 rule NONE blocks', 'rule-engine', () => assert(!rules.evaluateRule({ a: 1 }, { none: [{ field: 'a', equals: 1 }] }), 'NONE ne bloque pas'));
directCase('79 nested rule field works', 'rule-engine', () => assert(rules.evaluateRule({ nested: { value: 8 } }, { all: [{ field: 'nested.value', greaterThan: 5 }] }), 'Champ imbrique absent'));
directCase('80 missing evidence cannot create recommendation', 'rule-engine', () => assert(
    recommendations.rankRecommendations(['domain:projects'], [], [], false).immediate.length === 0,
    'Recommandation creee sans preuve'
));

const passed = tests.filter(test => test.passed).length;
const categories = tests.reduce((result, test) => {
    if (!result[test.category]) result[test.category] = { total: 0, passed: 0, failed: 0 };
    result[test.category].total += 1;
    result[test.category][test.passed ? 'passed' : 'failed'] += 1;
    return result;
}, {});
const report = { suite: 'Civilina Reasoning Adversarial', total: tests.length, passed, failed: tests.length - passed, categories, tests };
fs.writeFileSync(path.join(here, 'reasoning-validation.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({ total: report.total, passed: report.passed, failed: report.failed, categories }, null, 2));
if (report.failed) {
    tests.filter(test => !test.passed).forEach(test => console.error(`${test.testName}: ${test.error}`));
    process.exitCode = 1;
}


