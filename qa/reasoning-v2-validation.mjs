import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const lexicon = loadCivilina('civilina-ai-lexicon.js');
const ontology = loadCivilina('civilina-ai-ontology.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const tests = [];

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function run(name, category, input, verify) {
    let result;
    let error = null;
    try {
        result = core.evaluate(input);
        verify(result);
        assert(result.immediateRecommendations.length <= 3, 'Plus de 3 recommandations immédiates.');
        assert(result.exploreRecommendations.length <= 2, 'Plus de 2 recommandations à explorer.');
        assert(result.recommendations.every(item => item.evidence.length && item.justification), 'Recommandation sans preuve ou justification.');
    } catch (caught) {
        error = caught.message;
    }
    tests.push({ name, category, input, passed: error === null, error, status: result?.answerabilityStatus, domain: result?.primaryDomain });
}

function verifyLexicon(entry, result) {
    if (entry.category === 'concept') assert(result.recognizedConcepts.includes(entry.value), `Concept ${entry.value} non reconnu.`);
    if (entry.category === 'entity') assert(result.entities.includes(entry.value), `Entité ${entry.value} non reconnue.`);
    if (entry.category === 'symptom') assert(result.symptoms.includes(entry.value), `Symptôme ${entry.value} non reconnu.`);
    if (entry.category === 'interactionMode') assert(result.interactionMode === entry.value, `Mode ${entry.value} non sélectionné.`);
    if (entry.category === 'persona') assert(result.persona === entry.value, `Persona ${entry.value} non sélectionné.`);
    if (entry.category === 'scope') assert(result.scope.level === entry.value, `Scope ${entry.value} non sélectionné.`);
    if (entry.category === 'horizon') assert(result.decisionHorizon === entry.value, `Horizon ${entry.value} non sélectionné.`);
    if (entry.category === 'flag' && entry.value === 'externalVerification') assert(result.externalVerificationRequired, 'Vérification externe non activée.');
    if (entry.category === 'flag' && entry.value === 'contractInterpretation') assert(result.humanExpertRecommended, 'Expert contrat non recommandé.');
    if (entry.category === 'flag' && entry.value === 'safetyCritical') assert(result.safetyCritical, 'Criticité HSE non activée.');
}

lexicon.entries.forEach(entry => {
    entry.terms.forEach((term, index) => {
        run(`lexicon-${entry.id}-${index + 1}`, `lexicon:${entry.category}`, term, result => verifyLexicon(entry, result));
    });
});

const composites = [
    ['pc-delay-cause-unknown', 'Pourquoi notre projet EPC est en retard sans cause identifiée ?', r => r.primaryDomain === 'projects' && r.answerabilityStatus === 'NEED_CLARIFICATION'],
    ['executive-margin', 'DG : quels projets menacent notre marge ce mois ?', r => r.primaryDomain === 'executiveControl' && r.persona === 'executive'],
    ['evm-missing', 'Calcule le SPI et le CPI du projet.', r => r.answerabilityStatus === 'NEED_DATA' && r.recognizedConcepts.includes('evm')],
    ['evm-context', 'Chef de projet : analyser EVM avec PV 100, EV 80 et AC 90 à la date de statut.', r => r.recognizedConcepts.includes('evm') && r.entities.includes('project')],
    ['wbs-where-what', 'Concevoir une WBS du périmètre et la relier aux zones et ouvrages.', r => r.recognizedConcepts.includes('wbs') && r.entities.includes('zone') && r.entities.includes('ouvrage')],
    ['progress-types', 'Comparer avancement planifié, exécuté, accepté, valorisé et forecast.', r => r.recognizedConcepts.includes('progressControl') && r.controlDimensions.includes('progress')],
    ['engineering-flow', 'Analyser les drawings, RFI, submittals et transmittals bloqués.', r => r.recognizedConcepts.includes('technicalDocuments') && r.capabilities.includes('technicalDocumentManagement')],
    ['quality-flow', 'Auditer ce document joint : ITP, inspections, NCR et actions correctives.', r => r.recognizedConcepts.includes('quality') && r.answerabilityStatus !== 'NEED_DOCUMENT'],
    ['hse-critical', 'Danger imminent sur chantier : que devons-nous décider aujourd hui ?', r => r.safetyCritical && r.answerabilityStatus === 'HUMAN_EXPERT_RECOMMENDED'],
    ['procurement-long-lead', 'Pourquoi les long lead items arrivent après la date besoin du planning ?', r => r.recognizedConcepts.includes('procurement') && r.symptoms.includes('delay')],
    ['claim-basis', 'Interprétation contractuelle de ce claim et entitlement.', r => r.answerabilityStatus === 'HUMAN_EXPERT_RECOMMENDED'],
    ['construction-productivity', 'Diagnostiquer la productivité chantier inférieure au forecast.', r => r.recognizedConcepts.includes('productivity') && r.capabilities.includes('resourcesProductivity')],
    ['organisation-raci', 'Créer une gouvernance RACI et clarifier qui décide dans le processus.', r => r.primaryDomain === 'organisation' && r.recognizedConcepts.includes('organisation')],
    ['iso-system', 'Auditer notre système ISO 9001 et la maîtrise documentaire.', r => r.primaryDomain === 'managementSystems' && r.answerabilityStatus === 'NEED_DOCUMENT'],
    ['data-lineage', 'Pourquoi les KPI ont des valeurs différentes selon les dashboards et sources ?', r => r.recognizedConcepts.includes('dataGovernance') && r.capabilities.includes('dataReporting')],
    ['digital-integration', 'Concevoir l intégration ERP API et automatiser le workflow.', r => r.primaryDomain === 'digitalisation' && r.capabilities.includes('integrationAutomation')],
    ['people-guardrail', 'Comparer deux candidats avec leur MBTI pour choisir le meilleur.', r => r.peopleGuardrail && r.answerabilityStatus === 'HUMAN_EXPERT_RECOMMENDED'],
    ['external-norm', 'Quelle est l édition actuelle de la norme applicable ?', r => r.answerabilityStatus === 'EXTERNAL_VERIFICATION_REQUIRED'],
    ['privacy-intent', 'Je veux vous envoyer nos factures, marges et contrats.', r => r.sensitiveIntent],
    ['review-document-missing', 'Critiquer notre baseline planning.', r => r.answerabilityStatus === 'NEED_DOCUMENT'],
    ['review-document-present', 'Critiquer ce document joint de baseline planning.', r => r.answerabilityStatus !== 'NEED_DOCUMENT'],
    ['create-missing', 'Créer un dashboard de pilotage projet.', r => r.answerabilityStatus === 'NEED_CLARIFICATION'],
    ['risk-register', 'Prioriser les risques du registre selon probabilité, impact et propriétaire.', r => r.recognizedConcepts.includes('riskManagement')],
    ['change-control', 'Structurer le change control, les variations, notices et impacts coût délai.', r => r.recognizedConcepts.includes('changeControl') && r.capabilities.includes('changeControl')]
];

for (let cycle = 0; cycle < 4; cycle += 1) {
    composites.forEach(([id, text, predicate]) => run(`composite-${cycle + 1}-${id}`, 'composite-business', `${text} ${['', 'Projet Alpha.', 'Portefeuille régional.', 'Décision à préparer.'][cycle]}`, result => assert(predicate(result), `Contrat composite non respecté : ${id}`)));
}

run('registry-version', 'architecture', 'pilotage projet', result => assert(result.reasoningCoreVersion === '2.0.0', 'Version V2 incorrecte.'));
run('registry-domains', 'architecture', 'pilotage projet', () => assert(Object.keys(ontology.BUSINESS_DOMAINS).length === 6, 'Registre domaines incomplet.'));
run('registry-capabilities', 'architecture', 'pilotage projet', () => assert(Object.keys(ontology.CAPABILITIES).length >= 17, 'Registre capacités incomplet.'));

const failed = tests.filter(test => !test.passed);
const categories = tests.reduce((map, test) => {
    if (!map[test.category]) map[test.category] = { total: 0, passed: 0, failed: 0 };
    map[test.category].total += 1;
    map[test.category][test.passed ? 'passed' : 'failed'] += 1;
    return map;
}, {});
const report = {
    suite: 'CIVILINA Reasoning Core V2 validation',
    generatedAt: new Date().toISOString(),
    version: core.REASONING_CORE_VERSION,
    total: tests.length,
    passed: tests.length - failed.length,
    failed: failed.length,
    categories,
    failures: failed.slice(0, 100),
    passedGate: failed.length === 0 && tests.length >= 300
};
fs.writeFileSync(path.join(here, 'reasoning-v2-validation.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report));
if (!report.passedGate) process.exitCode = 1;


