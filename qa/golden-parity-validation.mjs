import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const dataset = JSON.parse(fs.readFileSync(path.join(here, 'golden-reasoning-cases.json'), 'utf8'));

function actualFrom(result) {
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

const tests = dataset.cases.map(testCase => {
    const actual = actualFrom(core.evaluate(testCase.input));
    const passed = JSON.stringify(actual) === JSON.stringify(testCase.expected);
    return { id: testCase.id, description: testCase.description, passed, expected: testCase.expected, actual: passed ? undefined : actual };
});
const passed = tests.filter(test => test.passed).length;
const report = {
    suite: 'Civilina Golden Reasoning Parity',
    contractVersion: dataset.reasoningCoreVersion,
    runtimeVersion: core.REASONING_CORE_VERSION,
    total: tests.length,
    passed,
    failed: tests.length - passed,
    tests
};
fs.writeFileSync(path.join(here, 'golden-parity-validation.json'), JSON.stringify(report, null, 2));
console.log(JSON.stringify({ total: report.total, passed: report.passed, failed: report.failed, contractVersion: report.contractVersion, runtimeVersion: report.runtimeVersion }, null, 2));
if (report.failed || report.contractVersion !== report.runtimeVersion) process.exitCode = 1;


