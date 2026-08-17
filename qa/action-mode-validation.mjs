import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina('civilina-ai-reasoning-v2.js');

const cases = [
    ['create-itp', 'Cree un ITP pour le betonnage.', 'create'],
    ['create-risk-register', 'Cree un registre des risques projet.', 'create'],
    ['create-wbs', 'Construis une WBS pour ce projet EPC.', 'create'],
    ['audit-procurement', 'Audite notre processus achats et fournisseurs.', 'audit'],
    ['audit-quality', 'Audite notre processus qualite et les NCR.', 'audit'],
    ['design-workflow', 'Concois un workflow de validation documentaire.', 'design']
];

let failed = 0;

for (const [id, text, expectedMode] of cases) {
    const result = core.evaluate(text);
    const passed = result.interactionMode === expectedMode;

    console.log(
        `${passed ? 'PASS' : 'FAIL'} ${id} | expected=${expectedMode} | actual=${result.interactionMode}`
    );

    if (!passed) failed += 1;
}

console.log(`\nAction Mode Sprint 2.3: ${cases.length - failed}/${cases.length} passed`);

if (failed > 0) {
    process.exitCode = 1;
}