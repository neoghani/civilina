import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const privacy = loadCivilina('civilina-ai-privacy.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const tests = [];

function record(name, category, expected, actual, input) {
    tests.push({ name, category, input, expected, actual, passed: expected === actual });
}

const sensitiveObjects = [
    'notre contrat', 'notre devis', 'une facture', 'les fiches de paie', 'les salaires',
    'nos marges', 'notre tresorerie', 'notre liste clients', 'nos donnees fournisseurs',
    'nos prix confidentiels', 'notre offre commerciale', 'notre budget',
    'nos donnees financieres', 'une procedure interne', 'un document interne',
    'nos donnees RH', 'un document confidentiel', 'notre NDA',
];

sensitiveObjects.forEach((object, index) => {
    const input = `Je veux partager ${object}.`;
    record(`${String(index + 1).padStart(2, '0')} transmit intent plus sensitive object`, 'privacy-positive', true, privacy.detectSensitiveIntent(input), input);
});

const sensitiveWithoutTransmission = [
    'Comment mieux gerer nos contrats ?',
    'Nous avons plusieurs devis a structurer.',
    'Le suivi des factures est lent.',
    'Notre paie doit etre fiabilisee.',
    'Nous voulons mieux piloter les marges.',
    'La tresorerie est un enjeu de pilotage.',
    'Il faut segmenter les clients.',
    'Nous evaluons nos fournisseurs.',
    'La politique de prix doit etre clarifiee.',
    'Comment construire une offre commerciale ?',
    'Nous travaillons sur le budget annuel.',
    'Notre procedure interne doit etre simplifiee.',
];

sensitiveWithoutTransmission.forEach((input, index) => {
    record(`${String(index + 19).padStart(2, '0')} sensitive object without transmit intent`, 'privacy-negative', false, privacy.detectSensitiveIntent(input), input);
});

const ordinaryTransmission = [
    'Je veux partager une idee generale.',
    'Je peux envoyer une question sans document.',
    'Je souhaite transmettre un retour public.',
    'Je veux montrer le fonctionnement general.',
];

ordinaryTransmission.forEach((input, index) => {
    record(`${String(index + 31).padStart(2, '0')} transmit intent without sensitive object`, 'privacy-negative', false, privacy.detectSensitiveIntent(input), input);
});

const peopleCases = [
    ['35 manager project is not people', 'Comment manager un projet EPC ?', false, false],
    ['36 project risk profile is not people', 'Construire le profil de risque du projet.', false, false],
    ['37 candidate profile is people', 'Analyser le profil candidat.', true, false],
    ['38 MBTI discussion is people but not decision', 'Le MBTI comme support de discussion.', true, false],
    ['39 candidate selection is people decision', 'Quel candidat choisir pour ce poste ?', true, true],
    ['40 employee promotion is people decision', 'Qui devrait promouvoir cet employe ?', true, true],
];

peopleCases.forEach(([name, input, signal, decision]) => {
    record(`${name} signal`, 'people-context', signal, privacy.detectPeopleSignal(input), input);
    record(`${name} decision`, 'people-context', decision, privacy.detectPeopleDecision(input), input);
});

const secretPhrase = 'MARGE-SECRETE-ALPHA-2026';
const stored = privacy.buildSafeStorageState({
    version: 3,
    entryIntent: 'projects',
    phase: 'conversation',
    step: 1,
    answers: { difficulty: 'cost-schedule', shortAnswer: 'reponse libre courte', longAnswer: secretPhrase.repeat(5) },
    answerLabels: { difficulty: 'Consolider couts et delais' },
    derivedTextFacts: ['domain:projects'],
    questionCount: 1,
    messages: [
        { role: 'assistant', text: 'Question Civilina' },
        { role: 'user', text: `Je veux partager notre contrat ${secretPhrase}` },
    ],
    initialText: secretPhrase,
    sensitiveIntent: true,
    peopleGuardrail: false,
});
const serialized = JSON.stringify(stored);
record('41 initial free text is excluded from storage', 'storage', false, Object.hasOwn(stored, 'initialText'), 'initialText');
record('42 short free answer is redacted', 'storage', '[free-text-answer]', stored.answers.shortAnswer, 'answers.shortAnswer');
record('43 long free answer is redacted', 'storage', '[free-text-answer]', stored.answers.longAnswer, 'answers.longAnswer');
record('44 sensitive user message is redacted', 'storage', '[Information sensible non conservée]', stored.messages[1].text, 'messages[1]');
record('45 secret phrase does not leak', 'storage', false, serialized.includes(secretPhrase), 'serialized storage');
record('46 assistant message remains available', 'storage', 'Question Civilina', stored.messages[0].text, 'assistant message');
record('47 state version remains explicit', 'storage', 3, stored.version, 'version');

const passed = tests.filter(test => test.passed).length;
const categories = tests.reduce((result, test) => {
    if (!result[test.category]) result[test.category] = { total: 0, passed: 0, failed: 0 };
    result[test.category].total += 1;
    result[test.category][test.passed ? 'passed' : 'failed'] += 1;
    return result;
}, {});
const report = { suite: 'Civilina Privacy and Storage Adversarial', total: tests.length, passed, failed: tests.length - passed, categories, tests };
fs.writeFileSync(path.join(here, 'privacy-validation.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({ total: report.total, passed: report.passed, failed: report.failed, categories }, null, 2));
if (report.failed) {
    tests.filter(test => !test.passed).forEach(test => console.error(`${test.name}: expected ${test.expected}, got ${test.actual}`));
    process.exitCode = 1;
}


