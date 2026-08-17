(function (root, factory) {
    'use strict';

    const isCommonJs = typeof module === 'object' && module.exports;

    const api = factory(
        isCommonJs
            ? require('./civilina-ai-recognizer.js')
            : root.CivilinaAIRecognizer
    );

    if (isCommonJs) module.exports = api;
    else root.CivilinaAIActionRecognizer = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (recognizer) {
    'use strict';

    /*
     * CIVILINA Expert Action Recognition
     *
     * Purpose:
     * recognise what the user is asking Civilina to DO,
     * independently from the business-domain lexicon.
     *
     * This deliberately avoids adding every conjugated verb
     * to the main Civilina lexicon.
     */

    const ACTION_RULES = Object.freeze([
        {
            mode: 'create',
            verbs: [
                'cree',
                'creer',
                'creez',
                'genere',
                'generer',
                'produis',
                'produire',
                'prepare',
                'preparer',
                'redige',
                'rediger',
                'elabore',
                'elaborer',
                'construis',
                'construire',
                'build',
                'create',
                'generate',
                'produce',
                'draft'
            ]
        },
        {
            mode: 'design',
            verbs: [
                'concois',
                'concevoir',
                'designer',
                'modelise',
                'modeliser',
                'architecture',
                'architecturer',
                'design'
            ]
        },
        {
            mode: 'audit',
            verbs: [
                'audite',
                'auditer',
                'auditez',
                'audit',
                'assess compliance'
            ]
        },
        {
            mode: 'critique',
            verbs: [
                'critique',
                'critiquer',
                'challenge',
                'challenger'
            ]
        },
        {
            mode: 'review',
            verbs: [
                'analyse',
                'analyser',
                'examine',
                'examiner',
                'review',
                'revois',
                'revoir'
            ]
        },
        {
            mode: 'compare',
            verbs: [
                'compare',
                'comparer'
            ]
        },
        {
            mode: 'prioritize',
            verbs: [
                'priorise',
                'prioriser',
                'classe',
                'classer',
                'prioritize',
                'rank'
            ]
        },
        {
            mode: 'calculate',
            verbs: [
                'calcule',
                'calculer',
                'compute',
                'calculate'
            ]
        },
        {
            mode: 'plan',
            verbs: [
                'planifie',
                'planifier',
                'plan'
            ]
        },
        {
            mode: 'troubleshoot',
            verbs: [
                'resous',
                'resoudre',
                'corrige',
                'corriger',
                'depanne',
                'depanner',
                'troubleshoot',
                'fix'
            ]
        },
        {
            mode: 'decide',
            verbs: [
                'decide',
                'decider',
                'choisis',
                'choisir',
                'arbitre',
                'arbitrer',
                'decide',
                'choose'
            ]
        },
        {
            mode: 'explain',
            verbs: [
                'explique',
                'expliquer',
                'explain'
            ]
        },
        {
            mode: 'diagnose',
            verbs: [
                'diagnostique',
                'diagnostiquer',
                'diagnose'
            ]
        }
    ]);

    const COMMAND_PREFIXES = Object.freeze([
        '',
        'peux tu ',
        'peux tu me ',
        'pourrais tu ',
        'pourrais tu me ',
        'pouvez vous ',
        'pouvez vous me ',
        'merci de ',
        'je veux que tu ',
        'je voudrais que tu '
    ]);

    function startsWithAction(text, verb) {
        return COMMAND_PREFIXES.some(function (prefix) {
            const expression = prefix + verb;

            return text === expression ||
                text.startsWith(expression + ' ');
        });
    }

    function detect(text) {
        const normalized = recognizer.normalizeText(text);
        const matches = [];

        ACTION_RULES.forEach(function (rule) {
            const matchedVerb = rule.verbs.find(function (verb) {
                return startsWithAction(normalized, verb);
            });

            if (matchedVerb) {
                matches.push({
                    mode: rule.mode,
                    verb: matchedVerb,
                    strength: 'direct-command',
                    reason: 'Action explicite demandee par l utilisateur.'
                });
            }
        });

        /*
         * The leading explicit action wins.
         *
         * Example:
         * "Cree un ITP pour auditer les inspections"
         * is primarily CREATE, not AUDIT.
         */
        const primaryMode = matches.length
            ? matches[0].mode
            : null;

        return {
            normalized,
            primaryMode,
            modes: matches.map(function (match) {
                return match.mode;
            }),
            matches
        };
    }

    return {
        ACTION_RULES,
        detect
    };
});