(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIRACIJourneyBridge =
            api;
    }

})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BRIDGE_VERSION =
            '1.0.0';

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
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();
        }

        function nullableText(value) {
            const text =
                normalize(value);

            if (!text) {
                return null;
            }

            const normalized =
                normalizeKey(text);

            if (
                [
                    'inconnu',
                    'inconnue',
                    'inconnus',
                    'inconnues',
                    'unknown',
                    'n/a',
                    'na',
                    'a definir',
                    'non defini',
                    'non definie'
                ].includes(normalized)
            ) {
                return null;
            }

            return text;
        }

        function splitRoles(value) {
            const text =
                nullableText(value);

            if (!text) {
                return [];
            }

            return text
                .split(/[,;]+/)
                .map(nullableText)
                .filter(Boolean);
        }

        function extractId(value) {
            const match =
                normalize(value)
                    .match(
                        /\b(ACT-\d+)\b/i
                    );

            return match
                ? match[1].toUpperCase()
                : null;
        }

        function parseField(segment) {
            const text =
                normalize(segment);

            const index =
                text.indexOf(':');

            if (index < 0) {
                return null;
            }

            return {
                label:
                    normalizeKey(
                        text.slice(
                            0,
                            index
                        )
                    ),

                value:
                    normalize(
                        text.slice(
                            index + 1
                        )
                    )
            };
        }

        function parseAssignmentLine(
            line
        ) {
            const segments =
                normalize(line)
                    .split('|')
                    .map(normalize)
                    .filter(Boolean);

            if (!segments.length) {
                return null;
            }

            const id =
                extractId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const row = {
                id,
                activity:
                    null,
                responsible:
                    [],
                accountable:
                    [],
                consulted:
                    [],
                informed:
                    []
            };

            segments
                .slice(1)
                .forEach(
                    function (segment) {
                        const field =
                            parseField(
                                segment
                            );

                        if (!field) {
                            return;
                        }

                        if (
                            [
                                'activite',
                                'activity'
                            ].includes(
                                field.label
                            )
                        ) {
                            row.activity =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            field.label ===
                                'r' ||
                            field.label ===
                                'responsible' ||
                            field.label ===
                                'responsable'
                        ) {
                            row.responsible =
                                splitRoles(
                                    field.value
                                );

                            return;
                        }

                        if (
                            field.label ===
                                'a' ||
                            field.label ===
                                'accountable' ||
                            field.label ===
                                'approbateur'
                        ) {
                            row.accountable =
                                splitRoles(
                                    field.value
                                );

                            return;
                        }

                        if (
                            field.label ===
                                'c' ||
                            field.label ===
                                'consulted' ||
                            field.label ===
                                'consulte'
                        ) {
                            row.consulted =
                                splitRoles(
                                    field.value
                                );

                            return;
                        }

                        if (
                            field.label ===
                                'i' ||
                            field.label ===
                                'informed' ||
                            field.label ===
                                'informe'
                        ) {
                            row.informed =
                                splitRoles(
                                    field.value
                                );
                        }
                    }
                );

            /*
             * Never construct a business activity
             * from an identifier alone.
             */
            if (!row.activity) {
                return null;
            }

            return row;
        }

        function parseAssignments(
            value
        ) {
            if (
                Array.isArray(value) &&
                value.every(
                    function (item) {
                        return (
                            item &&
                            typeof item ===
                                'object'
                        );
                    }
                )
            ) {
                return value;
            }

            return normalize(value)
                .split(/\r?\n/)
                .map(
                    parseAssignmentLine
                )
                .filter(Boolean);
        }

        function prepare(input) {
            const safeInput =
                input &&
                typeof input ===
                    'object'
                    ? input
                    : {};

            const sourceAnswers =
                safeInput.answers &&
                typeof safeInput.answers ===
                    'object'
                    ? safeInput.answers
                    : {};

            const assignments =
                parseAssignments(
                    sourceAnswers
                        .raciAssignments
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
                                    raciAssignments:
                                        assignments
                                }
                            )
                    }
                );

            return {
                bridgeVersion:
                    BRIDGE_VERSION,

                status:
                    assignments.length
                        ? 'READY_FOR_BUILDER'
                        : 'INCOMPLETE',

                ready:
                    assignments.length > 0,

                builderInput,

                traceability: {
                    sources: {
                        assignments:
                            sourceAnswers
                                .raciAssignments
                    },

                    associationMethod:
                        'explicit-activity-id',

                    inventedFacts:
                        false
                }
            };
        }

        return {
            BRIDGE_VERSION,
            parseAssignments,
            prepare
        };
    }
);