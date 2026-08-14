import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const lexicon = loadCivilina('civilina-ai-lexicon.js');
const ontology = loadCivilina('civilina-ai-ontology.js');
const questions = loadCivilina('civilina-ai-questions-v2.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
const outputDirectory = path.join(here, 'golden-v2');
fs.mkdirSync(outputDirectory, { recursive: true });

const requiredCases = [
    'Pourquoi notre projet EPC est-il en retard sans cause identifiée ?',
    'DG : quels projets menacent notre marge ce mois ?',
    'Calcule le SPI, CPI, EAC et VAC du projet.',
    'Chef de projet : analyser EVM avec PV 100, EV 80 et AC 90 à la date de statut.',
    'Concevoir une WBS du périmètre et la relier aux zones et ouvrages.',
    'Comparer avancement planifié, exécuté, accepté, valorisé et forecast.',
    'Analyser les drawings, RFI, submittals et transmittals bloqués.',
    'Auditer ce document joint : ITP, inspections, NCR et actions correctives.',
    'Danger imminent sur chantier : que devons-nous décider aujourd hui ?',
    'Pourquoi les long lead items arrivent-ils après la date besoin du planning ?',
    'Interprétation contractuelle de ce claim et entitlement.',
    'Diagnostiquer la productivité chantier inférieure au forecast.',
    'Créer une gouvernance RACI et clarifier qui décide dans le processus.',
    'Auditer notre système ISO 9001 et la maîtrise documentaire.',
    'Pourquoi les KPI ont-ils des valeurs différentes selon les dashboards et sources ?',
    'Concevoir l intégration ERP API et automatiser le workflow.',
    'Comparer deux candidats avec leur MBTI pour choisir le meilleur.',
    'Quelle est l édition actuelle de la norme applicable ?',
    'Je veux vous envoyer nos factures, marges et contrats.',
    'Le retard pourrait être causé par le fournisseur.',
    'Le retard est causé par une livraison fournisseur confirmée.',
    'Je dois manager un projet EPC.',
    'Analyser le profil de risque du projet.',
    'Créer un dashboard de pilotage projet.',
    'Critiquer notre baseline planning.',
    'Critiquer ce document joint de baseline planning.'
];

const lexiconCases = lexicon.entries
    .filter(entry => ['concept', 'entity', 'symptom', 'interactionMode', 'persona', 'scope', 'horizon', 'flag'].includes(entry.category))
    .flatMap(entry => entry.terms.slice(0, 2).map(term => `${term}. Décision à préparer.`));
const inputs = Array.from(new Set(requiredCases.concat(lexiconCases))).slice(0, 180);

function snapshot(result) {
    const copy = JSON.parse(JSON.stringify(result));
    delete copy.legacyContract;
    return copy;
}

const dataset = {
    contract: 'CIVILINA_REASONING_GOLDEN_V2',
    version: core.REASONING_CORE_VERSION,
    generatedAt: new Date().toISOString(),
    cases: inputs.map((freeText, index) => ({ id: `V2-${String(index + 1).padStart(3, '0')}`, input: { freeText, answers: {} }, expected: snapshot(core.evaluate({ freeText, answers: {} })) }))
};
fs.writeFileSync(path.join(outputDirectory, 'golden-reasoning-v2.json'), `${JSON.stringify(dataset, null, 2)}\n`);

const registry = {
    version: ontology.VERSION,
    interactionModes: ontology.INTERACTION_MODES,
    personas: ontology.PERSONAS,
    businessScopes: ontology.BUSINESS_SCOPES,
    businessDomains: ontology.BUSINESS_DOMAINS,
    capabilities: ontology.CAPABILITIES,
    businessEntities: ontology.BUSINESS_ENTITIES,
    controlDimensions: ontology.CONTROL_DIMENSIONS,
    answerabilityStatuses: ontology.ANSWERABILITY_STATUSES,
    decisionHorizons: ontology.DECISION_HORIZONS,
    expertLenses: ontology.EXPERT_LENSES,
    conceptMap: ontology.CONCEPT_MAP,
    recommendations: ontology.RECOMMENDATIONS,
    lexicon: lexicon.entries,
    questions: questions.QUESTION_CATALOG
};
fs.mkdirSync(path.join(here, 'contracts'), { recursive: true });
fs.writeFileSync(path.join(here, 'contracts', 'reasoning-v2-registry.json'), `${JSON.stringify(registry, null, 2)}\n`);
const runtimeDataDirectory = path.join(root, 'resources', 'data', 'civilina-ai');
fs.mkdirSync(runtimeDataDirectory, { recursive: true });
fs.writeFileSync(path.join(runtimeDataDirectory, 'reasoning-v2-registry.json'), `${JSON.stringify(registry, null, 2)}\n`);
const fixtureDirectory = path.join(root, 'tests', 'Fixtures');
fs.mkdirSync(fixtureDirectory, { recursive: true });
fs.writeFileSync(path.join(fixtureDirectory, 'golden-reasoning-v2.json'), `${JSON.stringify(dataset, null, 2)}\n`);
console.log(JSON.stringify({ version: dataset.version, cases: dataset.cases.length, registryEntries: lexicon.entries.length }));


