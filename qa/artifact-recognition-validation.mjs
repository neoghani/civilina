import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina('civilina-ai-reasoning-v2.js');

const cases = [
    ['itp', 'Cree un ITP pour le betonnage.', 'itp'],
    ['wbs', 'Construis une WBS pour ce projet EPC.', 'wbs'],
    ['risk-register', 'Cree un registre des risques projet.', 'riskRegister'],
    ['raci', 'Cree une matrice RACI pour le processus achats.', 'raci'],
    ['dashboard', 'Cree un dashboard de pilotage pour la direction.', 'dashboard'],
    ['workflow', 'Concois un workflow de validation documentaire.', 'workflow']
];

let failed = 0;

for (const [id, text, expectedArtifact] of cases) {
    const result = core.evaluate(text);

    const actualArtifact =
        result.requestedArtifact?.id ||
        null;

    const passed = actualArtifact === expectedArtifact;

    console.log(
        `${passed ? 'PASS' : 'FAIL'} ${id} | expected=${expectedArtifact} | actual=${actualArtifact}`
    );

    if (!passed) failed += 1;
}

console.log(
    `\nArtifact Recognition Sprint 2.3: ${cases.length - failed}/${cases.length} passed`
);

if (failed > 0) {
    process.exitCode = 1;
}