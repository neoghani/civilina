(function (root, factory) {
    'use strict';

    const api = factory();

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaAIWorkflowBuilder = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        const BUILDER_VERSION = '1.0.0';

        const REQUIRED_FIELDS = [
            'workflowScope',
            'workflowSteps',
            'workflowActors',
            'workflowRules',
            'workflowTraceability'
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

            const key = text
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '')
                .toLowerCase();

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

        function structuredSteps(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value.filter(
                function (row) {
                    return (
                        row &&
                        typeof row === 'object' &&
                        nullableText(row.id) &&
                        nullableText(row.name)
                    );
                }
            );
        }

        function hasRequiredValue(
            answers,
            field
        ) {
            if (
                field === 'workflowSteps'
            ) {
                return (
                    structuredSteps(
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
            const id =
                nullableText(source.id);

            const name =
                nullableText(source.name);

            const actor =
                nullableText(source.actor);

            const action =
                nullableText(source.action);

            const nextStep =
                nullableText(
                    source.nextStep
                );

            const outcome =
                nullableText(
                    source.outcome
                );

            const terminal =
                source.terminal === true;

            const missingFields = [];

            if (!name) {
                missingFields.push(
                    'name'
                );
            }

            if (!actor) {
                missingFields.push(
                    'actor'
                );
            }

            if (!action) {
                missingFields.push(
                    'action'
                );
            }

            if (
                !terminal &&
                !nextStep
            ) {
                missingFields.push(
                    'nextStep'
                );
            }

            if (!outcome) {
                missingFields.push(
                    'outcome'
                );
            }

            return {
                id,
                name,
                actor,
                action,
                nextStep,
                terminal,
                outcome,

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
                        'workflow',

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
                structuredSteps(
                    answers.workflowSteps
                ).map(buildRow);

            if (!rows.length) {
                return {
                    artifactType:
                        'workflow',

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
                    'workflow',

                title:
                    'Workflow',

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

                    scope:
                        nullableText(
                            answers.workflowScope
                        )
                },

                declaredActors:
                    businessList(
                        answers.workflowActors
                    ),

                rules: {
                    statement:
                        nullableText(
                            answers.workflowRules
                        )
                },

                traceabilityRequirements: {
                    statement:
                        nullableText(
                            answers.workflowTraceability
                        )
                },

                rows,

                traceability: {
                    sources: {
                        scope:
                            answers.workflowScope,

                        steps:
                            answers.workflowSteps,

                        actors:
                            answers.workflowActors,

                        rules:
                            answers.workflowRules,

                        audit:
                            answers.workflowTraceability
                    },

                    generatedFacts:
                        [],

                    inventedSteps:
                        false,

                    inventedActors:
                        false,

                    inventedTransitions:
                        false,

                    inventedRules:
                        false
                },

                enrichment: {
                    required: [
                        {
                            id:
                                'WORKFLOW_ROLE_VALIDATION',

                            label:
                                'Validation des rôles et pouvoirs'
                        },
                        {
                            id:
                                'WORKFLOW_EXCEPTION_PATHS',

                            label:
                                'Chemins de rejet, retour et exception'
                        },
                        {
                            id:
                                'WORKFLOW_SLA_MONITORING',

                            label:
                                'Délais et suivi SLA'
                        }
                    ]
                }
            };

            return {
                artifactType:
                    'workflow',

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