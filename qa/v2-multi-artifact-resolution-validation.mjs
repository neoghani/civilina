import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina('civilina-ai-reasoning-v2.js');

let passed = 0;
let failed = 0;

function assert(condition, message) {
    if (!condition) throw new Error(message);
}

function run(name, verify) {
    try {
        verify();
        passed += 1;
        console.log('PASS ' + name);
    } catch (error) {
        failed += 1;
        console.log('FAIL ' + name);
        console.log('  - ' + error.message);
    }
}

function evaluate(text, answers) {
    return core.evaluate({
        freeText: text || 'Crée un ITP et une WBS pour le projet SP-01.',
        answers: answers || {},
        seedFacts: []
    });
}

run('wbs-itp-default-follows-business-dependency', function () {
    const result = evaluate();

    assert(result.artifactAmbiguity === false, 'WBS + ITP ne doit plus rester ambigu lorsque la dépendance métier est connue.');
    assert(result.requestedArtifact && result.requestedArtifact.id === 'wbs', 'La WBS doit être priorisée avant l’ITP.');
});

run('wbs-itp-exposes-dependency-decision', function () {
    const result = evaluate();

    assert(result.artifactDependencyDecision, 'La décision de dépendance doit être exposée.');
    assert(result.artifactDependencyDecision.rule === 'WBS_BEFORE_ITP', 'La règle WBS_BEFORE_ITP est attendue.');
    assert(result.artifactDependencyDecision.dependentArtifactId === 'itp', 'ITP doit être identifié comme artefact dépendant.');
});

run('dependency-order-activates-wbs-readiness', function () {
    const result = evaluate();

    assert(result.artifactReadiness && result.artifactReadiness.applicable === true, 'La readiness WBS doit être applicable.');
    assert(result.artifactReadiness.artifactId === 'wbs', 'La readiness doit correspondre à la WBS priorisée.');
    assert(result.nextBestQuestion && result.nextBestQuestion.source === 'artifactRequirement', 'La prochaine question doit être une exigence WBS, pas une clarification générique.');
});

run('explicit-itp-first-overrides-default-dependency', function () {
    const result = evaluate('Crée un ITP et une WBS pour le projet SP-01, mais commence par ITP.');

    assert(result.artifactAmbiguity === false, 'Un ordre explicite doit lever toute ambiguïté.');
    assert(result.requestedArtifact && result.requestedArtifact.id === 'itp', 'La demande explicite de commencer par ITP doit être respectée.');
    assert(!result.artifactDependencyDecision, 'La règle automatique ne doit pas remplacer un ordre utilisateur explicite.');
});

run('explicit-answer-selection-can-still-override-order', function () {
    const result = evaluate(null, { artifactSelection: 'itp' });

    assert(result.artifactAmbiguity === false, 'Une sélection ITP valide doit être acceptée.');
    assert(result.requestedArtifact && result.requestedArtifact.id === 'itp', 'ITP doit devenir le livrable demandé après sélection explicite.');
});

run('invalid-selection-keeps-ambiguity', function () {
    const result = evaluate(null, { artifactSelection: 'autre' });

    assert(result.artifactAmbiguity === true, 'Une sélection inconnue ne doit pas déclencher la priorité automatique.');
    assert(result.requestedArtifact === null, 'Une sélection inconnue ne doit choisir aucun livrable.');
});

console.log('');
console.log('V2 Artifact Dependency Orchestration Sprint 2.8: ' + passed + '/' + (passed + failed) + ' passed');

if (failed > 0) process.exitCode = 1;
