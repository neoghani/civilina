(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIRiskRegisterJourneyBridge =
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
                .toLowerCase()
                .replace(
                    /\s+/g,
                    ' '
                );
        }

        function cleanTerminalPunctuation(
            value
        ) {
            const text =
                normalize(value);

            if (!text) {
                return '';
            }

            return text
                .replace(
                    /[.;]+\s*$/g,
                    ''
                )
                .trim();
        }

        function nullableText(value) {
            const text =
                cleanTerminalPunctuation(
                    value
                );

            if (!text) {
                return null;
            }

            const normalized =
                normalizeKey(text);

            const unknownValues = [
                'inconnu',
                'inconnue',
                'inconnus',
                'inconnues',
                'unknown',
                'non connu',
                'non connue',
                'non renseigne',
                'non renseignee',
                'a definir',
                'non defini',
                'non definie',
                'n/a',
                'na'
            ];

            if (
                unknownValues.includes(
                    normalized
                )
            ) {
                return null;
            }

            return text;
        }

        function numericValue(value) {
            const text =
                normalize(value);

            if (!text) {
                return null;
            }

            const number =
                Number(
                    text.replace(',', '.')
                );

            return Number.isFinite(number)
                ? number
                : null;
        }

        function splitLines(value) {
            if (
                Array.isArray(value)
            ) {
                return value
                    .map(normalize)
                    .filter(Boolean);
            }

            return normalize(value)
                .split(/\r?\n/)
                .map(normalize)
                .filter(Boolean);
        }

        function extractRiskId(value) {
            const text =
                normalize(value);

            if (!text) {
                return null;
            }

            const match =
                text.match(
                    /\b([A-Za-z]+-\d+)\b/
                );

            return match
                ? match[1]
                    .toUpperCase()
                : null;
        }

        function splitLabelValue(segment) {
            const text =
                normalize(segment);

            const separator =
                text.indexOf(':');

            if (separator < 0) {
                return null;
            }

            const label =
                normalizeKey(
                    text.slice(
                        0,
                        separator
                    )
                );

            const value =
                normalize(
                    text.slice(
                        separator + 1
                    )
                );

            return {
                label,
                value
            };
        }

        function parseRiskLine(line) {
            const segments =
                normalize(line)
                    .split('|')
                    .map(normalize)
                    .filter(Boolean);

            if (!segments.length) {
                return null;
            }

            const id =
                extractRiskId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const row = {
                id,
                category:
                    null,
                risk:
                    null,
                cause:
                    null,
                consequence:
                    null,
                probability:
                    null,
                impact:
                    null,
                owner:
                    null,
                response:
                    null,
                dueDate:
                    null,
                status:
                    null
            };

            segments
                .slice(1)
                .forEach(
                    function (segment) {
                        const field =
                            splitLabelValue(
                                segment
                            );

                        if (!field) {
                            return;
                        }

                        const label =
                            field.label;

                        if (
                            label ===
                                'risque' ||
                            label ===
                                'risk'
                        ) {
                            row.risk =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            label ===
                            'categorie'
                        ) {
                            row.category =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            label ===
                            'cause'
                        ) {
                            row.cause =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            label ===
                                'consequence' ||
                            label ===
                                'impact descriptif'
                        ) {
                            row.consequence =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            label ===
                                'probabilite' ||
                            label ===
                                'probability'
                        ) {
                            row.probability =
                                numericValue(
                                    field.value
                                );

                            return;
                        }

                        if (
                            label ===
                            'impact'
                        ) {
                            row.impact =
                                numericValue(
                                    field.value
                                );
                        }
                    }
                );

            /*
             * A risk description must have been
             * explicitly supplied by the user.
             *
             * We never manufacture a risk from
             * an identifier alone.
             */
            if (!row.risk) {
                return null;
            }

            return row;
        }

        function parseRiskItems(value) {
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
                return value.map(
                    function (item) {
                        return {
                            id:
                                extractRiskId(
                                    item.id
                                ) ||
                                null,

                            category:
                                nullableText(
                                    item.category
                                ),

                            risk:
                                nullableText(
                                    item.risk
                                ),

                            cause:
                                nullableText(
                                    item.cause
                                ),

                            consequence:
                                nullableText(
                                    item.consequence
                                ),

                            probability:
                                numericValue(
                                    item.probability
                                ),

                            impact:
                                numericValue(
                                    item.impact
                                ),

                            owner:
                                nullableText(
                                    item.owner
                                ),

                            response:
                                nullableText(
                                    item.response
                                ),

                            dueDate:
                                nullableText(
                                    item.dueDate
                                ),

                            status:
                                nullableText(
                                    item.status
                                )
                        };
                    }
                )
                    .filter(
                        function (row) {
                            return Boolean(
                                row.risk
                            );
                        }
                    );
            }

            return splitLines(value)
                .map(parseRiskLine)
                .filter(Boolean);
        }

        function parseOwners(value) {
            const map =
                new Map();

            normalize(value)
                .split(
                    /[;\r\n]+/
                )
                .map(normalize)
                .filter(Boolean)
                .forEach(
                    function (statement) {
                        const id =
                            extractRiskId(
                                statement
                            );

                        if (!id) {
                            return;
                        }

                        const separator =
                            statement.indexOf(
                                ':'
                            );

                        if (
                            separator < 0
                        ) {
                            return;
                        }

                        const owner =
                            nullableText(
                                statement.slice(
                                    separator + 1
                                )
                            );

                        if (owner) {
                            map.set(
                                id,
                                owner
                            );
                        }
                    }
                );

            return map;
        }

        function parseTreatmentLine(
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
                extractRiskId(
                    segments[0]
                );

            if (!id) {
                return null;
            }

            const result = {
                id,
                response:
                    null,
                dueDate:
                    null,
                status:
                    null
            };

            segments
                .slice(1)
                .forEach(
                    function (segment) {
                        const field =
                            splitLabelValue(
                                segment
                            );

                        if (!field) {
                            return;
                        }

                        const label =
                            field.label;

                        if (
                            [
                                'reponse',
                                'response',
                                'traitement',
                                'action'
                            ].includes(
                                label
                            )
                        ) {
                            result.response =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            [
                                'echeance',
                                'date',
                                'due date',
                                'duedate'
                            ].includes(
                                label
                            )
                        ) {
                            result.dueDate =
                                nullableText(
                                    field.value
                                );

                            return;
                        }

                        if (
                            [
                                'statut',
                                'status',
                                'etat'
                            ].includes(
                                label
                            )
                        ) {
                            result.status =
                                nullableText(
                                    field.value
                                );
                        }
                    }
                );

            return result;
        }

        function parseTreatments(value) {
            const map =
                new Map();

            splitLines(value)
                .map(
                    parseTreatmentLine
                )
                .filter(Boolean)
                .forEach(
                    function (item) {
                        map.set(
                            item.id,
                            item
                        );
                    }
                );

            return map;
        }

        function enrichRows(
            rows,
            owners,
            treatments
        ) {
            return rows.map(
                function (row) {
                    const id =
                        row.id;

                    const treatment =
                        id
                            ? treatments.get(
                                id
                            )
                            : null;

                    return Object.assign(
                        {},
                        row,
                        {
                            /*
                             * Association is performed
                             * strictly by explicit risk ID.
                             * Never by array position.
                             */
                            owner:
                                row.owner ||
                                (
                                    id
                                        ? owners.get(
                                            id
                                        ) ||
                                            null
                                        : null
                                ),

                            response:
                                row.response ||
                                (
                                    treatment
                                        ? treatment.response
                                        : null
                                ),

                            dueDate:
                                row.dueDate ||
                                (
                                    treatment
                                        ? treatment.dueDate
                                        : null
                                ),

                            status:
                                row.status ||
                                (
                                    treatment
                                        ? treatment.status
                                        : null
                                )
                        }
                    );
                }
            );
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

            const rows =
                parseRiskItems(
                    sourceAnswers.riskItems
                );

            const owners =
                parseOwners(
                    sourceAnswers.riskOwners
                );

            const treatments =
                parseTreatments(
                    sourceAnswers.riskTreatment
                );

            const enrichedRows =
                enrichRows(
                    rows,
                    owners,
                    treatments
                );

            const builderAnswers =
                Object.assign(
                    {},
                    sourceAnswers,
                    {
                        riskItems:
                            enrichedRows
                    }
                );

            const builderInput =
                Object.assign(
                    {},
                    safeInput,
                    {
                        answers:
                            builderAnswers
                    }
                );

            const ready =
                enrichedRows.length > 0;

            return {
                bridgeVersion:
                    BRIDGE_VERSION,

                status:
                    ready
                        ? 'READY_FOR_BUILDER'
                        : 'INCOMPLETE',

                ready,

                builderInput,

                traceability: {
                    sources: {
                        riskItems:
                            sourceAnswers.riskItems ||
                            null,

                        riskOwners:
                            sourceAnswers.riskOwners ||
                            null,

                        riskTreatment:
                            sourceAnswers.riskTreatment ||
                            null
                    },

                    associationMethod:
                        'explicit-risk-id',

                    inventedFacts:
                        false
                }
            };
        }

        return {
            BRIDGE_VERSION,
            parseRiskItems,
            parseOwners,
            parseTreatments,
            prepare
        };
    }
);