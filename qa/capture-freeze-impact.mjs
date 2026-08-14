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

const states = [
    {
        name: 'people-guardrail-v1',
        prepare: async page => {
            await startTyped(page, 'Quel manager mettre sur le projet Atlas ?');
            for (const answer of ['Éclairer une décision de recrutement ou d’affectation', 'Compétences et expérience', 'Une personne']) {
                await page.locator('#ai-suggestions button', { hasText: answer }).click();
            }
            await page.getByRole('button', { name: 'C’est correct' }).click();
            await page.getByRole('button', { name: 'Voir mon environnement recommandé' }).click();
        }
    },
    {
        name: 'delay-unknown-v1',
        prepare: page => startTyped(page, 'Le projet Atlas est en retard.')
    }
];

const report = [];
try {
    for (const width of widths) {
        for (const state of states) {
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
            await state.prepare(page);
            await page.locator('#ai-command').scrollIntoViewIfNeeded();
            await page.waitForTimeout(80);
            const metrics = await page.evaluate(() => ({
                documentWidth: document.documentElement.scrollWidth,
                viewportWidth: document.documentElement.clientWidth,
                commandClientHeight: document.getElementById('ai-command').clientHeight,
                commandScrollHeight: document.getElementById('ai-command').scrollHeight,
                outcomeBottom: document.getElementById('ai-outcome').getBoundingClientRect().bottom,
                commandBottom: document.getElementById('ai-command').getBoundingClientRect().bottom,
                phase: window.CivilinaAIJourney.getState().phase
            }));
            if (metrics.documentWidth > metrics.viewportWidth) throw new Error(`${width}/${state.name}: overflow horizontal`);
            if (metrics.commandScrollHeight > metrics.commandClientHeight + 1 || metrics.outcomeBottom > metrics.commandBottom + 1) throw new Error(`${width}/${state.name}: contenu vertical masqué`);
            if (consoleErrors.length || pageErrors.length) throw new Error(`${width}/${state.name}: erreur console/page`);
            const file = path.join(captures, `freeze-${width}-${state.name}.png`);
            await page.locator('#ai-command').screenshot({ path: file });
            report.push({ width, state: state.name, file, ...metrics, consoleErrors: 0, pageErrors: 0 });
            await context.close();
        }
    }
    fs.writeFileSync(path.join(here, 'freeze-responsive-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ captures: report.length, widths, states: states.length, overflowFailures: 0, consoleErrors: 0 }, null, 2));
} finally {
    await browser.close();
}
