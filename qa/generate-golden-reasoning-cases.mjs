import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning.js');
const here = path.dirname(fileURLToPath(import.meta.url));

const cases = [
    ['project-empty', 'Entrée projet sans précision', { entryIntent: 'projects', answers: {} }],
    ['project-cost-schedule', 'Projet EPC avec enjeu coûts-délais', { entryIntent: 'projects', answers: { activity: 'epc', portfolio: 'large', tracking: 'multiple-tools', difficulty: 'cost-schedule' } }],
    ['project-delay-unknown', 'Retard projet de cause inconnue', { freeText: 'Le projet Atlas est en retard.', answers: {} }],
    ['project-delay-planning', 'Retard causé par la planification', { entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'planning' } }],
    ['project-delay-procurement', 'Retard causé par les approvisionnements', { freeText: 'Le projet est en retard à cause des approvisionnements.', answers: {} }],
    ['project-delay-resources', 'Retard causé par les ressources', { entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'resources' } }],
    ['project-delay-engineering', 'Retard causé par les validations techniques', { entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'engineering' } }],
    ['project-delay-approvals', 'Retard causé par les approbations', { entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'approvals' } }],
    ['project-delay-multiple', 'Retard à causes multiples', { entryIntent: 'projects', freeText: 'Le projet est en retard.', answers: { delayCause: 'multiple' } }],
    ['project-subcontractor', 'Sous-traitant cité sans cause racine', { freeText: 'Le projet est en retard à cause du sous-traitant.', answers: {} }],
    ['project-ifc', 'Documents techniques IFC', { freeText: 'Plans IFC et drawings à maîtriser.', answers: {} }],
    ['project-epc-context', 'Contexte EPC seul', { entryIntent: 'projects', answers: { activity: 'epc' } }],

    ['organisation-empty', 'Entrée organisation sans précision', { entryIntent: 'organisation', answers: {} }],
    ['organisation-roles', 'Responsabilités peu claires', { entryIntent: 'organisation', answers: { issue: 'roles', objective: 'clarify-roles' } }],
    ['organisation-approvals', 'Validations lentes', { entryIntent: 'organisation', answers: { issue: 'approvals', decisions: 'email' } }],
    ['organisation-process', 'Processus variables', { entryIntent: 'organisation', answers: { issue: 'processes', objective: 'standardize' } }],
    ['organisation-reporting', 'Reporting de direction', { entryIntent: 'organisation', answers: { issue: 'reporting', objective: 'management-dashboard' } }],
    ['organisation-contradiction', 'Processus structuré et rôles flous', { entryIntent: 'organisation', answers: { processMaturity: 'structured', responsibilityClarity: 'low' } }],
    ['organisation-text', 'Demande libre de structuration', { freeText: 'Structurer notre organisation et clarifier qui valide.', answers: {} }],
    ['organisation-digital-approval', 'Organisation et digitalisation des validations', { freeText: 'Digitaliser les validations et clarifier les responsabilités.', answers: {} }],

    ['management-empty', 'Entrée système de management', { entryIntent: 'managementSystems', answers: {} }],
    ['management-iso9001', 'Structuration ISO 9001', { freeText: 'Structurer notre système ISO 9001.', answers: {} }],
    ['management-documents', 'Maîtrise documentaire qualité', { entryIntent: 'managementSystems', answers: { reference: 'iso-9001', maturity: 'fragmented', difficulty: 'documents' } }],
    ['management-audits', 'Audits et actions', { entryIntent: 'managementSystems', answers: { difficulty: 'audits-actions' } }],
    ['management-indicators', 'Indicateurs du système', { entryIntent: 'managementSystems', answers: { difficulty: 'indicators' } }],
    ['management-quality-docs', 'Documents qualité en GED', { freeText: 'Centraliser les documents qualité dans une GED.', answers: {} }],
    ['management-cross-project', 'ISO et retard projet', { entryIntent: 'managementSystems', freeText: 'ISO 9001 et projet en retard.', answers: {} }],
    ['management-contradiction', 'Système en amélioration mais fragmenté', { entryIntent: 'managementSystems', seedFacts: ['management:fragmented'], answers: { maturity: 'improving' } }],

    ['digital-empty', 'Entrée digitalisation sans précision', { entryIntent: 'digitalisation', answers: {} }],
    ['digital-approval', 'Workflow de validation', { entryIntent: 'digitalisation', answers: { process: 'approvals', difficulty: 'traceability', objective: 'workflow' } }],
    ['digital-documents', 'Digitalisation des documents qualité', { freeText: 'Digitaliser la maîtrise documentaire qualité.', answers: {} }],
    ['digital-reporting', 'Consolidation du reporting', { entryIntent: 'digitalisation', answers: { process: 'reporting', objective: 'dashboard' } }],
    ['digital-automation', 'Réduction de la double saisie', { entryIntent: 'digitalisation', answers: { difficulty: 'reentry', objective: 'automation' } }],
    ['digital-procurement', 'Digitalisation du processus achats', { entryIntent: 'digitalisation', answers: { process: 'procurement' } }],
    ['digital-traceability', 'Traçabilité digitale', { entryIntent: 'digitalisation', answers: { difficulty: 'traceability' } }],
    ['digital-weak-signal', 'Signal digital faible', { freeText: 'Je veux digitaliser.', answers: {} }],

    ['people-manager-atlas', 'Nomination manager projet Atlas', { freeText: 'Quel manager mettre sur le projet Atlas ?', answers: {} }],
    ['people-promote-chief', 'Promotion chef de projet', { freeText: 'Promouvoir notre meilleur chef de projet.', answers: {} }],
    ['people-site-responsible', 'Nomination responsable chantier', { freeText: 'Qui nommer responsable chantier ?', answers: {} }],
    ['people-project-direction', 'Prise de direction projet', { freeText: 'Qui peut prendre la direction de ce projet ?', answers: {} }],
    ['people-mbti-discussion', 'MBTI comme support de discussion', { freeText: 'Utiliser le MBTI comme support de discussion dans une équipe.', answers: {} }],
    ['people-development', 'Développement professionnel', { entryIntent: 'people', answers: { peopleObjective: 'development', peopleScope: 'individual', peopleCriteria: 'skills-experience' } }],
    ['people-incomplete', 'Critères humains incomplets', { entryIntent: 'people', answers: { peopleObjective: 'development', peopleCriteria: 'incomplete' } }],
    ['people-team-collaboration', 'Collaboration équipe projet', { freeText: 'Notre équipe projet doit mieux collaborer.', answers: {} }],

    ['privacy-business-plan', 'Partage de business plan', { freeText: 'Je vais partager notre business plan.', answers: {} }],
    ['privacy-financial-forecast', 'Transmission de prévisions financières', { freeText: 'Je souhaite transmettre nos prévisions financières.', answers: {} }],
    ['privacy-pl', 'Envoi de P&L', { freeText: 'Je vais envoyer le P&L.', answers: {} }],
    ['privacy-bpu-dqe', 'Upload BPU et DQE', { freeText: 'Je vais uploader le BPU et le DQE.', answers: {} }],
    ['privacy-no-share', 'Business plan sans partage', { freeText: 'Nous devons préparer un business plan.', answers: {} }],
    ['privacy-negated', 'Prévisions non transmises', { freeText: 'Analyser les prévisions financières sans les transmettre.', answers: {} }],

    ['ambiguous-empty', 'Entrée vide', { answers: {} }],
    ['ambiguous-pilot', 'Demande de pilotage ambiguë', { freeText: 'Nous avons du mal à piloter.', answers: {} }],
    ['negative-manager-project', 'Manager un projet sans décision RH', { freeText: 'Comment manager un projet EPC complexe ?', answers: {} }],
    ['negative-risk-profile', 'Profil de risque projet', { freeText: 'Construire le profil de risque du projet.', answers: {} }],
    ['negative-site-management', 'Management de chantier', { freeText: 'Améliorer le management du chantier.', answers: {} }]
];

