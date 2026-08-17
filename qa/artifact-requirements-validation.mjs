import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina('civilina-ai-reasoning-v2.js');

const cases = [
    [
        'itp',
        'Cree un ITP.',
        'ITP_ACTIVITY_SCOPE_REQUIRED'
    ],
    [
        'wbs',
        'Construis une WBS.',
        'WBS_SCOPE_BASIS_REQUIRED'
    ],
    [
        'risk-register',
        'Cree un registre des risques.',
        'RISK_SCOPE_REQUIRED'
    ],
    [
        'raci',
        'Cree une matrice RACI.',
        'RACI_PROCESS_SCOPE_REQUIRED'
    ],
    [
        'dashboard',
        'Cree un dashboard.',
        'DASHBOARD_DECISION_REQUIRED'
    ],
    [
        'workflow',
        'Concois un workflow.',
        'WORKFLOW_SCOPE_REQUIRED'
    ]
];

let failed = 0;

for (const [id, text, expectedRequirement] of cases) {
    const result = core.evaluate(text);

    const actual =
        result.artifactReadiness?.nextRequirement?.id ||
        null;

    const passed = actual === expectedRequirement;

    console.log(
        `${passed ? 'PASS' : 'FAIL'} ${id} | expected=${expectedRequirement} | actual=${actual}`
    );

    if (!passed) failed += 1;
}

console.log(
    `\nArtifact Requirements Sprint 2.3: ${cases.length - failed}/${cases.length} passed`
);

if (failed > 0) {
    process.exitCode = 1;
}