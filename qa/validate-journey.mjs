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

const scenarios = [
    { key: 'projects', start: 'Piloter mes projets', answers: ['Consolider coûts et délais', 'Plusieurs outils', 'Plus de 10 projets', 'Construction / EPC'], primary: 'projects', recommendation: 'cost-control', edit: true },
    { key: 'organisation', start: 'Structurer mon organisation', answers: ['Responsabilités peu claires', 'Clarifier les rôles', 'E-mails', '20 à 99 personnes'], primary: 'organisation', recommendation: 'roles-responsibilities' },
    { key: 'managementSystems', start: 'Améliorer mon système ISO', answers: ['Documents et versions', 'Déployé mais fragmenté', 'Système intégré', 'Plusieurs outils'], primary: 'managementSystems', recommendation: 'document-control' },
    { key: 'digitalisation', start: 'Digitaliser mes processus', answers: ['Validations internes', 'Manque de traçabilité', 'Papier et e-mail', 'Créer un workflow'], primary: 'digitalisation', recommendation: 'digital-workflows' },
];

async function newPage(viewport = { width: 1440, height: 1000 }) {
    const context = await browser.newContext({ viewport, reducedMotion: 'reduce' });
    const page = await context.newPage();
    const consoleErrors = [];
    const pageErrors = [];
    page.on('console', message => { if (message.type() === 'error') consoleErrors.push(message.text()); });
    page.on('pageerror', error => pageErrors.push(error.message));
    await page.route('https://fonts.googleapis.com/**', route => route.fulfill({ status: 200, contentType: 'text/css', body: '' }));
    await page.route('https://fonts.gstatic.com/**', route => route.fulfill({ status: 204, body: '' }));
    await page.goto(origin, { waitUntil: 'networkidle' });
    return { context, page, consoleErrors, pageErrors };
}

async function runScenario(definition) {
    const { context, page, consoleErrors, pageErrors } = await newPage();
    await page.locator('#ai-suggestions button', { hasText: definition.start }).click();
    for (let index = 0; index < definition.answers.length; index += 1) {
        assert((await page.evaluate(() => window.CivilinaAIJourney.getState())).phase === 'conversation', `${definition.key}: question ${index + 1} absente`);
        await page.locator('#ai-suggestions button', { hasText: definition.answers[index] }).click();
        if (definition.key === 'projects' && index === 0) {
            await page.reload({ waitUntil: 'networkidle' });
            const restored = await page.evaluate(() => window.CivilinaAIJourney.getState());
            assert(restored.phase === 'conversation' && restored.questionCount === 1, 'Reprise sessionStorage incorrecte');
        }
    }
    let confirmation = await page.evaluate(() => window.CivilinaAIJourney.getState());
    assert(confirmation.phase === 'confirmation', `${definition.key}: confirmation absente`);
    assert(confirmation.primaryDomain === definition.primary, `${definition.key}: domaine principal incorrect`);
    assert(confirmation.reasoning.recommendations.immediate.some(item => item.id === definition.recommendation), `${definition.key}: recommandation attendue absente`);
    assert(confirmation.reasoning.recommendations.immediate.length <= 3 && confirmation.reasoning.recommendations.explore.length <= 2, `${definition.key}: limites dépassées`);

    if (definition.edit) {
        await page.locator('[data-action="show-edit"]').click();
        await page.locator('[data-action="edit-answer"][data-field="difficulty"]').click();
        await page.locator('#ai-suggestions button', { hasText: 'Fiabiliser le reporting direction' }).click();
        confirmation = await page.evaluate(() => window.CivilinaAIJourney.getState());
        assert(confirmation.phase === 'confirmation', 'Retour confirmation après modification absent');
        assert(!confirmation.reasoning.recommendations.immediate.concat(confirmation.reasoning.recommendations.explore).some(item => item.id === 'cost-control'), 'Ancienne recommandation conservée après modification');
    }

    await page.locator('[data-action="confirm-context"]').click();
    assert((await page.evaluate(() => window.CivilinaAIJourney.getState())).phase === 'reading', `${definition.key}: lecture absente`);
    await page.locator('[data-action="show-recommendation"]').click();
    await page.locator('[data-action="show-account"]').click();
    assert(await page.getByText('Connexion Microsoft et Google prévue.').isVisible(), `${definition.key}: roadmap compte absente`);
    assert(await page.getByRole('button', { name: 'Continuer avec l’e-mail' }).isDisabled(), `${definition.key}: compte fictivement actif`);
    assert(consoleErrors.length === 0, `${definition.key}: ${consoleErrors.join(' | ')}`);
    assert(pageErrors.length === 0, `${definition.key}: ${pageErrors.join(' | ')}`);
    const summary = {
        scenario: definition.key,
        primaryDomain: confirmation.primaryDomain,
        relatedDomains: confirmation.relatedDomains,
        immediate: confirmation.reasoning.recommendations.immediate.map(item => item.id),
        explore: confirmation.reasoning.recommendations.explore.map(item => item.id),
        editRecalculated: Boolean(definition.edit),
        consoleErrors: 0,
        pageErrors: 0,
    };
    await context.close();
    return summary;
}

