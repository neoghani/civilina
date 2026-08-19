(function (root, factory) {
    'use strict';

    const isCommonJs =
        typeof module === 'object' &&
        module.exports;

    const api = factory();

    if (isCommonJs) {
        module.exports = api;
    } else {
        root.CivilinaAIWBSBuilder = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function () {
        'use strict';

        /*
         * CIVILINA WBS Builder
         * Sprint 2.7 — v1
         *
         * Purpose:
         * Build a deterministic WBS draft from user-confirmed
         * project context.
         *
         * Guardrails:
         * - does not invent WBS nodes;
         * - does not invent Work Packages;
         * - keeps Zone / Ouvrage separate from WBS hierarchy;
         * - preserves codification rules as provided;
         * - exposes Project Controls integration;
         * - preserves source traceability;
         * - identifies missing structure explicitly.
         */

        const BUILDER_VERSION = '1.1.0';

        const REQUIRED_FIELDS = Object.freeze([
            {
                key:
                    'wbsScopeBasis',

                label:
                    'Périmètre WBS'
            },
            {
                key:
                    'wbsDecomposition',

                label:
                    'Logique de décomposition'
            },
            {
                key:
                    'wbsZoneOuvrage',

                label:
                    'Zones / ouvrages'
            },
            {
                key:
                    'wbsCodification',

                label:
                    'Codification'
            },
            {
                key:
                    'wbsIntegration',

                label:
                    'Intégration Project Controls'
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

        function validate(input) {
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
                    .filter(
                        function (field) {
                            return !hasValue(
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

                value:
                    normalize(value)
            };
        }

        /*
         * ------------------------------------------------------------
         * PROJECT CONTROLS INTEGRATION
         * ------------------------------------------------------------
         */

        function detectIntegration(
            statement
        ) {
            const text =
                normalizeForMatch(
                    statement
                );

            return {
                planning:
                    /\b(?:planning|schedule|scheduling|delai|delais)\b/
                        .test(text),

                cost:
                    /\b(?:cout|couts|cost|costs|budget)\b/
                        .test(text),

                progress:
                    /\b(?:progres|progress|avancement)\b/
                        .test(text),

                documents:
                    /\b(?:document|documents|documentaire|documentation)\b/
                        .test(text),

                statement:
                    normalize(
                        statement
                    )
            };
        }

        /*
         * ------------------------------------------------------------
         * DECOMPOSITION
         * ------------------------------------------------------------
         */

        function detectDecompositionSignals(
            statement
        ) {
            const text =
                normalizeForMatch(
                    statement
                );

            const signals = [];

            const definitions = [
                {
                    id:
                        'deliverable',

                    pattern:
                        /\b(?:livrable|livrables|deliverable|deliverables)\b/
                },
                {
                    id:
                        'ouvrage',

                    pattern:
                        /\b(?:ouvrage|ouvrages)\b/
                },
                {
                    id:
                        'zone',

                    pattern:
                        /\b(?:zone|zones)\b/
                },
                {
                    id:
                        'discipline',

                    pattern:
                        /\b(?:discipline|disciplines)\b/
                },
                {
                    id:
                        'phase',

                    pattern:
                        /\b(?:phase|phases)\b/
                },
                {
                    id:
                        'lot',

                    pattern:
                        /\b(?:lot|lots)\b/
                },
                {
                    id:
                        'work-package',

                    pattern:
                        /\b(?:work\s*package|work\s*packages|package|packages)\b/
                },
                {
                    id:
                        'system',

                    pattern:
                        /\b(?:systeme|systemes|system|systems)\b/
                }
            ];

            definitions.forEach(
                function (definition) {
                    if (
                        definition.pattern
                            .test(text)
                    ) {
                        signals.push(
                            definition.id
                        );
                    }
                }
            );

            return signals;
        }

        function partitionDecompositionSignals(
            signals
        ) {
            const contextualIds =
                new Set([
                    'zone',
                    'ouvrage'
                ]);

            const safeSignals =
                Array.isArray(signals)
                    ? signals
                    : [];

            return {
                hierarchyDimensions:
                    unique(
                        safeSignals.filter(
                            function (signal) {
                                return !contextualIds
                                    .has(signal);
                            }
                        )
                    ),

                contextualDimensions:
                    unique(
                        safeSignals.filter(
                            function (signal) {
                                return contextualIds
                                    .has(signal);
                            }
                        )
                    )
            };
        }

        /*
         * ------------------------------------------------------------
         * BUILD
         * ------------------------------------------------------------
         */

        function build(input) {
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
                        'wbs',

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
                    answers.wbsScopeBasis
                );

            const decompositionStatement =
                normalize(
                    answers.wbsDecomposition
                );

            const zonesOuvrages =
                splitBusinessList(
                    answers.wbsZoneOuvrage
                );

            const codificationRule =
                normalize(
                    answers.wbsCodification
                );

            const integration =
                detectIntegration(
                    answers.wbsIntegration
                );

            const decompositionSignals =
                detectDecompositionSignals(
                    decompositionStatement
                );

            const decompositionPartition =
                partitionDecompositionSignals(
                    decompositionSignals
                );

            const hierarchyDimensions =
                decompositionPartition
                    .hierarchyDimensions;

            const contextualDimensions =
                decompositionPartition
                    .contextualDimensions;

            /*
             * IMPORTANT:
             * Zone / Ouvrage may be mentioned in the user's decomposition
             * statement, but Civilina treats them as contextual dimensions
             * to map against WBS elements. They must never silently become
             * WBS hierarchy levels.
             *
             * A declaration such as:
             * "par ouvrages puis disciplines et lots"
             * preserves "ouvrage" as contextual intent while exposing only
             * "discipline" and "lot" as WBS hierarchy signals.
             *
             * We still do not invent actual WBS nodes here.
             */
            const hierarchy = {
                status:
                    'TO_BE_STRUCTURED',

                decompositionStatement,

                declaredDimensions:
                    hierarchyDimensions,

                excludedContextualDimensions:
                    contextualDimensions,

                root:
                    null,

                nodes:
                    [],

                levels:
                    []
            };

            const artifact = {
                id:
                    safeInput.id ||
                    null,

                type:
                    'wbs',

                title:
                    'Work Breakdown Structure',

                status:
                    'draft',

                revision:
                    safeInput.revision ||
                    'DRAFT-01',

                baseline:
                    {
                        id:
                            null,

                        version:
                            null,

                        status:
                            'TO_BE_DEFINED'
                    },

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

                    program:
                        normalize(
                            safeInput.program
                        ) ||
                        null,

                    portfolio:
                        normalize(
                            safeInput.portfolio
                        ) ||
                        null,

                    zonesOuvrages,

                    /*
                     * Zone / Ouvrage is contextual.
                     * It must never silently become the WBS itself.
                     */
                    zoneOuvrageDimension:
                        {
                            status:
                                zonesOuvrages.length
                                    ? 'DECLARED'
                                    : 'TO_BE_DEFINED',

                            values:
                                zonesOuvrages.slice(),

                            separateFromHierarchy:
                                true
                        }
                },

                scope: {
                    statement:
                        scope,

                    source:
                        'user-provided'
                },

                decomposition: {
                    statement:
                        decompositionStatement,

                    declaredDimensions:
                        hierarchyDimensions,

                    contextualDimensions,

                    allDeclaredSignals:
                        decompositionSignals,

                    zoneOuvrageSeparated:
                        true,

                    source:
                        'user-provided'
                },

                hierarchy,

                codification: {
                    rule:
                        codificationRule,

                    source:
                        'user-provided',

                    generatedCodes:
                        []
                },

                integration,

                /*
                 * Work Packages must be explicitly structured
                 * from project evidence in a later step.
                 */
                workPackages:
                    [],

                activities:
                    [],

                controlAccounts:
                    [],

                traceability: {
                    sources: [
                        createSourceTrace(
                            'wbsScopeBasis',
                            answers.wbsScopeBasis
                        ),

                        createSourceTrace(
                            'wbsDecomposition',
                            answers.wbsDecomposition
                        ),

                        createSourceTrace(
                            'wbsZoneOuvrage',
                            answers.wbsZoneOuvrage
                        ),

                        createSourceTrace(
                            'wbsCodification',
                            answers.wbsCodification
                        ),

                        createSourceTrace(
                            'wbsIntegration',
                            answers.wbsIntegration
                        )
                    ],

                    generatedFacts:
                        [],

                    inventedBusinessStructure:
                        false,

                    inventedWorkPackages:
                        false
                },

                enrichment: {
                    required: [
                        {
                            id:
                                'WBS_HIERARCHY_STRUCTURING',

                            label:
                                'Structurer les niveaux et nœuds réels de la WBS à partir du périmètre confirmé.',

                            status:
                                'TO_BE_DEFINED'
                        },

                        {
                            id:
                                'WBS_WORK_PACKAGES',

                            label:
                                'Définir les Work Packages et leurs limites de responsabilité.',

                            status:
                                'TO_BE_DEFINED'
                        },

                        {
                            id:
                                'WBS_BASELINE_VERSION',

                            label:
                                'Définir la baseline, la version et les règles de contrôle des modifications.',

                            status:
                                'TO_BE_DEFINED'
                        },

                        {
                            id:
                                'WBS_ZONE_OUVRAGE_MAPPING',

                            label:
                                'Associer les éléments WBS aux zones et ouvrages sans confondre les deux dimensions.',

                            status:
                                'TO_BE_DEFINED'
                        }
                    ],

                    optional: [
                        {
                            id:
                                'WBS_CONTROL_ACCOUNTS',

                            label:
                                'Définir les Control Accounts si le modèle de Project Controls l’exige.'
                        },

                        {
                            id:
                                'WBS_ACTIVITY_MAPPING',

                            label:
                                'Relier les Work Packages aux activités du planning.'
                        },

                        {
                            id:
                                'WBS_COST_MAPPING',

                            label:
                                'Relier la WBS aux structures de coûts et engagements.'
                        },

                        {
                            id:
                                'WBS_PROGRESS_MAPPING',

                            label:
                                'Définir les règles de mesure du progrès par élément WBS.'
                        },

                        {
                            id:
                                'WBS_DOCUMENT_MAPPING',

                            label:
                                'Relier les documents et livrables aux éléments WBS.'
                        }
                    ]
                }
            };

            return {
                builderVersion:
                    BUILDER_VERSION,

                artifactType:
                    'wbs',

                status:
                    'DRAFT_READY',

                ready:
                    true,

                validation,

                artifact
            };
        }

        function canBuild(input) {
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