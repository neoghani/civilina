import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const dataset = JSON.parse(fs.readFileSync(path.join(here, 'golden-v2', 'golden-reasoning-v2.json'), 'utf8'));

function snapshot(result) {
    const copy = JSON.parse(JSON.stringify(result));
    delete copy.legacyContract;
    return copy;
}

const tests = dataset.cases.map(testCase => {
    const actual = snapshot(core.evaluate(testCase.input));
    const passed = JSON.stringify(actual) === JSON.stringify(testCase.expected);
    return { id: testCase.id, passed, error: passed ? null : 'Le contrat V2 ne correspond plus au Golden Dataset.' };
});
const failed = tests.filter(test => !test.passed);
const report = {
    suite: 'CIVILINA Golden Dataset V2 validation',
    generatedAt: new Date().toISOString(),
    contract: dataset.contract,
    contractVersion: dataset.version,
    runtimeVersion: core.REASONING_CORE_VERSION,
    total: tests.length,
    passed: tests.length - failed.length,
    failed: failed.length,
    failures: failed,
    passedGate: failed.length === 0 && tests.length >= 150 && dataset.version === core.REASONING_CORE_VERSION
};
fs.writeFileSync(path.join(here, 'golden-v2-validation.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report));
if (!report.passedGate) process.exitCode = 1;


