import fs from 'node:fs';
import path from 'node:path';
import { createRequire } from 'node:module';
import { fileURLToPath } from 'node:url';

const require = createRequire(import.meta.url);
const { chromium } = require('playwright');
const here = path.dirname(fileURLToPath(import.meta.url));
const origin = process.env.CIVILINA_BASE_URL || 'http://127.0.0.1:8000';

const browser = await chromium.launch({ executablePath: process.env.CHROME_PATH || 'C:/Program Files/Google/Chrome/Application/chrome.exe', headless: true });
function assert(condition, message) { if (!condition) throw new Error(message); }

async function answerWhileConversing(page, answers) {
    for (const answer of answers) {
        const phase = await page.evaluate(() => window.CivilinaAIJourney.getState().phase);
        if (phase !== 'conversation') break;
        await page.locator('#ai-suggestions button', { hasText: answer }).click();
    }
}

try {
    const context = await browser.newContext({ viewport: { width: 390, height: 844 }, reducedMotion: 'reduce' });
    const page = await context.newPage();
    await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
    await page.route('https://fonts.gstatic.com/**', route => route.fulfill({ status: 204, body: '' }));
    await page.goto(origin, { waitUntil: 'networkidle' });

    const initial = await page.evaluate(() => ({
        h1: document.querySelectorAll('h1').length,
        progressbar: document.querySelectorAll('[role="progressbar"]').length,
        liveRegions: document.querySelectorAll('[aria-live]').length,
        inputLabel: document.querySelector('label[for="ai-need"]')?.textContent.trim(),
        reducedMotion: matchMedia('(prefers-reduced-motion: reduce)').matches,
        startButtons: document.querySelectorAll('#ai-suggestions button').length,
    }));
    assert(initial.h1 === 1, 'Un seul H1 attendu');
    assert(initial.progressbar === 1, 'Progressbar absente');
    assert(initial.liveRegions >= 2, 'Régions aria-live insuffisantes');
    assert(Boolean(initial.inputLabel), 'Label du champ absent');
    assert(initial.reducedMotion, 'Reduced motion non actif');
    assert(initial.startButtons === 4, 'Quatre suggestions initiales attendues');

    await page.locator('.menu-toggle').click();
    assert(await page.locator('.menu-toggle').getAttribute('aria-expanded') === 'true', 'aria-expanded non mis à jour');
    await page.keyboard.press('Escape');
    assert(await page.locator('.menu-toggle').getAttribute('aria-expanded') === 'false', 'Escape ne ferme pas le menu');

    await page.locator('#ai-need').fill('Nous avons du mal à piloter.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    assert(await page.locator('#ai-conversation').getByText('Quel sujet souhaitez-vous mieux maîtriser en premier ?').isVisible(), 'Question de clarification non annoncée');
    await page.getByRole('button', { name: 'Mon organisation', exact: true }).click();
    await answerWhileConversing(page, ['Responsabilités peu claires', 'Clarifier les rôles', 'E-mails', '20 à 99 personnes']);
    assert(await page.getByRole('button', { name: 'Modifier', exact: true }).isVisible(), 'Bouton Modifier inaccessible');
    await page.getByRole('button', { name: 'Modifier', exact: true }).focus();
    assert(await page.evaluate(() => document.activeElement?.textContent.trim() === 'Modifier'), 'Focus Modifier absent');

    await page.getByRole('button', { name: 'Recommencer' }).click();
    await page.locator('#ai-need').fill('Nos processus sont bien structurés mais les responsabilités de validation restent peu claires.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    await answerWhileConversing(page, ['Responsabilités peu claires', 'Clarifier les rôles', 'Workflow formalisé', '20 à 99 personnes']);
    assert(await page.locator('#ai-outcome').getByText('Un point mérite d’être clarifié', { exact: false }).isVisible(), 'Contradiction non annoncée');

    await page.getByRole('button', { name: 'Recommencer' }).click();
    await page.locator('#ai-need').fill('Le projet Atlas est en retard.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    assert(await page.locator('#ai-conversation').getByText('Qu’est-ce qui provoque actuellement le plus de retard ?').isVisible(), 'Incertitude causale non annoncée');

    await page.getByRole('button', { name: 'Recommencer' }).click();
    await page.locator('#ai-need').fill('Nous préparons ISO 9001 et je veux transmettre notre contrat confidentiel.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    await answerWhileConversing(page, ['Rôles et responsabilités', 'Démarrage', 'ISO 9001', 'Excel']);
    assert(await page.locator('#ai-outcome').getByText('Information sensible détectée').isVisible(), 'Alerte confidentialité non annoncée');

    await page.getByRole('button', { name: 'Recommencer' }).click();
    await page.locator('#ai-need').fill('J’ai les MBTI de mes salariés. Qui dois-je mettre chef de chantier ?');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    await answerWhileConversing(page, ['Éclairer une décision de recrutement ou d’affectation', 'Éléments encore incomplets', 'Des candidats']);
    assert(await page.locator('#ai-outcome').getByText('Décision humaine à encadrer').isVisible(), 'People Guardrail non annoncé');
    assert(await page.getByRole('button', { name: 'Structurer les critères de décision' }).isVisible(), 'Action People non accessible');

    const report = {
        ...initial,
        menuEscape: true,
        clarificationAriaLive: true,
        modifyKeyboardFocus: true,
        contradictionAnnounced: true,
        delayUncertaintyAnnounced: true,
        privacyAlertAnnounced: true,
        peopleGuardrailAnnounced: true,
        passed: true,
    };
    fs.writeFileSync(path.join(here, 'accessibility-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify(report, null, 2));
    await context.close();
} finally {
    await browser.close();
}
