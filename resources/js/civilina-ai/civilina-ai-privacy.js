(function (root, factory) {
    'use strict';

    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CivilinaAIPrivacy = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const SHARE_INTENT = /\b(envoyer|envoi|partager|partage|joindre|uploader|upload|televerser|transmettre|transmission|deposer|importer|montrer|fournir|communiquer|mettre\s+a\s+disposition|vous\s+donner|vous\s+adresser)\b/;
    const NEGATED_SHARE_INTENT = /\b(?:sans|ne\s+(?:pas|plus|jamais)|ne\b.{0,20}\b(?:pas|plus|jamais)|pas\s+question\s+de)\b.{0,30}\b(?:envoyer|partager|joindre|uploader|televerser|transmettre|deposer|importer|fournir|communiquer)\b/;
    const SENSITIVE_OBJECT = /\b(contrats?|devis|factures?|budgets?|business\s+plans?|previsions?|forecasts?(?:\s+financiers?)?|p\s*&\s*l|comptes?\s+de\s+resultat|bilans?|cash[-\s]?flows?|donnees?\s+financieres?|finances?\s+internes?|paie|fiches?\s+de\s+paie|bulletins?\s+de\s+paie|salaires?|marges?|tresorerie|donnees?\s+rh|donnees?\s+personnelles?\s+nominatives?|procedures?\s+internes?|documents?\s+internes?|offres?\s+commerciales?|offres?\s+fournisseurs?|devis\s+fournisseurs?|prix(?:\s+confidentiels?)?|bordereaux?\s+de\s+prix|bpu|dqe|tarifs?\s+internes?|donnees?\s+clients?|listes?\s+clients?|clients?|donnees?\s+fournisseurs?|listes?\s+fournisseurs?|fournisseurs?|nda|documents?\s+confidentiels?)\b/;
    const PSYCHOMETRIC = /\b(mbti|personnalite|psychometr|style\s+de\s+travail)\b/;
    const PERSON_OBJECT = /\b(candidats?|salaries?|employes?|collaborateurs?|personnes?|individus?|techniciens?|responsables?|chefs?|managers?|directeurs?|superviseurs?|coordinateurs?)\b/;
    const PEOPLE_CONTEXT = /\b(recrutement|recruter|competences?|affectation|promotion|mobilite|equipe|ressources?\s+humaines?|rh|poste|fonction)\b/;
    const DECISION_VERB = /\b(choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier|prendre\s+la\s+direction|prendre\s+la\s+responsabilite)\b/;
    const ROLE_CONTEXT = /\b(manager|chef(?:s)?(?:\s+de\s+projet|\s+de\s+chantier|\s+d\s+equipe)?|responsable|directeur|superviseur|coordinateur|direction|responsabilite|poste|fonction)\b/;
    const PERSON_QUESTION = /\b(qui|quel(?:le|s|les)?|lequel|laquelle|lesquels|lesquelles)\b/;
    const NON_PEOPLE_PROFILE = /\bprofil\s+(?:de\s+|du\s+)?(?:risque|projet|cout|financier|tresorerie|marge|planning)\b/;
    const PROJECT_MANAGEMENT_VERB = /\bmanager\s+(?:un\s+|le\s+|mon\s+|notre\s+|des\s+)?(?:projet|programme|portefeuille|risque|planning)\b/;
    const NON_PEOPLE_MANAGEMENT = /\b(?:management|gestion)\s+(?:du\s+|de\s+|des\s+)?(?:projet|programme|portefeuille|chantier|risque|planning)\b/;

    function normalizeText(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, ' ')
            .toLowerCase()
            .replace(/\s+/g, ' ')
            .trim();
    }

    function detectSensitiveIntent(text) {
        const value = normalizeText(text);
        return !NEGATED_SHARE_INTENT.test(value) && SHARE_INTENT.test(value) && SENSITIVE_OBJECT.test(value);
    }

    function detectPeopleDecision(text) {
        const value = normalizeText(text);
        if (NON_PEOPLE_PROFILE.test(value)) return false;

        const asksForPerson = PERSON_QUESTION.test(value);
        const hasDecision = DECISION_VERB.test(value);
        const hasRole = ROLE_CONTEXT.test(value);
        const hasPerson = PERSON_OBJECT.test(value);
        const explicitDirectionDecision = /\bqui\b.{0,35}\bpeut\b.{0,25}\bprendre\s+la\s+(?:direction|responsabilite)\b/.test(value);
        const orderedDecision = /\b(?:choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier)\b.{0,55}\b(?:candidat|salarie|employe|collaborateur|technicien|personne|manager|chef|responsable|directeur|superviseur|coordinateur)\b/.test(value)
            || /\b(?:candidat|salarie|employe|collaborateur|technicien|personne|manager|chef|responsable|directeur|superviseur|coordinateur)\b.{0,55}\b(?:choisir|selectionner|nommer|affecter|promouvoir|recruter|designer|remplacer|mettre|confier)\b/.test(value);

        if (explicitDirectionDecision) return true;
        if (asksForPerson && hasDecision && hasRole) return true;
        if (orderedDecision && (hasPerson || hasRole) && (asksForPerson || /\b(meilleur|candidate?|salarie|collaborateur|technicien)\b/.test(value))) return true;
        return false;
    }

    function detectPeopleSignal(text) {
        const value = normalizeText(text);
        if (detectPeopleDecision(value)) return true;
        if ((NON_PEOPLE_PROFILE.test(value) || PROJECT_MANAGEMENT_VERB.test(value) || NON_PEOPLE_MANAGEMENT.test(value))
            && !PSYCHOMETRIC.test(value) && !/\bequipe\b/.test(value) && !/\brecrut/.test(value)) return false;
        return PSYCHOMETRIC.test(value)
            || PEOPLE_CONTEXT.test(value)
            || /\bprofil\s+(?:candidat|professionnel|de\s+poste)\b/.test(value)
            || /\b(?:notre|mon|mes|les)\s+(?:equipe|collaborateurs?|salaries?|employes?)\b/.test(value);
    }

    function sanitizeFreeText() {
        return '[free-text-answer]';
    }

    function sanitizeMessage(role, text) {
        if (role !== 'user') return { role: role, text: text };
        return { role: role, text: detectSensitiveIntent(text) ? '[Information sensible non conservée]' : '[Réponse libre non conservée]' };
    }

    function buildSafeStorageState(state) {
        const answers = {};
        Object.keys(state.answers || {}).forEach(function (key) {
            const value = state.answers[key];
            answers[key] = typeof value === 'string' && /^[a-z0-9-]{1,48}$/i.test(value) ? value : sanitizeFreeText();
        });
        return {
            version: state.version,
            entryIntent: state.entryIntent,
            phase: state.phase,
            step: state.step,
            answers: answers,
            answerLabels: state.answerLabels || {},
            derivedTextFacts: Array.isArray(state.derivedTextFacts) ? state.derivedTextFacts.slice(0, 24) : [],
            questionCount: state.questionCount || 0,
            messages: (state.messages || []).slice(-12).map(function (message) { return sanitizeMessage(message.role, message.text); }),
            sensitiveIntent: Boolean(state.sensitiveIntent),
            peopleGuardrail: Boolean(state.peopleGuardrail)
        };
    }

    return {
        normalizeText: normalizeText,
        detectSensitiveIntent: detectSensitiveIntent,
        detectPeopleSignal: detectPeopleSignal,
        detectPeopleDecision: detectPeopleDecision,
        sanitizeFreeText: sanitizeFreeText,
        sanitizeMessage: sanitizeMessage,
        buildSafeStorageState: buildSafeStorageState
    };
});
