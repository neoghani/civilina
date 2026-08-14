(function () {
    'use strict';

    const config = window.CivilinaAIScenarios;
    const domains = window.CivilinaAIDomains;
    const core = window.CivilinaAIReasoningRuntime || window.CivilinaAIReasoning;
    const privacy = window.CivilinaAIPrivacy;
    const storageKey = 'civilinaAiJourneyV3';
    const validPhases = ['start', 'conversation', 'confirmation', 'edit', 'reading', 'recommendation', 'account', 'followup'];

    document.documentElement.dataset.reasoningCoreVersion = core.REASONING_CORE_VERSION || 'unknown';

    const elements = {
        navigation: document.getElementById('main-navigation'),
        menuToggle: document.querySelector('.menu-toggle'),
        commandCenter: document.getElementById('ai-command'),
        progress: document.getElementById('ai-progress'),
        phase: document.getElementById('ai-phase'),
        conversation: document.getElementById('ai-conversation'),
        suggestions: document.getElementById('ai-suggestions'),
        form: document.getElementById('ai-form'),
        input: document.getElementById('ai-need'),
        submit: document.getElementById('ai-submit'),
        note: document.getElementById('form-note'),
        reset: document.getElementById('ai-reset'),
        outcome: document.getElementById('ai-outcome')
    };

    let state = createInitialState();

    function createInitialState() {
        return {
            version: 3,
            scenario: null,
            entryIntent: null,
            primaryDomain: null,
            relatedDomains: [],
            capabilities: [],
            phase: 'start',
            step: 0,
            questionCount: 0,
            answers: {},
            answerLabels: {},
            derivedTextFacts: [],
            initialText: '',
            messages: [],
            activeQuestion: null,
            editingField: null,
            reasoning: emptyReasoning(),
            sensitiveIntent: false,
            peopleGuardrail: false,
            expertSuggested: false
        };
    }

    function emptyReasoning() {
        return {
            entryIntent: null,
            primaryDomain: null,
            relatedDomains: [],
            capabilities: [],
            domainSignals: {},
            capabilitySignals: {},
            confidence: { domains: {}, capabilities: {} },
            facts: [],
            evidence: [],
            uncertainties: [],
            contradictions: [],
            priorities: [],
            recommendations: { immediate: [], explore: [] },
            sensitiveIntent: false,
            peopleGuardrail: false,
            expertSuggested: false
        };
    }

    function normalize(value) {
        return String(value || '').trim();
    }

    function createElement(tag, className, text) {
        const element = document.createElement(tag);
        if (className) element.className = className;
        if (text !== undefined && text !== null) element.textContent = text;
        return element;
    }

    function closeMenu() {
        elements.navigation.classList.remove('is-open');
        elements.menuToggle.setAttribute('aria-expanded', 'false');
        elements.menuToggle.setAttribute('aria-label', 'Ouvrir le menu');
    }

    function currentScenario() {
        return state.entryIntent && config.scenarios[state.entryIntent] ? config.scenarios[state.entryIntent] : null;
    }

    function addMessage(role, text) {
        state.messages.push({ role: role, text: text });
    }

    function recalculate() {
        state.reasoning = core.evaluate({
            entryIntent: state.entryIntent,
            answers: state.answers,
            freeText: state.initialText,
            seedFacts: state.derivedTextFacts
        });
        state.entryIntent = state.reasoning.entryIntent;
        state.scenario = state.entryIntent;
        state.primaryDomain = state.reasoning.primaryDomain;
        state.relatedDomains = state.reasoning.relatedDomains;
        state.capabilities = state.reasoning.capabilities;
        state.sensitiveIntent = state.sensitiveIntent || state.reasoning.sensitiveIntent;
        state.peopleGuardrail = state.reasoning.peopleGuardrail;
        state.expertSuggested = state.reasoning.expertSuggested;
    }

    function persist() {
        sessionStorage.setItem(storageKey, JSON.stringify(privacy.buildSafeStorageState(state)));
    }

    function restore() {
        try {
            const raw = sessionStorage.getItem(storageKey);
            if (!raw) return;
            const parsed = JSON.parse(raw);
            if (!parsed || parsed.version !== 3 || !validPhases.includes(parsed.phase)) return;
            state = Object.assign(createInitialState(), parsed, {
                initialText: '',
                activeQuestion: null,
                editingField: null,
                messages: Array.isArray(parsed.messages) ? parsed.messages : []
            });
            recalculate();
            if (state.phase === 'conversation') prepareNextQuestion(false);
        } catch (error) {
            sessionStorage.removeItem(storageKey);
            state = createInitialState();
        }
    }

    function renderMessages() {
        elements.conversation.innerHTML = '';
        const visibleMessages = ['reading', 'confirmation'].includes(state.phase) ? state.messages.slice(-4) : state.messages.slice(-8);
        visibleMessages.forEach(function (message, index) {
            const article = createElement('article', 'ai-message ai-message-' + message.role);
            if (index === visibleMessages.length - 1) article.classList.add('is-new');
            article.append(
                createElement('span', 'ai-message-label', message.role === 'assistant' ? 'Civilina AI' : 'Vous'),
                createElement('p', null, message.text)
            );
            elements.conversation.appendChild(article);
        });
        elements.conversation.scrollTop = elements.conversation.scrollHeight;
    }

    function renderSuggestions() {
        elements.suggestions.innerHTML = '';
        let options = [];
        if (state.phase === 'start') {
            options = config.initialOptions.map(function (option) {
                return { label: option.label, value: option.value, scenario: option.scenario };
            });
        } else if (state.phase === 'conversation' && state.activeQuestion) {
            options = state.activeQuestion.suggestions.map(function (item) {
                return { label: item[0], value: item[0], answer: item[1] };
            });
        }
        options.forEach(function (option) {
            const button = createElement('button', 'suggestion', option.label);
            button.type = 'button';
            button.dataset.value = option.value;
            if (option.scenario) button.dataset.scenario = option.scenario;
            if (option.answer) button.dataset.answer = option.answer;
            elements.suggestions.appendChild(button);
        });
        elements.suggestions.hidden = options.length === 0;
    }

    function progressValue() {
        if (state.phase === 'start') return 10;
        if (state.phase === 'conversation') return Math.min(62, 20 + state.questionCount * 10);
        if (['confirmation', 'edit'].includes(state.phase)) return 64;
        if (state.phase === 'reading') return 72;
        if (state.phase === 'recommendation') return 86;
        return 100;
    }

    function renderProgress() {
        const labels = {
            start: 'Comprendre', conversation: 'Comprendre', confirmation: 'Analyser', edit: 'Analyser',
            reading: 'Analyser', recommendation: 'Recommander', account: 'Recommander', followup: 'Recommander'
        };
        const value = progressValue();
        elements.phase.textContent = labels[state.phase];
        elements.progress.style.setProperty('--ai-progress', value + '%');
        elements.progress.setAttribute('aria-valuenow', String(value));
        elements.progress.querySelectorAll('span').forEach(function (item) {
            item.classList.toggle('is-active', value >= Number(item.dataset.progress));
        });
    }

    function renderForm() {
        const interactive = state.phase === 'start' || state.phase === 'conversation';
        elements.form.hidden = !interactive;
        elements.input.placeholder = state.phase === 'conversation' ? 'Votre réponse…' : 'Décrivez votre besoin…';
        elements.submit.textContent = state.phase === 'conversation' ? 'Répondre' : 'Commencer';
        elements.note.textContent = interactive
            ? 'Vos réponses restent dans ce navigateur et ne sont envoyées à aucun serveur Civilina.'
            : 'Diagnostic conservé dans cette session uniquement.';
    }

    function appendDomainSummary(section) {
        const summary = createElement('div', 'domain-summary');
        const primary = createElement('div', 'domain-primary');
        primary.append(
            createElement('span', 'result-label', 'Besoin principal'),
            createElement('strong', null, state.primaryDomain ? domains.getDomainLabel(state.primaryDomain) : 'À clarifier')
        );
        summary.appendChild(primary);
        if (state.relatedDomains.length) {
            const related = createElement('div', 'domain-related');
            related.appendChild(createElement('span', 'result-label', 'Sujets associés'));
            const tags = createElement('div', 'domain-tags');
            state.relatedDomains.forEach(function (domainId) {
                tags.appendChild(createElement('span', null, domains.getDomainLabel(domainId)));
            });
            related.appendChild(tags);
            summary.appendChild(related);
        }
        if (state.capabilities.length) {
            const capabilityBlock = createElement('div', 'domain-capabilities');
            capabilityBlock.appendChild(createElement('span', 'result-label', 'Capacités mobilisées'));
            const capabilityTags = createElement('div', 'capability-tags');
            state.capabilities.forEach(function (capabilityId) {
                capabilityTags.appendChild(createElement('span', null, domains.getCapabilityLabel(capabilityId)));
            });
            capabilityBlock.appendChild(capabilityTags);
            summary.appendChild(capabilityBlock);
        }
        section.appendChild(summary);
    }

    function appendReasoningAlerts(section) {
        state.reasoning.contradictions.forEach(function (item) {
            const alert = createElement('div', 'reasoning-alert reasoning-alert-neutral');
            alert.append(createElement('strong', null, 'Point à clarifier'), createElement('p', null, item.message));
            section.appendChild(alert);
        });
        if (state.sensitiveIntent) {
            const alert = createElement('div', 'reasoning-alert');
            alert.append(
                createElement('strong', null, 'Information sensible détectée'),
                createElement('p', null, 'Aucun document n’est transmis ici. Un espace professionnel confidentiel pourra être préparé avant tout échange sensible.')
            );
            section.appendChild(alert);
        }
        state.reasoning.uncertainties.filter(function (item) {
            return item.blocking && item.id !== 'people-evidence-incomplete';
        }).forEach(function (item) {
            const alert = createElement('div', 'reasoning-alert reasoning-alert-neutral');
            alert.append(createElement('strong', null, 'Information à préciser'), createElement('p', null, item.message));
            section.appendChild(alert);
        });
        if (state.peopleGuardrail) {
            const alert = createElement('div', 'reasoning-alert people-note');
            const action = createElement('button', 'text-action', 'Structurer les critères de décision');
            action.type = 'button';
            action.dataset.action = 'structure-people-criteria';
            alert.append(
                createElement('strong', null, 'Décision humaine à encadrer'),
                createElement('p', null, 'Civilina peut aider à structurer la décision, mais ne peut pas désigner une personne à partir d’un seul profil ou indicateur. La décision doit considérer les compétences, l’expérience, la performance, les responsabilités, la disponibilité et les exigences du poste.'),
                action
            );
            section.appendChild(alert);
        }
    }

    function renderConfirmation() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'confirmation';
        section.append(createElement('span', 'journey-kicker', 'Confirmation du contexte'), createElement('h3', null, 'Civilina a compris :'));
        const context = createElement('dl', 'context-list');
        Object.keys(state.answerLabels).slice(0, 5).forEach(function (field) {
            const row = createElement('div', 'context-row');
            const question = findQuestion(field);
            row.append(createElement('dt', null, questionLabel(field, question)), createElement('dd', null, state.answerLabels[field]));
            context.appendChild(row);
        });
        section.appendChild(context);
        appendReasoningAlerts(section);
        const actions = createElement('div', 'journey-actions');
        const confirm = createElement('button', 'btn btn-navy journey-primary', 'C’est correct');
        confirm.type = 'button';
        confirm.dataset.action = 'confirm-context';
        const edit = createElement('button', 'btn btn-outline journey-primary', 'Modifier');
        edit.type = 'button';
        edit.dataset.action = 'show-edit';
        actions.append(confirm, edit);
        section.appendChild(actions);
        return section;
    }

    function renderEdit() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'edit';
        section.append(createElement('span', 'journey-kicker', 'Modifier le contexte'), createElement('h3', null, 'Quelle réponse souhaitez-vous corriger ?'));
        const list = createElement('div', 'edit-answer-list');
        Object.keys(state.answerLabels).forEach(function (field) {
            const question = findQuestion(field);
            const button = createElement('button', 'edit-answer', questionLabel(field, question) + ' : ' + state.answerLabels[field]);
            button.type = 'button';
            button.dataset.action = 'edit-answer';
            button.dataset.field = field;
            list.appendChild(button);
        });
        section.appendChild(list);
        const back = createElement('button', 'text-action', 'Retour à la confirmation');
        back.type = 'button';
        back.dataset.action = 'show-confirmation';
        section.appendChild(back);
        return section;
    }

    function renderReading() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'reading';
        section.append(createElement('span', 'journey-kicker', 'Première lecture Civilina'), createElement('h3', null, 'Votre situation en quelques points'));
        appendDomainSummary(section);
        appendReasoningAlerts(section);
        if (state.reasoning.priorities.length) {
            section.appendChild(createElement('h3', 'journey-subtitle', 'Priorités identifiées'));
            const list = createElement('ol', 'reasoned-list');
            state.reasoning.priorities.slice(0, 3).forEach(function (priority) {
                const item = createElement('li');
                item.append(createElement('strong', null, priority.title), createElement('p', null, priority.reason));
                list.appendChild(item);
            });
            section.appendChild(list);
        } else if (state.reasoning.uncertainties.length) {
            section.appendChild(createElement('p', 'uncertainty-note', state.reasoning.uncertainties[0].message));
        }
        const action = createElement('button', 'btn btn-navy journey-primary', 'Voir mon environnement recommandé');
        action.type = 'button';
        action.dataset.action = 'show-recommendation';
        section.appendChild(action);
        return section;
    }

    function appendRecommendations(section, title, recommendations) {
        if (!recommendations.length) return;
        section.appendChild(createElement('h3', 'journey-subtitle', title));
        const list = createElement('div', 'evidence-list');
        recommendations.forEach(function (recommendation) {
            const item = createElement('article', 'evidence-item');
            item.append(createElement('strong', null, recommendation.label), createElement('p', null, recommendation.evidence[0].reason));
            list.appendChild(item);
        });
        section.appendChild(list);
    }

    function renderRecommendation() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'recommendation';
        section.append(createElement('span', 'journey-kicker', 'Votre environnement Civilina recommandé'), createElement('h3', null, 'Des pistes ciblées, fondées sur vos réponses'));
        appendRecommendations(section, 'Priorités immédiates', state.reasoning.recommendations.immediate);
        appendRecommendations(section, 'À explorer ensuite', state.reasoning.recommendations.explore);
        if (!state.reasoning.recommendations.immediate.length && !state.reasoning.recommendations.explore.length) {
            section.appendChild(createElement('p', 'uncertainty-note', state.peopleGuardrail
                ? 'Aucune personne ni affectation n’est recommandée sur la base d’un profil ou d’un style de travail.'
                : 'Je n’ai pas encore assez d’éléments pour formuler une recommandation utile.'));
        }
        appendReasoningAlerts(section);
        const action = createElement('button', 'btn btn-navy journey-primary', 'Poursuivre avec Civilina');
        action.type = 'button';
        action.dataset.action = 'show-account';
        section.appendChild(action);
        return section;
    }

    function renderAccount() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'account';
        section.append(
            createElement('span', 'journey-kicker', 'Poursuivre le diagnostic'),
            createElement('h3', null, 'Sauvegardez votre diagnostic pour poursuivre avec Civilina.'),
            createElement('p', 'account-roadmap', 'Connexion Microsoft et Google prévue. Le compte e-mail sera relié à Civilina Core.')
        );
        const actions = createElement('div', 'account-choice-list account-choice-compact');
        const email = createElement('button', 'btn btn-navy', 'Continuer avec l’e-mail');
        email.type = 'button';
        email.disabled = true;
        actions.appendChild(email);
        section.appendChild(actions);
        if (state.sensitiveIntent || state.expertSuggested) {
            const followup = createElement('button', 'text-action', state.sensitiveIntent ? 'Informations sensibles' : 'Compléter avec un expert');
            followup.type = 'button';
            followup.dataset.action = 'show-followup';
            section.appendChild(followup);
        }
        return section;
    }

    function buildHandoffSummary() {
        const immediate = state.reasoning.recommendations.immediate.map(function (item) { return item.label; }).join(', ');
        return [
            'Besoin principal : ' + (state.primaryDomain ? domains.getDomainLabel(state.primaryDomain) : 'à clarifier'),
            'Sujets associés : ' + (state.relatedDomains.map(domains.getDomainLabel).join(', ') || 'aucun sujet associé confirmé'),
            'Capacités mobilisées : ' + (state.capabilities.map(domains.getCapabilityLabel).join(', ') || 'à préciser'),
            'Priorités dérivées : ' + (immediate || 'à approfondir'),
            'Résumé : première lecture Civilina fondée sur des catégories, sans reprise du texte libre.'
        ].join('\n');
    }

    function renderFollowup() {
        const section = createElement('section', 'journey-panel');
        section.dataset.view = 'followup';
        if (state.sensitiveIntent) {
            section.append(
                createElement('span', 'journey-kicker', 'Informations sensibles'),
                createElement('h3', null, 'Un espace protégé avant tout document interne.'),
                createElement('p', 'journey-context', 'Civilina pourra ouvrir un espace professionnel confidentiel et mettre en place un NDA avant une transmission. Aucun document n’est transmis dans cette démonstration.')
            );
        }
        if (state.expertSuggested) {
            section.append(
                createElement('span', 'journey-kicker journey-kicker-spaced', 'Civilina AI + expertise humaine'),
                createElement('h3', null, 'Un regard métier peut compléter cette première lecture.'),
                createElement('p', 'journey-context', 'Avec votre accord, un expert Civilina peut reprendre uniquement le contexte synthétique que vous choisissez de transmettre.'),
                createElement('pre', 'handoff-summary', buildHandoffSummary())
            );
            const expert = createElement('a', 'btn btn-navy journey-primary', 'Parler à un expert');
            expert.href = 'mailto:abdelghani.bouhouche@civilina.com?subject=' + encodeURIComponent('Diagnostic Civilina') + '&body=' + encodeURIComponent(buildHandoffSummary());
            section.appendChild(expert);
        }
        const back = createElement('button', 'text-action', 'Retour à la sauvegarde');
        back.type = 'button';
        back.dataset.action = 'show-account';
        section.appendChild(back);
        return section;
    }

    function renderOutcome() {
        elements.outcome.innerHTML = '';
        elements.outcome.hidden = ['start', 'conversation'].includes(state.phase);
        if (state.phase === 'confirmation') elements.outcome.appendChild(renderConfirmation());
        if (state.phase === 'edit') elements.outcome.appendChild(renderEdit());
        if (state.phase === 'reading') elements.outcome.appendChild(renderReading());
        if (state.phase === 'recommendation') elements.outcome.appendChild(renderRecommendation());
        if (state.phase === 'account') elements.outcome.appendChild(renderAccount());
        if (state.phase === 'followup') elements.outcome.appendChild(renderFollowup());
    }

    function render() {
        renderProgress();
        renderMessages();
        renderSuggestions();
        renderForm();
        renderOutcome();
        elements.reset.hidden = state.phase === 'start' && state.messages.length <= 1;
        elements.commandCenter.dataset.phase = state.phase;
        persist();
    }

    function findQuestion(field) {
        const scenarioQuestion = currentScenario() && currentScenario().questions.find(function (question) { return question.id === field; });
        if (scenarioQuestion) return scenarioQuestion;
        const dynamic = {
            domainClarification: {
                id: 'domainClarification', prompt: 'Sujet principal', suggestions: [
                    ['Mes projets', 'projects'], ['Mon organisation', 'organisation'], ['Mon système ISO / qualité', 'managementSystems'], ['Mes processus digitaux', 'digitalisation'], ['Mes équipes', 'people']
                ]
            },
            delayCause: {
                id: 'delayCause', prompt: 'Cause principale des retards', suggestions: [
                    ['Planning / séquencement', 'planning'], ['Approvisionnement', 'procurement'], ['Ressources', 'resources'], ['Causes multiples', 'multiple'], ['Je ne sais pas encore', 'unknown']
                ]
            },
            subcontractorBlockage: {
                id: 'subcontractorBlockage', prompt: 'Nature du blocage sous-traitant', suggestions: [
                    ['Capacité / ressources', 'capacity'], ['Interface achats', 'procurement-interface'], ['Validation technique', 'technical-approval'], ['Qualité d’exécution', 'quality'], ['Je ne sais pas encore', 'unknown']
                ]
            },
            peopleCriteria: {
                id: 'peopleCriteria', prompt: 'Quels éléments objectifs sont déjà disponibles ?', suggestions: [
                    ['Compétences et expérience', 'skills-experience'], ['Performance et responsabilités', 'performance-responsibilities'], ['Exigences du poste', 'role-requirements'], ['Éléments encore incomplets', 'incomplete']
                ]
            }
        };
        return dynamic[field] || null;
    }

    function shortQuestionLabel(prompt) {
        const cleaned = String(prompt || '').replace(/[?]/g, '');
        return cleaned.length > 42 ? cleaned.slice(0, 39) + '…' : cleaned;
    }

    function questionLabel(field, question) {
        const labels = {
            activity: 'Activité', portfolio: 'Portefeuille', tracking: 'Outils de suivi', difficulty: 'Priorité',
            size: 'Taille', issue: 'Difficulté', decisions: 'Validations', objective: 'Objectif',
            reference: 'Référentiel', maturity: 'Maturité', tools: 'Outils', process: 'Processus',
            peopleObjective: 'Objectif humain', peopleScope: 'Périmètre humain', peopleCriteria: 'Critères de décision',
            domainClarification: 'Sujet principal', delayCause: 'Cause des retards', subcontractorBlockage: 'Blocage sous-traitant'
        };
        return labels[field] || (question ? shortQuestionLabel(question.prompt) : field);
    }

    function prepareNextQuestion(announce) {
        recalculate();
        const scenario = currentScenario();
        state.activeQuestion = core.nextBestQuestion({
            entryIntent: state.entryIntent,
            answers: state.answers,
            reasoning: state.reasoning,
            questions: scenario ? scenario.questions : []
        });
        if (state.activeQuestion && state.questionCount < 6) {
            state.phase = 'conversation';
            if (announce) addMessage('assistant', state.activeQuestion.prompt);
            return true;
        }
        state.activeQuestion = null;
        state.phase = 'confirmation';
        if (announce) addMessage('assistant', 'Merci. Vérifions rapidement le contexte avant la première lecture.');
        return false;
    }

    function startJourney(entryIntent, userText) {
        state = createInitialState();
        state.entryIntent = entryIntent || core.detectEntryIntent(userText);
        state.scenario = state.entryIntent;
        state.initialText = userText;
        state.derivedTextFacts = core.factsFromText(userText);
        state.sensitiveIntent = privacy.detectSensitiveIntent(userText);
        addMessage('user', userText);
        recalculate();
        prepareNextQuestion(false);
        if (state.activeQuestion) {
            const intro = currentScenario() ? currentScenario().intro + ' ' : '';
            addMessage('assistant', intro + state.activeQuestion.prompt);
        }
        render();
    }

    function mapFreeAnswer(question, value) {
        const normalized = normalize(value).toLocaleLowerCase('fr');
        const exact = question.suggestions.find(function (item) { return item[0].toLocaleLowerCase('fr') === normalized; });
        if (exact) return exact[1];
        const keyword = question.suggestions.find(function (item) {
            return item[0].toLocaleLowerCase('fr').split(/\s+/).some(function (word) { return word.length > 4 && normalized.includes(word); });
        });
        return keyword ? keyword[1] : 'other';
    }

    function answerCurrentQuestion(text, mappedValue) {
        const question = state.activeQuestion;
        if (!question) return;
        state.answers[question.id] = mappedValue || mapFreeAnswer(question, text);
        state.answerLabels[question.id] = mappedValue ? text : 'Réponse libre qualifiée';
        state.sensitiveIntent = state.sensitiveIntent || privacy.detectSensitiveIntent(text);
        addMessage('user', text);
        state.questionCount += 1;
        const wasEditing = state.editingField;
        state.editingField = null;
        state.activeQuestion = null;
        recalculate();
        if (wasEditing) {
            state.phase = 'confirmation';
            addMessage('assistant', 'La lecture a été recalculée à partir de votre correction.');
        } else {
            prepareNextQuestion(true);
        }
        render();
        if (state.phase !== 'conversation') elements.outcome.focus({ preventScroll: true });
    }

    function submitValue(value, dataset) {
        const text = normalize(value);
        if (!text) {
            elements.note.textContent = 'Décrivez brièvement ce que vous souhaitez améliorer.';
            elements.input.focus();
            return;
        }
        if (state.phase === 'start') startJourney(dataset && dataset.scenario ? dataset.scenario : null, text);
        else if (state.phase === 'conversation') answerCurrentQuestion(text, dataset && dataset.answer);
        elements.input.value = '';
    }

    function resetJourney() {
        sessionStorage.removeItem(storageKey);
        state = createInitialState();
        addMessage('assistant', 'Bonjour. Que souhaitez-vous améliorer ou mieux maîtriser dans votre organisation ?');
        render();
        elements.input.focus();
    }

    function prepareExternalPrompt(prompt) {
        elements.commandCenter.scrollIntoView({ behavior: 'smooth', block: 'center' });
        startJourney(core.detectEntryIntent(prompt), prompt);
    }

    function beginEdit(field) {
        const question = findQuestion(field);
        if (!question) return;
        delete state.answers[field];
        delete state.answerLabels[field];
        state.editingField = field;
        state.activeQuestion = question;
        state.phase = 'conversation';
        addMessage('assistant', 'Modifions ce point. ' + question.prompt);
        render();
        elements.input.focus();
    }

    elements.menuToggle.addEventListener('click', function () {
        const isOpen = elements.navigation.classList.toggle('is-open');
        elements.menuToggle.setAttribute('aria-expanded', String(isOpen));
        elements.menuToggle.setAttribute('aria-label', isOpen ? 'Fermer le menu' : 'Ouvrir le menu');
    });
    elements.navigation.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', closeMenu); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') closeMenu(); });
    elements.form.addEventListener('submit', function (event) {
        event.preventDefault();
        submitValue(elements.input.value, null);
    });
    elements.suggestions.addEventListener('click', function (event) {
        const button = event.target.closest('button');
        if (button) submitValue(button.dataset.value, button.dataset);
    });
    elements.outcome.addEventListener('click', function (event) {
        const target = event.target.closest('[data-action]');
        if (!target) return;
        const action = target.dataset.action;
        if (action === 'confirm-context') state.phase = 'reading';
        if (action === 'show-edit') state.phase = 'edit';
        if (action === 'show-confirmation') state.phase = 'confirmation';
        if (action === 'edit-answer') return beginEdit(target.dataset.field);
        if (action === 'structure-people-criteria') return beginEdit('peopleCriteria');
        if (action === 'show-recommendation') state.phase = 'recommendation';
        if (action === 'show-account') state.phase = 'account';
        if (action === 'show-followup') state.phase = 'followup';
        render();
        elements.outcome.focus({ preventScroll: true });
    });
    elements.reset.addEventListener('click', resetJourney);
    document.querySelectorAll('[data-prompt]').forEach(function (control) {
        control.addEventListener('click', function () { prepareExternalPrompt(control.dataset.prompt); });
    });

    restore();
    if (state.messages.length === 0) addMessage('assistant', 'Bonjour. Que souhaitez-vous améliorer ou mieux maîtriser dans votre organisation ?');
    render();
    document.getElementById('current-year').textContent = new Date().getFullYear();

    window.CivilinaAIJourney = {
        getState: function () { return JSON.parse(JSON.stringify(state)); },
        reset: resetJourney,
        startScenario: startJourney,
        recalculate: recalculate
    };
})();
