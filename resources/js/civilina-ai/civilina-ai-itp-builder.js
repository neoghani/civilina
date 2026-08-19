(function (root, factory) {
    'use strict';

    const isCommonJs =
        typeof module === 'object' &&
        module.exports;

    const api = factory();

    if (isCommonJs) {
        module.exports = api;
    } else {
        root.CivilinaAIITPBuilder = api;
    }

})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        /*
         * CIVILINA ITP Builder
         * Sprint 2.5
         *
         * Purpose:
         * Transform a validated ITP artifact context into a deterministic,
         * structured Inspection & Test Plan draft.
         *
         * This module:
         * - does NOT invent technical requirements;
         * - does NOT invent acceptance criteria;
         * - does NOT invent standards or references;
         * - preserves user-provided information;
         * - identifies information that still requires enrichment;
         * - produces a stable DTO that can later be rendered in the UI,
         *   stored in Civilina Core, exported, or enriched by an LLM.
         */

        const BUILDER_VERSION = '1.0.0';

        const REQUIRED_FIELDS = Object.freeze([
            {
                key: 'itpActivityScope',
                label: 'Périmètre de l’activité'
            },
            {
                key: 'itpReferences',
                label: 'Références applicables'
            },
            {
                key: 'itpInspectionsTests',
                label: 'Inspections et essais'
            },
            {
                key: 'itpAcceptanceCriteria',
                label: 'Critères d’acceptation'
            },
            {
                key: 'itpHoldWitness',
                label: 'Hold / Witness Points'
            },
            {
                key: 'itpResponsibilities',
                label: 'Responsabilités'
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
function activityScopeFromInitialText(value) {
    const text = normalize(value);

    if (!text) {
        return '';
    }

    const match = text.match(
        /\b(?:pour|sur|concernant)\s+(?:le|la|les|l['’])?\s*(.+?)[.!?]*$/i
    );

    return match
        ? normalize(match[1])
        : '';
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
            const normalized =
                normalize(value);

            if (!normalized) {
                return [];
            }

            /*
             * Deterministic splitting only.
             * We deliberately do not try to semantically interpret
             * technical statements here.
             */
            return unique(
                normalized
                    .split(
                        /\s*(?:;|\n|\r|\.\s+)\s*/
                    )
                    .map(function (item) {
                        return item
                            .replace(
                                /^[\-\u2022]\s*/,
                                ''
                            )
                            .trim();
                    })
            );
        }

        function hasValue(
            answers,
            key
        ) {
            return Boolean(
                normalize(
                    answers &&
                    answers[key]
                )
            );
        }

        function validate(
            input
        ) {
            const safeInput =
                input &&
                typeof input === 'object'
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
        .filter(function (field) {
            if (
                field.key === 'itpActivityScope' &&
                (
                    hasValue(
                        answers,
                        field.key
                    ) ||
                    activityScopeFromInitialText(
                        safeInput.initialText
                    )
                )
            ) {
                return false;
            }

            return !hasValue(
                answers,
                field.key
            );
        })

            return {
                valid:
                    missing.length === 0,

                required:
                    REQUIRED_FIELDS.length,

                completed:
                    REQUIRED_FIELDS.length -
                    missing.length,

                completion:
                    Math.round(
                        (
                            (
                                REQUIRED_FIELDS.length -
                                missing.length
                            ) /
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
                value:
                    normalize(value)
            };
        }

        function build(
            input
        ) {
            const safeInput =
                input &&
                typeof input === 'object'
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
                    builderVersion:
                        BUILDER_VERSION,

                    artifactType:
                        'itp',

                    status:
                        'INCOMPLETE',

                    ready:
                        false,

                    validation,

                    artifact:
                        null
                };
            }

            const activityScope =
    normalize(
        answers.itpActivityScope
    ) ||
    activityScopeFromInitialText(
        safeInput.initialText
    );

            const references =
                splitBusinessList(
                    answers.itpReferences
                );

            const inspectionsTests =
                splitBusinessList(
                    answers.itpInspectionsTests
                );

            const acceptanceCriteria =
                splitBusinessList(
                    answers.itpAcceptanceCriteria
                );

            const holdWitnessPoints =
                splitBusinessList(
                    answers.itpHoldWitness
                );

            const responsibilities =
                splitBusinessList(
                    answers.itpResponsibilities
                );

            const artifact = {
                id:
                    safeInput.id ||
                    null,

                type:
                    'itp',

                title:
                    'Inspection & Test Plan',

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

                    activity:
                        activityScope
                },

                scope: {
                    activity:
                        activityScope,

                    statement:
                        activityScope
                },

                references,

                inspectionPlan: {
                    inspectionsAndTests:
                        inspectionsTests,

                    acceptanceCriteria,

                    checkpoints:
                        holdWitnessPoints,

                    responsibilities
                },

                controlPoints: {
                    holdWitness:
                        holdWitnessPoints,

                    /*
                     * We do not infer HP / WP classification
                     * automatically yet because the user may have
                     * provided mixed sentences.
                     */
                    structuredClassification:
                        []
                },

                responsibilities: {
                    statements:
                        responsibilities,

                    /*
                     * Future Builder enrichment may transform
                     * these statements into explicit roles:
                     * execute / inspect / approve / accept.
                     */
                    roleMatrix:
                        []
                },

                records: {
                    requiredRecords:
                        [],

                    status:
                        'TO_BE_DEFINED'
                },

                traceability: {
                    sources: [
                        createSourceTrace(
                            'itpActivityScope',
                            answers.itpActivityScope
                        ),
                        createSourceTrace(
                            'itpReferences',
                            answers.itpReferences
                        ),
                        createSourceTrace(
                            'itpInspectionsTests',
                            answers.itpInspectionsTests
                        ),
                        createSourceTrace(
                            'itpAcceptanceCriteria',
                            answers.itpAcceptanceCriteria
                        ),
                        createSourceTrace(
                            'itpHoldWitness',
                            answers.itpHoldWitness
                        ),
                        createSourceTrace(
                            'itpResponsibilities',
                            answers.itpResponsibilities
                        )
                    ],

                    generatedFacts:
                        [],

                    inventedTechnicalRequirements:
                        false
                },

                enrichment: {
                    required: [
                        {
                            id:
                                'ITP_ROWS_STRUCTURING',
                            label:
                                'Associer chaque inspection ou essai à son critère d’acceptation.'
                        },
                        {
                            id:
                                'ITP_HP_WP_CLASSIFICATION',
                            label:
                                'Structurer individuellement les Hold Points, Witness Points et points de surveillance.'
                        },
                        {
                            id:
                                'ITP_RESPONSIBILITY_MATRIX',
                            label:
                                'Associer les rôles Exécute / Inspecte / Approuve / Accepte à chaque contrôle.'
                        },
                        {
                            id:
                                'ITP_RECORDS',
                            label:
                                'Définir les enregistrements et preuves qualité à conserver.'
                        }
                    ],

                    optional: [
                        {
                            id:
                                'ITP_FREQUENCY',
                            label:
                                'Fréquence des inspections et essais.'
                        },
                        {
                            id:
                                'ITP_METHOD',
                            label:
                                'Méthode ou procédure de contrôle.'
                        },
                        {
                            id:
                                'ITP_RESPONSIBLE_PARTY',
                            label:
                                'Partie responsable par ligne de contrôle.'
                        }
                    ]
                }
            };

            return {
                builderVersion:
                    BUILDER_VERSION,

                artifactType:
                    'itp',

                status:
                    'DRAFT_READY',

                ready:
                    true,

                validation,

                artifact
            };
        }

        function canBuild(
            input
        ) {
            return validate(
                input
            ).valid;
        }

        return {
            BUILDER_VERSION,
            REQUIRED_FIELDS,
            validate,
            canBuild,
            build
        };
    }
);