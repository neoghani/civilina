import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const here = path.dirname(fileURLToPath(import.meta.url));

const seeds = [
    ['project-controls', 'Analyser la baseline, l actual, le forecast et les jalons du projet.', 'projects', 'planning'],
    ['wbs', 'Revoir la WBS, les work packages, zones et ouvrages.', 'projects', 'wbs'],
    ['evm', 'Expliquer PV EV AC SPI CPI EAC ETC et VAC.', 'executiveControl', 'evm'],
    ['progress', 'Comparer le progrès planifié, exécuté, accepté, valorisé et prévisionnel.', 'projects', 'progressControl'],
    ['cost', 'Analyser budget, engagements, coûts réels et forecast à terminaison.', 'executiveControl', 'costControl'],
    ['risk', 'Prioriser le registre des risques, probabilités, impacts et propriétaires.', 'projects', 'riskManagement'],
    ['changes', 'Auditer les changements, variations, notices et impacts coût délai.', 'projects', 'changeControl'],
    ['contracts', 'Revoir claim, entitlement et preuves contemporaines du contrat joint.', 'projects', 'contractsClaims'],
    ['engineering', 'Diagnostiquer drawings, RFI, submittals et transmittals en attente.', 'projects', 'technicalDocuments'],
    ['quality', 'Analyser ITP, inspection, NCR et actions correctives.', 'managementSystems', 'quality'],
    ['hse', 'Analyser incidents, near misses, permis de travail et actions HSE.', 'managementSystems', 'hse'],
    ['construction', 'Diagnostiquer ressources, équipements et productivité chantier.', 'projects', 'construction'],
    ['procurement', 'Analyser PO, fournisseurs, long lead items et dates de livraison.', 'projects', 'procurement'],
    ['organisation', 'Concevoir une gouvernance RACI et des responsabilités de processus.', 'organisation', 'organisation'],
    ['management-system', 'Revoir le système ISO 9001 et la maîtrise documentaire.', 'managementSystems', 'managementSystem'],
    ['digital', 'Concevoir le workflow ERP API et son automatisation.', 'digitalisation', 'digitalisation'],
    ['data', 'Diagnostiquer la qualité des KPI, sources, propriétaires et réconciliation.', 'digitalisation', 'dataGovernance'],
    ['executive', 'DG : quels projets nécessitent mon attention et quels arbitrages préparer ?', 'executiveControl', 'executiveControl'],
    ['people', 'Structurer des critères professionnels pour comparer deux candidats sans décision automatique.', 'people', 'people']
];
const contexts = ['Projet EPC.', 'Portefeuille multi-projets.', 'Décision cette semaine.', 'Données encore partielles.'];
const tests = [];

seeds.forEach(([id, text, domain, concept]) => {
    contexts.forEach((context, index) => {
        const input = `${text} ${context}`;
        const result = core.evaluate(input);
        const checks = {
            domain: result.primaryDomain === domain || result.relatedDomains.includes(domain),
            concept: result.recognizedConcepts.includes(concept),
            evidence: result.recommendations.every(item => item.evidence.length > 0 && Boolean(item.justification)),
            ranking: result.immediateRecommendations.length <= 3 && result.exploreRecommendations.length <= 2,
            blueprint: Boolean(result.responseBlueprint?.answerabilityStatus && result.responseBlueprint?.objective)
        };
        tests.push({ name: `${id}-${index + 1}`, input, checks, passed: Object.values(checks).every(Boolean) });
    });
});

const failed = tests.filter(test => !test.passed);
const report = { suite: 'CIVILINA V2 expert domain validation', generatedAt: new Date().toISOString(), total: tests.length, passed: tests.length - failed.length, failed: failed.length, failures: failed, passedGate: failed.length === 0 && tests.length >= 60 };
fs.writeFileSync(path.join(here, 'expert-domain-validation.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report));
if (!report.passedGate) process.exitCode = 1;


