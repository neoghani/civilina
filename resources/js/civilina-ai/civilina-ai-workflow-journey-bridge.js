(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIWorkflowJourneyBridge = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BRIDGE_VERSION = '1.0.0';

        function normalize(value) {
            if (
                value === null ||
                value === undefined
            ) {
                return '';
            }

            return String(value).trim();
        }

        function normalizeKey(value) {
            return normalize(value)
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function nullableText(value) {
            const text = normalize(value);

            if (!text) {
                return null;
            }

            const key = normalizeKey(text);

            if (
                [
                    'inconnu',
                    'inconnue',
                    'unknown',
                    'n/a',
                    'na',
                    'a definir',
                    'non defini',
                    'non definie'
                ].includes(key)
            ) {
                return null;
            }

            return text;
        }

        function extractStepId(value) {
            const match =
                normalize(value).match(
                    /\bSTEP-\d+\b/i
                );

            return match
                ? match[0].toUpperCase()
                : null;
        }

        function parseField(segment) {
            const text = normalize(segment);
            const index = text.indexOf(':');

            if (index < 0) {
                return null;
            }

            return {
                label: normalizeKey(
                    text.slice(0, index)
                ),

                value: normalize(
                    text.slice(index + 1)
                )
            };
        }

        function parseTransition(value) {
            const text = nullableText(value);

            if (!text) {
                return {
                    nextStep: null,
                    terminal: false
                };
            }

            const key = normalizeKey(text);

            if (
                [
                    'fin',
                    'final',
                    'termine',
                    'terminee',
                    'cloture',
                    'cloturee',
                    'end'
                ].includes(key)
            ) {
                return {
                    nextStep: null,
                    terminal: true
                };
            }

            return {
                nextStep: extractStepId(text),
                terminal: false
            };
        }

        function parseStepLine(line) {
            const segments =
                normalize(line)
                    .split('|')
                    .map(normalize)
                    .filter(Boolean);

            if (!segments.length) {
                return null;
            }

            const id =
                extractStepId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const row = {
                id,
                name: null,
                actor: null,
                action: null,
                nextStep: null,
                terminal: false,
                outcome: null
            };

            segments
                .slice(1)
                .forEach(function (segment) {
                    const field =
                        parseField(segment);

                    if (!field) {
                        return;
                    }

                    if (
                        [
                            'etape',
                            'step',
                            'nom',
                            'name'
                        ].includes(field.label)
                    ) {
                        row.name =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'acteur',
                            'actor',
                            'role'
                        ].includes(field.label)
                    ) {
                        row.actor =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        field.label ===
                        'action'
                    ) {
                        row.action =
                            nullableText(
                                field.value
                            );

                        return;
                    }

                    if (
                        [
                            'suivant',
                            'next',
                            'prochaine',
                            'prochaine etape'
                        ].includes(field.label)
                    ) {
                        const transition =
                            parseTransition(
                                field.value
                            );

                        row.nextStep =
                            transition.nextStep;

                        row.terminal =
                            transition.terminal;

                        return;
                    }

                    if (
                        [
                            'sortie',
                            'resultat',
                            'outcome',
                            'output'
                        ].includes(field.label)
                    ) {
                        row.outcome =
                            nullableText(
                                field.value
                            );
                    }
                });

            // Ne jamais inventer le nom métier
            // d'une étape à partir de son ID.
            if (!row.name) {
                return null;
            }

            return row;
        }

        function parseSteps(value) {
            if (
                Array.isArray(value) &&
                value.every(function (item) {
                    return (
                        item &&
                        typeof item ===
                            'object'
                    );
                })
            ) {
                return value;
            }

            return normalize(value)
                .split(/\r?\n/)
                .map(parseStepLine)
                .filter(Boolean);
        }

        function prepare(input) {
            const safeInput =
                input &&
                typeof input === 'object'
                    ? input
                    : {};

            const sourceAnswers =
                safeInput.answers &&
                typeof safeInput.answers ===
                    'object'
                    ? safeInput.answers
                    : {};

            const steps =
                parseSteps(
                    sourceAnswers.workflowSteps
                );

            const builderInput =
                Object.assign(
                    {},
                    safeInput,
                    {
                        answers:
                            Object.assign(
                                {},
                                sourceAnswers,
                                {
                                    workflowSteps:
                                        steps
                                }
                            )
                    }
                );

            return {
                bridgeVersion:
                    BRIDGE_VERSION,

                status:
                    steps.length
                        ? 'READY_FOR_BUILDER'
                        : 'INCOMPLETE',

                ready:
                    steps.length > 0,

                builderInput,

                traceability: {
                    sources: {
                        steps:
                            sourceAnswers.workflowSteps
                    },

                    associationMethod:
                        'explicit-step-id-and-transition',

                    inventedFacts:
                        false
                }
            };
        }

        return {
            BRIDGE_VERSION,
            parseSteps,
            prepare
        };
    }
);