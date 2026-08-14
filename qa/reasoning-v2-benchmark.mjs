import { loadCivilina } from './load-civilina-cjs.mjs';
import fs from 'node:fs';
import path from 'node:path';
import { performance } from 'node:perf_hooks';
import { fileURLToPath } from 'node:url';

const core = loadCivilina('civilina-ai-reasoning-v2.js');
const here = path.dirname(fileURLToPath(import.meta.url));
const prompts = [
    'Pourquoi notre projet EPC est-il en retard sans cause identifiée ?',
    'DG : quels projets menacent notre marge ce mois ?',
    'Analyser EVM avec PV 100, EV 80 et AC 90.',
    'Comparer avancement planifié, exécuté, accepté et valorisé.',
    'Analyser drawings, RFI, ITP, NCR, procurement et claims.'
];
const iterations = 10000;
const startedAt = performance.now();
for (let index = 0; index < iterations; index += 1) core.evaluate(prompts[index % prompts.length]);
const durationMs = performance.now() - startedAt;
const report = {
    suite: 'CIVILINA Reasoning Core V2 benchmark',
    generatedAt: new Date().toISOString(),
    iterations,
    durationMs: Number(durationMs.toFixed(2)),
    averageMs: Number((durationMs / iterations).toFixed(4)),
    evaluationsPerSecond: Math.round(iterations / (durationMs / 1000)),
    passedGate: durationMs / iterations < 5
};
fs.writeFileSync(path.join(here, 'reasoning-v2-benchmark.json'), `${JSON.stringify(report, null, 2)}\n`);
console.log(JSON.stringify(report));
if (!report.passedGate) process.exitCode = 1;


