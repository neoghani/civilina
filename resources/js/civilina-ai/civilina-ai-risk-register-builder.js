    (function (root, factory) {
        'use strict';

        const isCommonJs =
            typeof module === 'object' &&
            module.exports;

        const api = factory();

        if (isCommonJs) {
            module.exports = api;
        } else {
            root.CivilinaAIRiskRegisterBuilder = api;
        }
    })(
        typeof globalThis !== 'undefined'
            ? globalThis
            : this,

        function () {
            'use strict';

            /*
            * CIVILINA Risk Register Builder
            * Sprint 2.9 — v1
            *
            * Purpose:
            * Build a deterministic project risk register
            * only from explicit user-provided risk information.
            *
            * Guardrails:
            * - does not invent risks;
            * - does not invent causes or consequences;
            * - does not invent probability or impact;
            * - calculates criticality only when the user-defined
            *   scoring method explicitly supports P x I;
            * - preserves owners, responses, dates and status;
            * - exposes missing enrichment explicitly;
            * - preserves source traceability.
            */

            const BUILDER_VERSION =
                '1.0.0';

            const REQUIRED_FIELDS =
                Object.freeze([
                    {
                        key:
                            'riskScope',
                        label:
                            'Périmètre des risques'
                    },
                    {
                        key:
                            'riskCategories',
                        label:
                            'Catégories de risques'
                    },
                    {
                        key:
                            'riskScoring',
                        label:
                            'Méthode de cotation'
                    },
                    {
                        key:
                            'riskOwners',
                        label:
                            'Responsables des risques'
                    },
                    {
                        key:
                            'riskItems',
                        label:
                            'Risques identifiés'
                    }
                ]);

            function normalize(value) {
                return String(
                    value === undefined ||
                    value === null
                        ? ''
                        : value
                )
                    .replace(/\s+/g, ' ')
                    .trim();
            }

            function normalizeForMatch(value) {
                return normalize(value)
                    .normalize('NFD')
                    .replace(
                        /[\u0300-\u036f]/g,
                        ''
                    )
                    .replace(
                        /[’']/g,
                        ' '
                    )
                    .toLowerCase();
            }

            function unique(values) {
                return Array.from(
                    new Set(
                        (values || [])
                            .map(normalize)
                            .filter(Boolean)
                    )
                );
            }

            function splitBusinessList(value) {
                const text =
                    normalize(value);

                if (!text) {
                    return [];
                }

                return unique(
                    text
                        .split(
                            /\s*(?:;|\n|\r|\.\s+)\s*/
                        )
                        .map(
                            function (item) {
                                return item
                                    .replace(
                                        /^[\-\u2022]\s*/,
                                        ''
                                    )
                                    .trim();
                            }
                        )
                );
            }

            function riskItemsFrom(
                answers
            ) {
                return Array.isArray(
                    answers &&
                    answers.riskItems
                )
                    ? answers.riskItems
                    : [];
            }

            function hasRequiredValue(
                answers,
                key
            ) {
                if (
                    key ===
                    'riskItems'
                ) {
                    return riskItemsFrom(
                        answers
                    ).some(
                        function (item) {
                            return Boolean(
                                item &&
                                typeof item ===
                                    'object' &&
                                normalize(
                                    item.risk
                                )
                            );
                        }
                    );
                }

                return Boolean(
                    normalize(
                        answers &&
                        answers[key]
                    )
                );
            }

            function validate(input) {
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

                const missing =
                    REQUIRED_FIELDS
                        .filter(
                            function (field) {
                                return !hasRequiredValue(
                                    answers,
                                    field.key
                                );
                            }
                        )
                        .map(
                            function (field) {
                                return {
                                    key:
                                        field.key,
                                    label:
                                        field.label
                                };
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
                            ) *
                            100
                        ),

                    missing
                };
            }

            function createSourceTrace(
                field,
                value
            ) {
                return {
                    field,
                    source:
                        'user-provided',
                    value
                };
            }

            /*
            * ------------------------------------------------------------
            * SCORING
            * ------------------------------------------------------------
            */

            function parseScoring(
                statement
            ) {
                const raw =
                    normalize(statement);

                const text =
                    normalizeForMatch(
                        statement
                    );

                const is5x5 =
                    /\b5\s*x\s*5\b/
                        .test(text) ||
                    /\b5x5\b/
                        .test(text);

                const hasProbability =
                    /\b(?:probabilite|probability|proba)\b/
                        .test(text);

                const hasImpact =
                    /\bimpact\b/
                        .test(text);

                const hasProductFormula =
                    (
                        hasProbability &&
                        hasImpact
                    ) &&
                    (
                        /\bprobabilite\s*x\s*impact\b/
                            .test(text) ||
                        /\bprobability\s*x\s*impact\b/
                            .test(text) ||
                        /\bproba\s*x\s*impact\b/
                            .test(text) ||
                        /\bprobabilite\s*(?:fois|multipliee?\s+par)\s*impact\b/
                            .test(text) ||
                        /\bproduct\b/
                            .test(text) ||
                        /\bproduit\b/
                            .test(text)
                    );

                return {
                    statement:
                        raw,

                    method:
                        is5x5
                            ? 'matrix-5x5'
                            : 'user-defined',

                    formula:
                        hasProductFormula
                            ? 'probability-impact-product'
                            : null,

                    probabilityScale:
                        is5x5
                            ? {
                                min:
                                    1,
                                max:
                                    5
                            }
                            : null,

                    impactScale:
                        is5x5
                            ? {
                                min:
                                    1,
                                max:
                                    5
                            }
                            : null
                };
            }

            function numericValue(
                value
            ) {
                if (
                    value === null ||
                    value === undefined ||
                    value === ''
                ) {
                    return null;
                }

                const number =
                    Number(value);

                return Number.isFinite(
                    number
                )
                    ? number
                    : null;
            }

            function calculateCriticality(
                probability,
                impact,
                scoring
            ) {
                if (
                    !scoring ||
                    scoring.formula !==
                        'probability-impact-product'
                ) {
                    return null;
                }

                const p =
                    numericValue(
                        probability
                    );

                const i =
                    numericValue(
                        impact
                    );

                if (
                    p === null ||
                    i === null
                ) {
                    return null;
                }

                return p * i;
            }

            /*
            * ------------------------------------------------------------
            * ROWS
            * ------------------------------------------------------------
            */

            function missingFieldsForRow(
                row
            ) {
                const fields = [
                    'category',
                    'risk',
                    'cause',
                    'consequence',
                    'probability',
                    'impact',
                    'owner',
                    'response',
                    'dueDate',
                    'status'
                ];

                return fields.filter(
                    function (field) {
                        return (
                            row[field] ===
                                null ||
                            row[field] ===
                                ''
                        );
                    }
                );
            }

            function buildRiskRow(
                item,
                index,
                scoring
            ) {
                const probability =
                    numericValue(
                        item.probability
                    );

                const impact =
                    numericValue(
                        item.impact
                    );

                const row = {
                    id:
                        normalize(
                            item.id
                        ) ||
                        (
                            'RISK-' +
                            String(
                                index + 1
                            ).padStart(
                                3,
                                '0'
                            )
                        ),

                    sequence:
                        index + 1,

                    category:
                        normalize(
                            item.category
                        ) ||
                        null,

                    risk:
                        normalize(
                            item.risk
                        ) ||
                        null,

                    cause:
                        normalize(
                            item.cause
                        ) ||
                        null,

                    consequence:
                        normalize(
                            item.consequence
                        ) ||
                        null,

                    probability,

                    impact,

                    criticality:
                        calculateCriticality(
                            probability,
                            impact,
                            scoring
                        ),

                    owner:
                        normalize(
                            item.owner
                        ) ||
                        null,

                    response:
                        normalize(
                            item.response
                        ) ||
                        null,

                    dueDate:
                        normalize(
                            item.dueDate
                        ) ||
                        null,

                    status:
                        normalize(
                            item.status
                        ) ||
                        null,

                    source:
                        'user-provided'
                };

                row.missingFields =
                    missingFieldsForRow(
                        row
                    );

                row.rowStatus =
                    row.missingFields
                        .length
                        ? 'TO_BE_ENRICHED'
                        : 'STRUCTURED';

                return row;
            }

            function buildRows(
                items,
                scoring
            ) {
                return (
                    items || []
                )
                    .filter(
                        function (item) {
                            return (
                                item &&
                                typeof item ===
                                    'object' &&
                                normalize(
                                    item.risk
                                )
                            );
                        }
                    )
                    .map(
                        function (
                            item,
                            index
                        ) {
                            return buildRiskRow(
                                item,
                                index,
                                scoring
                            );
                        }
                    );
            }

            /*
            * ------------------------------------------------------------
            * BUILD
            * ------------------------------------------------------------
            */

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

                if (
                    !validation.valid
                ) {
                    return {
                        builderVersion:
                            BUILDER_VERSION,

                        artifactType:
                            'riskRegister',

                        status:
                            'INCOMPLETE',

                        ready:
                            false,

                        validation,

                        artifact:
                            null
                    };
                }

                const scope =
                    normalize(
                        answers.riskScope
                    );

                const categories =
                    splitBusinessList(
                        answers.riskCategories
                    );

                const scoring =
                    parseScoring(
                        answers.riskScoring
                    );

                const ownerStatements =
                    splitBusinessList(
                        answers.riskOwners
                    );

                const sourceRiskItems =
                    riskItemsFrom(
                        answers
                    );

                const rows =
                    buildRows(
                        sourceRiskItems,
                        scoring
                    );

                if (
                    !rows.length
                ) {
                    return {
                        builderVersion:
                            BUILDER_VERSION,

                        artifactType:
                            'riskRegister',

                        status:
                            'INCOMPLETE',

                        ready:
                            false,

                        validation:
                            Object.assign(
                                {},
                                validation,
                                {
                                    valid:
                                        false,
                                    missing:
                                        [
                                            {
                                                key:
                                                    'riskItems',
                                                label:
                                                    'Risques identifiés'
                                            }
                                        ]
                                }
                            ),

                        artifact:
                            null
                    };
                }

                const artifact = {
                    id:
                        safeInput.id ||
                        null,

                    type:
                        'riskRegister',

                    title:
                        'Risk Register',

                    status:
                        'draft',

                    revision:
                        safeInput.revision ||
                        'DRAFT-01',

                    context: {
                        initialRequest:
                            normalize(
                                safeInput.initialText
                            ),

                        project:
                            normalize(
                                safeInput.project
                            ) ||
                            null,

                        ouvrage:
                            normalize(
                                safeInput.ouvrage
                            ) ||
                            null,

                        zone:
                            normalize(
                                safeInput.zone
                            ) ||
                            null,

                        scope
                    },

                    categories,

                    scoring,

                    owners: {
                        statements:
                            ownerStatements
                    },

                    rows,

                    traceability: {
                        sources: [
                            createSourceTrace(
                                'riskScope',
                                answers.riskScope
                            ),

                            createSourceTrace(
                                'riskCategories',
                                answers.riskCategories
                            ),

                            createSourceTrace(
                                'riskScoring',
                                answers.riskScoring
                            ),

                            createSourceTrace(
                                'riskOwners',
                                answers.riskOwners
                            ),

                            createSourceTrace(
                                'riskItems',
                                sourceRiskItems
                            )
                        ],

                        generatedFacts:
                            [],

                        inventedRisks:
                            false,

                        inventedScores:
                            false
                    },

                    enrichment: {
                        required: [
                            {
                                id:
                                    'RISK_WBS_LINKAGE',
                                label:
                                    'Rattachement WBS / activité',
                                status:
                                    'TO_BE_DEFINED'
                            },
                            {
                                id:
                                    'RISK_RESIDUAL_ASSESSMENT',
                                label:
                                    'Évaluation du risque résiduel',
                                status:
                                    'TO_BE_DEFINED'
                            },
                            {
                                id:
                                    'RISK_MONITORING',
                                label:
                                    'Suivi et revue périodique',
                                status:
                                    'TO_BE_DEFINED'
                            }
                        ]
                    }
                };

                return {
                    builderVersion:
                        BUILDER_VERSION,

                    artifactType:
                        'riskRegister',

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
                parseScoring,
                build
            };
        }
    );