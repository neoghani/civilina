(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIRACIBuilder = api;
    }

})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BUILDER_VERSION = '1.0.0';

        const REQUIRED_FIELDS = [
            'raciProcessScope',
            'raciActivities',
            'raciRoles',
            'raciAuthority',
            'raciAssignments'
        ];

        function normalize(value) {
            if (
                value === null ||
                value === undefined
            ) {
                return '';
            }

            return String(value).trim();
        }

        function nullableText(value) {
            const text = normalize(value);

            if (!text) {
                return null;
            }

            const normalized = text
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

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

        function businessList(value) {
            if (Array.isArray(value)) {
                return value
                    .map(nullableText)
                    .filter(Boolean);
            }

            return normalize(value)
                .split(/[;\r\n]+/)
                .map(nullableText)
                .filter(Boolean);
        }

        function assignmentsFrom(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value.filter(
                function (row) {
                    return (
                        row &&
                        typeof row === 'object' &&
                        nullableText(row.activity)
                    );
                }
            );
        }

        function hasRequiredValue(
            answers,
            field
        ) {
            const value =
                answers[field];

            if (
                field ===
                'raciAssignments'
            ) {
                return (
                    assignmentsFrom(value)
                        .length > 0
                );
            }

            return Boolean(
                nullableText(value)
            );
        }

        function validate(input) {
            const answers =
                input &&
                input.answers &&
                typeof input.answers ===
                    'object'
                    ? input.answers
                    : {};

            const missing =
                REQUIRED_FIELDS.filter(
                    function (field) {
                        return !hasRequiredValue(
                            answers,
                            field
                        );
                    }
                );

            const completed =
                REQUIRED_FIELDS.length -
                missing.length;

            return {
                valid:
                    missing.length === 0,

                required:
                    REQUIRED_FIELDS.length,

                completed,

                completion:
                    Math.round(
                        (
                            completed /
                            REQUIRED_FIELDS.length
                        ) * 100
                    ),

                missing
            };
        }

        function roleArray(value) {
            if (Array.isArray(value)) {
                return value
                    .map(nullableText)
                    .filter(Boolean);
            }

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

        function buildRow(
            source,
            index
        ) {
            const explicitId =
                nullableText(
                    source.id
                );

            const activity =
                nullableText(
                    source.activity
                );

            const responsible =
                roleArray(
                    source.responsible ||
                    source.r
                );

            const accountable =
                roleArray(
                    source.accountable ||
                    source.a
                );

            const consulted =
                roleArray(
                    source.consulted ||
                    source.c
                );

            const informed =
                roleArray(
                    source.informed ||
                    source.i
                );

            const missingFields = [];

            if (!activity) {
                missingFields.push(
                    'activity'
                );
            }

            if (!responsible.length) {
                missingFields.push(
                    'responsible'
                );
            }

            if (!accountable.length) {
                missingFields.push(
                    'accountable'
                );
            }

            if (!consulted.length) {
                missingFields.push(
                    'consulted'
                );
            }

            if (!informed.length) {
                missingFields.push(
                    'informed'
                );
            }

            return {
                id:
                    explicitId ||
                    (
                        'RACI-' +
                        String(
                            index + 1
                        ).padStart(
                            3,
                            '0'
                        )
                    ),

                activity,

                responsible,
                accountable,
                consulted,
                informed,

                missingFields,

                rowStatus:
                    missingFields.length
                        ? 'TO_BE_ENRICHED'
                        : 'STRUCTURED',

                source:
                    'user-provided'
            };
        }

        function build(input) {
            const safeInput =
                input &&
                typeof input ===
                    'object'
                    ? input
                    : {};

            const answers =
                safeInput.answers &&
                typeof safeInput.answers ===
                    'object'
                    ? safeInput.answers
                    : {};

            const validation =
                validate(
                    safeInput
                );

            if (!validation.valid) {
                return {
                    artifactType:
                        'raci',

                    status:
                        'INCOMPLETE',

                    ready:
                        false,

                    validation,

                    artifact:
                        null
                };
            }

            const sourceAssignments =
                assignmentsFrom(
                    answers.raciAssignments
                );

            const rows =
                sourceAssignments.map(
                    buildRow
                );

            if (!rows.length) {
                return {
                    artifactType:
                        'raci',

                    status:
                        'INCOMPLETE',

                    ready:
                        false,

                    validation,

                    artifact:
                        null
                };
            }

            const artifact = {
                type:
                    'raci',

                title:
                    'RACI Matrix',

                status:
                    'draft',

                revision:
                    safeInput.revision ||
                    'DRAFT-01',

                context: {
                    initialRequest:
                        nullableText(
                            safeInput.initialText
                        ),

                    project:
                        nullableText(
                            safeInput.project
                        ),

                    processScope:
                        nullableText(
                            answers.raciProcessScope
                        )
                },

                declaredActivities:
                    businessList(
                        answers.raciActivities
                    ),

                declaredRoles:
                    businessList(
                        answers.raciRoles
                    ),

                authority: {
                    statement:
                        nullableText(
                            answers.raciAuthority
                        )
                },

                rows,

                traceability: {
                    sources: {
                        processScope:
                            answers.raciProcessScope,

                        activities:
                            answers.raciActivities,

                        roles:
                            answers.raciRoles,

                        authority:
                            answers.raciAuthority,

                        assignments:
                            answers.raciAssignments
                    },

                    generatedFacts:
                        [],

                    inventedActivities:
                        false,

                    inventedRoles:
                        false,

                    inventedAssignments:
                        false
                },

                enrichment: {
                    required: [
                        {
                            id:
                                'RACI_WBS_ACTIVITY_LINKAGE',

                            label:
                                'Rattachement WBS / activités'
                        },
                        {
                            id:
                                'RACI_GOVERNANCE_REVIEW',

                            label:
                                'Revue de gouvernance et délégation'
                        }
                    ]
                }
            };

            return {
                artifactType:
                    'raci',

                status:
                    'READY',

                ready:
                    true,

                validation,

                artifact
            };
        }

        return {
            BUILDER_VERSION,
            REQUIRED_FIELDS,
            validate,
            build
        };
    }
);