(function (root, factory) {
    'use strict';

    const isCommonJs = typeof module === 'object' && module.exports;

    const api = factory(
        isCommonJs
            ? require('./civilina-ai-recognizer.js')
            : root.CivilinaAIRecognizer
    );

    if (isCommonJs) module.exports = api;
    else root.CivilinaAIArtifactRecognizer = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (recognizer) {
    'use strict';

    /*
     * CIVILINA Artifact Recognition
     *
     * Distinguishes:
     * - what the user wants Civilina to DO      -> interactionMode
     * - what the user wants Civilina to PRODUCE -> requestedArtifact
     * - what business subject is concerned       -> concepts/domains/entities
     */

    const ARTIFACTS = Object.freeze([
        {
            id: 'itp',
            label: 'Inspection & Test Plan',
            aliases: [
                'itp',
                'inspection test plan',
                'plan inspection et essais',
                'plan inspection essais'
            ],
            family: 'quality'
        },
        {
            id: 'wbs',
            label: 'Work Breakdown Structure',
            aliases: [
                'wbs',
                'work breakdown structure',
                'structure de decoupage'
            ],
            family: 'projectControls'
        },
        {
            id: 'riskRegister',
            label: 'Risk Register',
            aliases: [
                'registre des risques',
                'registre risques',
                'risk register'
            ],
            family: 'riskManagement'
        },
        {
            id: 'raci',
            label: 'RACI Matrix',
            aliases: [
                'matrice raci',
                'raci'
            ],
            family: 'organisation'
        },
        {
            id: 'dashboard',
            label: 'Dashboard',
            aliases: [
                'dashboard',
                'tableau de bord',
                'cockpit de pilotage'
            ],
            family: 'dataReporting'
        },
        {
            id: 'workflow',
            label: 'Workflow',
            aliases: [
                'workflow',
                'flux de validation',
                'circuit de validation'
            ],
            family: 'processManagement'
        }
    ]);

    function detect(text) {
        const normalized = recognizer.normalizeText(text);

        const matches = ARTIFACTS
            .map(function (artifact) {
                const aliases = artifact.aliases.filter(function (alias) {
                    return recognizer.containsTerm(normalized, alias);
                });

                if (!aliases.length) return null;

                return {
                    id: artifact.id,
                    label: artifact.label,
                    family: artifact.family,
                    matchedAlias: aliases[0],
                    confidence: 'direct',
                    reason: 'Artefact explicitement demande ou mentionne.'
                };
            })
            .filter(Boolean);

        return {
            normalized,
            primary: matches[0] || null,
            matches
        };
    }

    return {
        ARTIFACTS,
        detect
    };
});