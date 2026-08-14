import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.resolve(here, '..');
const captures = path.join(root, 'captures');
fs.mkdirSync(captures, { recursive: true });
const origin = process.env.CIVILINA_BASE_URL || 'http://127.0.0.1:8000';
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const widths = [1440, 1024, 768, 430, 390];

async function startTyped(page, text) {
    await page.locator('#ai-need').fill(text);
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
}

async function answer(page, values) {
    for (const value of values) await page.locator('#ai-suggestions button', { hasText: value }).click();
}

const states = [
    {
        name: 'conversation-longue',
        prepare: async page => {
            await page.locator('#ai-suggestions button', { hasText: 'Piloter mes projets' }).click();
            await answer(page, ['Consolider coûts et délais', 'Plusieurs outils']);
        },
    },
    { name: 'clarification', prepare: page => startTyped(page, 'Nous avons du mal à piloter.') },
    {
        name: 'confirmation',
        prepare: async page => {
            await page.locator('#ai-suggestions button', { hasText: 'Piloter mes projets' }).click();
            await answer(page, ['Consolider coûts et délais', 'Plusieurs outils', 'Plus de 10 projets', 'Construction / EPC']);
        },
    },
    {
        name: 'multi-domain',
        prepare: async page => {
            await startTyped(page, 'Nous préparons ISO 9001, les responsabilités sont floues et nous digitalisons les validations.');
            await answer(page, ['Rôles et responsabilités', 'Déployé mais fragmenté', 'Système intégré', 'Plusieurs outils']);
            await page.locator('[data-action="confirm-context"]').click();
        },
    },
    {
        name: 'recommendations',
        prepare: async page => {
            await page.locator('#ai-suggestions button', { hasText: 'Piloter mes projets' }).click();
            await answer(page, ['Consolider coûts et délais', 'Plusieurs outils', 'Plus de 10 projets', 'Construction / EPC']);
            await page.locator('[data-action="confirm-context"]').click();
            await page.locator('[data-action="show-recommendation"]').click();
        },
    },
    {
        name: 'contradiction',
        prepare: async page => {
            await startTyped(page, 'Nos processus sont bien structurés mais les responsabilités de validation restent peu claires.');
            await answer(page, ['Responsabilités peu claires', 'Clarifier les rôles', 'Workflow formalisé', '20 à 99 personnes']);
        },
    },
    {
        name: 'privacy-alert',
        prepare: async page => {
            await startTyped(page, 'Nous préparons ISO 9001 et je veux transmettre notre contrat confidentiel.');
            await answer(page, ['Rôles et responsabilités', 'Démarrage', 'ISO 9001', 'Excel']);
        },
    },
    {
        name: 'people-guardrail',
        prepare: async page => {
            await startTyped(page, 'J’ai les MBTI de mes salariés. Qui dois-je mettre chef de chantier ?');
            await answer(page, ['Éclairer une décision de recrutement ou d’affectation', 'Éléments encore incomplets', 'Des candidats']);
        },
    },
    {
        name: 'expert-suggestion',
        prepare: async page => {
            await startTyped(page, 'Nous préparons ISO 9001, les responsabilités sont floues et nous digitalisons les validations.');
            await answer(page, ['Rôles et responsabilités', 'Déployé mais fragmenté', 'Système intégré', 'Plusieurs outils']);
            await page.locator('[data-action="confirm-context"]').click();
            await page.locator('[data-action="show-recommendation"]').click();
            await page.locator('[data-action="show-account"]').click();
            await page.getByRole('button', { name: 'Compléter avec un expert' }).click();
        },
    },
];

const report = [];
try {
    for (const width of widths) {
        for (const definition of states) {
            const height = width <= 430 ? 900 : width <= 768 ? 960 : 900;
            const context = await browser.newContext({ viewport: { width, height }, reducedMotion: 'reduce' });
            const page = await context.newPage();
            const consoleErrors = [];
            const pageErrors = [];
            page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
            page.on('pageerror', error => pageErrors.push(error.message));
            await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
            await page.route('https://fonts.gstatic.com/**', route => route.fulfill({ status: 204, body: '' }));
            await page.goto(origin, { waitUntil: 'networkidle' });
            await definition.prepare(page);
            await page.locator('#ai-command').scrollIntoViewIfNeeded();
            await page.waitForTimeout(80);
            const metrics = await page.evaluate(() => ({
                documentWidth: document.documentElement.scrollWidth,
                viewportWidth: document.documentElement.clientWidth,
                commandWidth: document.getElementById('ai-command').getBoundingClientRect().width,
                commandHeight: document.getElementById('ai-command').getBoundingClientRect().height,
                commandClientHeight: document.getElementById('ai-command').clientHeight,
                commandScrollHeight: document.getElementById('ai-command').scrollHeight,
                outcomeBottom: document.getElementById('ai-outcome').getBoundingClientRect().bottom,
                commandBottom: document.getElementById('ai-command').getBoundingClientRect().bottom,
                phase: window.CivilinaAIJourney.getState().phase,
            }));
            if (metrics.documentWidth > metrics.viewportWidth) throw new Error(`${width}/${definition.name}: overflow horizontal`);
            if (metrics.commandScrollHeight > metrics.commandClientHeight + 1 || metrics.outcomeBottom > metrics.commandBottom + 1) throw new Error(`${width}/${definition.name}: contenu vertical masque`);
            if (consoleErrors.length || pageErrors.length) throw new Error(`${width}/${definition.name}: erreur console/page`);
            if (metrics.commandHeight > height - 40) await page.setViewportSize({ width, height: Math.ceil(metrics.commandHeight) + 40 });
            const file = path.join(captures, `${width}-${definition.name}.png`);
            await page.locator('#ai-command').screenshot({ path: file });
            report.push({ width, state: definition.name, file, ...metrics, consoleErrors: 0, pageErrors: 0 });
            await context.close();
        }
    }
    fs.writeFileSync(path.join(here, 'responsive-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ captures: report.length, widths, states: states.length, overflowFailures: 0, consoleErrors: 0 }, null, 2));
} finally {
    await browser.close();
}