async function testAmbiguity() {
    const { context, page } = await newPage({ width: 430, height: 900 });
    await page.locator('#ai-need').fill('Nous avons du mal à piloter.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    const state = await page.evaluate(() => window.CivilinaAIJourney.getState());
    assert(state.phase === 'conversation' && state.activeQuestion.id === 'domainClarification', 'Clarification ambiguë absente');
    assert(await page.getByRole('button', { name: 'Mes projets', exact: true }).isVisible(), 'Options de clarification absentes');
    await context.close();
    return { clarification: true };
}

async function testMultiDomainAndExpert() {
    const { context, page } = await newPage();
    await page.locator('#ai-need').fill('Nous préparons ISO 9001, les responsabilités sont floues et nous digitalisons les validations.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    for (const answer of ['Rôles et responsabilités', 'Déployé mais fragmenté', 'Système intégré', 'Plusieurs outils']) {
        await page.locator('#ai-suggestions button', { hasText: answer }).click();
    }
    const state = await page.evaluate(() => window.CivilinaAIJourney.getState());
    assert(state.primaryDomain === 'managementSystems', 'Management Systems doit rester principal');
    assert(state.relatedDomains.includes('organisation') && state.relatedDomains.includes('digitalisation'), 'Domaines associés perdus');
    assert(state.expertSuggested, 'Expert transverse attendu');
    await page.locator('[data-action="confirm-context"]').click();
    await page.locator('[data-action="show-recommendation"]').click();
    await page.locator('[data-action="show-account"]').click();
    await page.getByRole('button', { name: 'Compléter avec un expert' }).click();
    const href = await page.locator('#ai-outcome').getByRole('link', { name: 'Parler à un expert' }).getAttribute('href');
    assert(href.startsWith('mailto:') && !href.includes('responsabilit%C3%A9s%20sont%20floues'), 'Handoff contient du texte libre');
    await context.close();
    return { primary: state.primaryDomain, related: state.relatedDomains, expert: true, safeMailto: true };
}

async function testPeopleGuardrail() {
    const { context, page, consoleErrors, pageErrors } = await newPage({ width: 430, height: 900 });
    await page.locator('#ai-need').fill('J’ai les MBTI de mes salariés. Qui dois-je mettre chef de chantier ?');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    for (const answer of ['Éclairer une décision de recrutement ou d’affectation', 'Éléments encore incomplets', 'Des candidats']) {
        await page.locator('#ai-suggestions button', { hasText: answer }).click();
    }
    const state = await page.evaluate(() => window.CivilinaAIJourney.getState());
    assert(state.primaryDomain === 'people' && state.peopleGuardrail, 'Guardrail People absent');
    assert(state.reasoning.recommendations.immediate.length === 0 && state.reasoning.recommendations.explore.length === 0, 'Recommandation RH automatique');
    assert(await page.getByRole('button', { name: 'Structurer les critères de décision' }).isVisible(), 'Action de structuration People absente');
    assert(consoleErrors.length === 0 && pageErrors.length === 0, 'Erreur navigateur People');
    await context.close();
    return { primary: 'people', guardrail: true, recommendations: 0 };
}

async function testUnknownDelay() {
    const { context, page, consoleErrors, pageErrors } = await newPage({ width: 390, height: 844 });
    await page.locator('#ai-need').fill('Le projet Atlas est en retard.');
    await page.locator('#ai-form').evaluate(form => form.requestSubmit());
    const state = await page.evaluate(() => window.CivilinaAIJourney.getState());
    assert(state.activeQuestion.id === 'delayCause', 'Clarification causale du retard absente');
    assert(state.reasoning.uncertainties.some(item => item.id === 'project-delay-cause'), 'Incertitude retard absente du state');
    assert(!state.reasoning.recommendations.immediate.some(item => item.id === 'planning-wbs'), 'Planning affirmé avant clarification');
    assert(await page.getByRole('button', { name: 'Je ne sais pas encore' }).isVisible(), 'Option inconnue absente');
    assert(consoleErrors.length === 0 && pageErrors.length === 0, 'Erreur navigateur retard inconnu');
    await context.close();
    return { uncertainty: 'project-delay-cause', nextQuestion: 'delayCause', planningImmediate: false };
}

try {
    const scenarioResults = [];
    for (const scenario of scenarios) scenarioResults.push(await runScenario(scenario));
    const ambiguity = await testAmbiguity();
    const multiDomain = await testMultiDomainAndExpert();
    const peopleGuardrail = await testPeopleGuardrail();
    const unknownDelay = await testUnknownDelay();
    const report = { origin, scenarioResults, ambiguity, multiDomain, peopleGuardrail, unknownDelay, passed: true };
    fs.writeFileSync(path.join(here, 'journey-validation.json'), JSON.stringify(report, null, 2));
    console.log(JSON.stringify({ scenarios: scenarioResults.length, ambiguity: true, multiDomain: true, peopleGuardrail: true, unknownDelay: true, passed: true }, null, 2));
} finally {
    await browser.close();
}