function expectedFrom(result) {
    return {
        entryIntent: result.entryIntent,
        primaryDomain: result.primaryDomain,
        relatedDomains: result.relatedDomains,
        capabilities: result.capabilities,
        immediate: result.recommendations.immediate.map(item => item.id),
        explore: result.recommendations.explore.map(item => item.id),
        peopleGuardrail: result.peopleGuardrail,
        sensitiveIntent: result.sensitiveIntent,
        expertSuggested: result.expertSuggested,
        uncertainties: result.uncertainties.map(item => item.id),
        blockingUncertainty: result.uncertainties.some(item => item.blocking === true)
    };
}

const dataset = {
    contract: 'Civilina Reasoning Core V1 parity contract',
    reasoningCoreVersion: core.REASONING_CORE_VERSION,
    generatedAt: new Date().toISOString(),
    caseCount: cases.length,
    cases: cases.map(([id, description, input]) => ({ id, description, input, expected: expectedFrom(core.evaluate(input)) }))
};

fs.writeFileSync(path.join(here, 'golden-reasoning-cases.json'), JSON.stringify(dataset, null, 2));
console.log(JSON.stringify({ file: 'qa/golden-reasoning-cases.json', cases: dataset.caseCount, reasoningCoreVersion: dataset.reasoningCoreVersion }, null, 2));


