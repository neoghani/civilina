import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const here = path.dirname(fileURLToPath(import.meta.url));
const sentinel = 'CIVILINA_SECRET_TEST_92841';
const origin = process.env.CIVILINA_BASE_URL || 'http://127.0.0.1:8000';
const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
const context = await browser.newContext({ viewport: { width: 1024, height: 900 }, reducedMotion: 'reduce' });
const page = await context.newPage();
const consoleMessages = [];
page.on('console', message => consoleMessages.push(message.text()));
await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
await page.route('https://fonts.gstatic.com/**', route => route.fulfill({ status: 204, body: '' }));

function assert(condition, message) { if (!condition) throw new Error(message); }

try {
    await page.goto(origin, { waitUntil: 'networkidle' });
    const input = `Nous préparons ISO 9001, les responsabilités sont floues, nous digitalisons les validations et je veux transmettre notre contrat confidentiel ${sentinel}`;
    await page.locator('#ai-need').fill(input);
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    for (const answer of ['Rôles et responsabilités', 'Déployé mais fragmenté', 'Système intégré', 'Plusieurs outils']) {
        await page.locator('#ai-suggestions button', { hasText: answer }).click();
    }
    await page.locator('[data-action="confirm-context"]').click();
    await page.locator('[data-action="show-recommendation"]').click();
    await page.locator('[data-action="show-account"]').click();
    await page.getByRole('button', { name: 'Informations sensibles' }).click();

    const channels = await page.evaluate(secret => ({
        sessionStorage: Object.values(sessionStorage).join('\n'),
        localStorage: Object.values(localStorage).join('\n'),
        url: location.href,
        mailto: Array.from(document.querySelectorAll('a[href^="mailto:"]')).map(item => item.getAttribute('href')).join('\n'),
        datasets: Array.from(document.querySelectorAll('*')).flatMap(element => Object.values(element.dataset)).join('\n'),
        hasUpload: document.querySelectorAll('input[type="file"]').length,
        sessionPlaceholder: Object.values(sessionStorage).join('\n').includes('[Information sensible non conservée]'),
        secret,
    }), sentinel);
    const checks = {
        sessionStorage: !channels.sessionStorage.includes(sentinel),
        localStorage: !channels.localStorage.includes(sentinel),
        url: !channels.url.includes(sentinel),
        console: !consoleMessages.join('\n').includes(sentinel),
        mailto: !channels.mailto.includes(sentinel),
        datasetDOM: !channels.datasets.includes(sentinel),
        noUpload: channels.hasUpload === 0,
        sensitivePlaceholder: channels.sessionPlaceholder,
    };
    Object.entries(checks).forEach(([name, passed]) => assert(passed, `Échec fuite sentinelle: ${name}`));
    const report = { sentinel, checks, consoleMessageCount: consoleMessages.length, passed: true };
    fs.writeFileSync(path.join(here, 'storage-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
} finally {
    await context.close();
    await browser.close();
}
