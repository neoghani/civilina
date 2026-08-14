(function (root, factory) {
    'use strict';
    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory(isCommonJs ? require('./civilina-ai-lexicon.js') : root.CivilinaAILexicon);
    if (isCommonJs) module.exports = api;
    else root.CivilinaAIRecognizer = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (lexicon) {
    'use strict';

    function normalizeText(text) {
        return String(text || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[’']/g, ' ')
            .replace(/[×]/g, 'x')
            .toLowerCase()
            .replace(/[^a-z0-9&]+/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();
    }

    function containsTerm(normalizedText, term) {
        const normalizedTerm = normalizeText(term);
        if (!normalizedTerm) return false;
        return (' ' + normalizedText + ' ').includes(' ' + normalizedTerm + ' ');
    }

    function unique(values) {
        return Array.from(new Set(values));
    }

    function recognize(text) {
        const normalized = normalizeText(text);
        const matches = [];
        lexicon.entries.forEach(function (entry) {
            const matchedTerms = entry.terms.filter(function (term) { return containsTerm(normalized, term); });
            if (matchedTerms.length) {
                matches.push({ id: entry.id, category: entry.category, value: entry.value, terms: matchedTerms, order: entry.order });
            }
        });
        const byCategory = matches.reduce(function (result, match) {
            if (!result[match.category]) result[match.category] = [];
            result[match.category].push(match.value);
            return result;
        }, {});
        Object.keys(byCategory).forEach(function (category) { byCategory[category] = unique(byCategory[category]); });

        const confirmedConnector = /\b(a cause de|en raison de|principalement cause par|cause par|because of|due to|caused by)\b/;
        const suspectedConnector = /\b(semble lie a|pourrait etre|pourrait etre cause par|peut etre cause par|suspecte|probablement|appears related to|may be caused by)\b/;
        const unknownCause = /\b(ne sais pas pourquoi|cause inconnue|cause non etablie|unknown cause|cause is unknown|sans cause identifiee)\b/.test(normalized);
        const confirmedIndex = normalized.search(confirmedConnector);
        const suspectedIndex = normalized.search(suspectedConnector);
        const conceptMatches = matches.filter(function (match) { return match.category === 'concept'; });
        const confirmedConcepts = confirmedIndex < 0 || (suspectedIndex >= 0 && confirmedIndex > suspectedIndex) ? [] : conceptMatches.filter(function (match) {
            return match.terms.some(function (term) { return normalized.indexOf(normalizeText(term)) > confirmedIndex; });
        }).map(function (match) { return match.value; });
        const suspectedConcepts = suspectedIndex < 0 ? [] : conceptMatches.filter(function (match) {
            return match.terms.some(function (term) { return normalized.indexOf(normalizeText(term)) > suspectedIndex; });
        }).map(function (match) { return match.value; });

        return {
            normalized,
            matches,
            byCategory,
            concepts: unique(byCategory.concept || []),
            confirmedConcepts: unique(confirmedConcepts),
            suspectedConcepts: unique(suspectedConcepts),
            unknownCause,
            hasNumbers: /\b\d+(?:[.,]\d+)?\b/.test(normalized),
            hasDocumentReference: /\b(document joint|fichier joint|piece jointe|ci joint|document fourni|document transmis|ce document|cet audit|attached|uploaded)\b/.test(normalized)
        };
    }

    return { normalizeText, containsTerm, recognize };
});
