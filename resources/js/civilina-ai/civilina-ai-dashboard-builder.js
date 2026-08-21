(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIDashboardBuilder = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BUILDER_VERSION = '1.0.0';

        const REQUIRED_FIELDS = [
            'dashboardDecision',
            'dashboardAudience',
            'dashboardKpi',
            'dashboardDataSource',
            'dashboardFrequency'
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

        function normalizeKey(value) {
            return normalize(value)
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();
        }

        function nullableText(value) {
            const text =
                normalize(value);

            if (!text) {
                return null;
            }

            const key =
                normalizeKey(text);

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

        function structuredKpis(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value.filter(
                function (item) {
                    return (
                        item &&
                        typeof item === 'object' &&
                        nullableText(item.id) &&
                        nullableText(item.name)
                    );
                }
            );
        }

        function structuredSources(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value.filter(
                function (item) {
                    return (
                        item &&
                        typeof item === 'object' &&
                        nullableText(item.id)
                    );
                }
            );
        }

        function hasRequiredValue(
            answers,
            field
        ) {
            if (
                field === 'dashboardKpi'
            ) {
                return (
                    structuredKpis(
                        answers[field]
                    ).length > 0
                );
            }

            if (
                field === 'dashboardDataSource'
            ) {
                return (
                    structuredSources(
                        answers[field]
                    ).length > 0
                );
            }

            return Boolean(
                nullableText(
                    answers[field]
                )
            );
        }

        function validate(input) {
            const answers =
                input &&
                input.answers &&
                typeof input.answers === 'object'
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

        function buildRow(source) {
            const missingFields = [];

            const row = {
                id:
                    nullableText(source.id),

                name:
                    nullableText(source.name),

                value:
                    nullableText(source.value),

                unit:
                    nullableText(source.unit),

                target:
                    nullableText(source.target),

                trend:
                    nullableText(source.trend),

                source:
                    nullableText(source.source),

                dataOwner:
                    nullableText(source.dataOwner)
            };

            if (!row.value) {
                missingFields.push('value');
            }

            if (!row.unit) {
                missingFields.push('unit');
            }

            if (!row.target) {
                missingFields.push('target');
            }

            if (!row.source) {
                missingFields.push('source');
            }

            if (!row.dataOwner) {
                missingFields.push(
                    'dataOwner'
                );
            }

            row.missingFields =
                missingFields;

            row.rowStatus =
                missingFields.length
                    ? 'TO_BE_ENRICHED'
                    : 'STRUCTURED';

            row.sourceType =
                'user-provided';

            return row;
        }

        function build(input) {
            const safeInput =
                input &&
                typeof input === 'object'
                    ? input
                    : {};

            const answers =
                safeInput.answers &&
                typeof safeInput.answers === 'object'
                    ? safeInput.answers
                    : {};

            const validation =
                validate(safeInput);

            if (!validation.valid) {
                return {
                    artifactType:
                        'dashboard',

                    status:
                        'INCOMPLETE',

                    ready:
                        false,

                    validation,

                    artifact:
                        null
                };
            }

            const rows =
                structuredKpis(
                    answers.dashboardKpi
                ).map(buildRow);

            const dataSources =
                structuredSources(
                    answers.dashboardDataSource
                );

            const artifact = {
                type:
                    'dashboard',

                title:
                    'Dashboard de pilotage',

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

                    decision:
                        nullableText(
                            answers.dashboardDecision
                        ),

                    audience:
                        nullableText(
                            answers.dashboardAudience
                        ),

                    frequency:
                        nullableText(
                            answers.dashboardFrequency
                        )
                },

                rows,

                dataSources,

                unassociatedDataSources:
                    Array.isArray(
                        safeInput.unassociatedDataSources
                    )
                        ? safeInput
                            .unassociatedDataSources
                        : [],

                traceability: {
                    sources: {
                        decision:
                            answers.dashboardDecision,

                        audience:
                            answers.dashboardAudience,

                        kpis:
                            answers.dashboardKpi,

                        dataSources:
                            answers.dashboardDataSource,

                        frequency:
                            answers.dashboardFrequency
                    },

                    generatedFacts:
                        [],

                    inventedKpis:
                        false,

                    inventedValues:
                        false,

                    inventedTargets:
                        false,

                    inventedDataSources:
                        false,

                    inventedOwners:
                        false
                },

                enrichment: {
                    required: [
                        {
                            id:
                                'DASHBOARD_THRESHOLD_RULES',

                            label:
                                'Seuils et règles d’alerte'
                        },
                        {
                            id:
                                'DASHBOARD_HISTORY',

                            label:
                                'Historique et tendances'
                        },
                        {
                            id:
                                'DASHBOARD_DATA_QUALITY',

                            label:
                                'Contrôle qualité des données'
                        }
                    ]
                }
            };

            return {
                artifactType:
                    'dashboard',

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