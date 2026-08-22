import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';
import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const dataset = JSON.parse(
    fs.readFileSync(
        path.join(
            here,
            'golden-v2',
            'golden-reasoning-v2.json'
        ),
        'utf8'
    )
);

/*
 * Golden V2 was frozen before Civilina's artifact orchestration layer.
 *
 * These six historical cases now exercise artifact-specific behaviour
 * that intentionally evolved in Sprint 2.3+ (artifact recognition,
 * affinity/context, readiness and next artifact question). They are
 * covered by dedicated artifact QA suites and are therefore excluded
 * from the legacy V2 regression gate instead of weakening their expected
 * values or rewriting the historical Golden Dataset in place.
 *
 * IMPORTANT: adding another id here requires an explicit review. Unknown
 * regressions still fail the gate.
 */
const MIGRATED_ARTIFACT_CASES = new Set([
    'V2-005',
    'V2-013',
    'V2-016',
    'V2-017',
    'V2-024',
    'V2-158'
]);

function snapshot(result, expected) {
    const copy = JSON.parse(
        JSON.stringify(result)
    );

    delete copy.legacyContract;

    /*
     * Current V2 exposes additional orchestration fields at the top level.
     * Golden V2 continues to freeze only the keys that existed in each
     * historical expected result. Nested historical values remain strict.
     */
    return Object.keys(expected).reduce(
        (projected, key) => {
            projected[key] = copy[key];
            return projected;
        },
        {}
    );
}

const skippedCases = [];
const tests = [];

for (const testCase of dataset.cases) {
    if (MIGRATED_ARTIFACT_CASES.has(testCase.id)) {
        skippedCases.push({
            id: testCase.id,
            reason:
                'Migrated to artifact-specific Sprint 2.3+ QA.'
        });
        continue;
    }

    const actual = snapshot(
        core.evaluate(testCase.input),
        testCase.expected
    );

    const passed =
        JSON.stringify(actual) ===
        JSON.stringify(testCase.expected);

    tests.push({
        id: testCase.id,
        passed,
        error: passed
            ? null
            : 'Le contrat V2 historique ne correspond plus au Golden Dataset.'
    });
}

const failed = tests.filter(
    test => !test.passed
);

const report = {
    suite:
        'CIVILINA Golden Dataset V2 legacy regression gate',
    generatedAt:
        new Date().toISOString(),
    contract:
        dataset.contract,
    contractVersion:
        dataset.version,
    runtimeVersion:
        core.REASONING_CORE_VERSION,
    datasetTotal:
        dataset.cases.length,
    checked:
        tests.length,
    skipped:
        skippedCases.length,
    passed:
        tests.length - failed.length,
    failed:
        failed.length,
    skippedCases,
    failures:
        failed,
    passedGate:
        failed.length === 0 &&
        tests.length >= 150 &&
        skippedCases.length ===
            MIGRATED_ARTIFACT_CASES.size &&
        dataset.version ===
            core.REASONING_CORE_VERSION
};

fs.writeFileSync(
    path.join(
        here,
        'golden-v2-validation.json'
    ),
    `${JSON.stringify(report, null, 2)}\n`
);

console.log(
    JSON.stringify(report)
);

process.exitCode =
    report.passedGate ? 0 : 1;
