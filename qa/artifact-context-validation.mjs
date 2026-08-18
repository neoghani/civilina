
import { loadCivilina } from './load-civilina-cjs.mjs';

const reasoningV2 = loadCivilina(
    'civilina-ai-reasoning-v2.js'
);

const cases = [
    {
        id: 'create-itp-concrete-context',
        input: 'Crée un ITP pour le bétonnage.',
        expect: {
            primaryDomain: 'projects',
            capability: 'quality',
            scopeNot: 'enterprise',
            requestedArtifact: 'itp',
            entityIncludes: ['itp', 'inspection'],
            entityExcludes: ['ncr', 'correctiveAction']
        }
    },
    {
        id: 'create-wbs-project-context',
        input: 'Construis une WBS pour mon projet de construction.',
        expect: {
            primaryDomain: 'projects',
            capability: 'projectControls',
            scopeNot: 'enterprise',
            requestedArtifact: 'wbs',
            entityIncludes: ['wbs']
        }
    },
    {
        id: 'create-risk-register-project-context',
        input: 'Crée un registre des risques pour ce projet.',
        expect: {
            primaryDomain: 'projects',
            capability: 'riskManagement',
            scopeNot: 'enterprise',
            requestedArtifact: 'riskRegister'
        }
    }
];

let passed = 0;
let failed = 0;

function getEntityIds(result) {
    return (result.entities || []).map(function (entity) {
        return typeof entity === 'string'
            ? entity
            : entity.id;
    });
}

function getScopeLevel(result) {
    if (!result.scope) {
        return null;
    }

    return typeof result.scope === 'string'
        ? result.scope
        : result.scope.level;
}

function check(condition, message, failures) {
    if (!condition) {
        failures.push(message);
    }
}

cases.forEach(function (testCase) {
    const result = reasoningV2.evaluate(testCase.input);

    const failures = [];
    const entityIds = getEntityIds(result);
    const scopeLevel = getScopeLevel(result);

    check(
        result.primaryDomain === testCase.expect.primaryDomain,
        'primaryDomain expected=' +
            testCase.expect.primaryDomain +
            ' actual=' +
            result.primaryDomain,
        failures
    );

    check(
        (result.capabilities || []).includes(testCase.expect.capability),
        'capability expected=' +
            testCase.expect.capability +
            ' actual=' +
            JSON.stringify(result.capabilities || []),
        failures
    );

    check(
        scopeLevel !== testCase.expect.scopeNot,
        'scope must not be ' +
            testCase.expect.scopeNot +
            ' actual=' +
            scopeLevel,
        failures
    );

    check(
        result.requestedArtifact &&
            result.requestedArtifact.id ===
                testCase.expect.requestedArtifact,
        'requestedArtifact expected=' +
            testCase.expect.requestedArtifact +
            ' actual=' +
            (
                result.requestedArtifact
                    ? result.requestedArtifact.id
                    : null
            ),
        failures
    );

    (testCase.expect.entityIncludes || []).forEach(function (entityId) {
        check(
            entityIds.includes(entityId),
            'missing entity=' + entityId +
                ' actual=' + JSON.stringify(entityIds),
            failures
        );
    });

    (testCase.expect.entityExcludes || []).forEach(function (entityId) {
        check(
            !entityIds.includes(entityId),
            'unexpected entity=' + entityId +
                ' actual=' + JSON.stringify(entityIds),
            failures
        );
    });

    if (failures.length === 0) {
        passed += 1;
        console.log(
            'PASS ' + testCase.id
        );
    } else {
        failed += 1;

        console.log(
            'FAIL ' + testCase.id
        );

        failures.forEach(function (failure) {
            console.log(
                '  - ' + failure
            );
        });
    }
});

console.log('');
console.log(
    'Artifact Context Sprint 2.4: ' +
        passed +
        '/' +
        cases.length +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}