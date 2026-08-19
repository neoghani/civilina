(function (root, factory) {
    'use strict';

    const isCommonJs = typeof module === 'object' && module.exports;
    const api = factory();

    if (isCommonJs) module.exports = api;
    else root.CivilinaAIITPBuilder = api;
})(
    typeof globalThis !== 'undefined' ? globalThis : this,
    function () {
        'use strict';

        /*
         * CIVILINA ITP Builder
         * Sprint 2.6 — Structured Rows
         *
         * Guardrails:
         * - no invented technical requirement;
         * - no invented acceptance criterion;
         * - no invented standard or reference;
         * - no invented method, frequency or quality record;
         * - preserve and trace user-provided information;
         * - identify missing enrichment explicitly.
         */

        const BUILDER_VERSION = '1.2.0';

        const REQUIRED_FIELDS = Object.freeze([
            { key: 'itpActivityScope', label: 'Périmètre de l’activité' },
            { key: 'itpReferences', label: 'Références applicables' },
            { key: 'itpInspectionsTests', label: 'Inspections et essais' },
            { key: 'itpAcceptanceCriteria', label: 'Critères d’acceptation' },
            { key: 'itpHoldWitness', label: 'Hold / Witness Points' },
            { key: 'itpResponsibilities', label: 'Responsabilités' }
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
                .replace(/[\u0300-\u036f]/g, '')
                .replace(/[’']/g, ' ')
                .toLowerCase();
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
            const text = normalize(value);

            if (!text) {
                return [];
            }

            /*
             * Les virgules sont conservées :
             * elles appartiennent souvent à une même exigence technique.
             */
            return unique(
                text
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

        function splitInspectionItems(value) {
            const normalized = normalize(value);

            if (!normalized) {
                return [];
            }

            return unique(
                normalized
                    .split(
                        /\s*(?:;|\n|\r|\.\s+|,\s*(?=(?:essai|contrôle|controle|inspection|vérification|verification|prélèvement|prelevement|mesure|test)\b)|\s+et\s+(?=(?:essai|contrôle|controle|inspection|vérification|verification|prélèvement|prelevement|mesure|test)\b))\s*/i
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

        function hasExplicitListStructure(value) {
            return /[;\n\r]/.test(
                String(
                    value === undefined ||
                    value === null
                        ? ''
                        : value
                )
            );
        }

        function hasAcceptanceSignal(value) {
            const text =
                normalizeForMatch(value);

            if (!text) {
                return false;
            }

            return /\b(?:conforme?s?|admissible?s?|acceptable?s?|respecte?s?|respectee?s?|respectees?|tolerance?s?|absence|aucun|aucune|sans|minimum|maximum|minimale?s?|maximale?s?|inferieur(?:e|es|s)?|superieur(?:e|es|s)?|compris(?:e|es)?|plage)\b/i.test(
                text
            );
        }

        function splitAcceptanceCriteriaItems(value) {
            const raw = String(
                value === undefined ||
                value === null
                    ? ''
                    : value
            ).trim();

            if (!raw) {
                return [];
            }

            const strongItems =
                raw
                    .split(
                        /\s*(?:;|\n|\r|\.\s+)\s*/
                    )
                    .map(function (item) {
                        return normalize(
                            item
                                .replace(
                                    /^[\-\u2022]\s*/,
                                    ''
                                )
                        );
                    })
                    .filter(Boolean);

            const commaSafeItems = [];

            strongItems.forEach(
                function (item) {
                    const parts =
                        item.split(
                            /\s*,\s*/
                        );

                    if (parts.length <= 1) {
                        commaSafeItems.push(
                            item
                        );
                        return;
                    }

                    let current =
                        normalize(parts[0]);

                    for (
                        let i = 1;
                        i < parts.length;
                        i += 1
                    ) {
                        const next =
                            normalize(parts[i]);

                        /*
                         * A comma becomes a criterion separator only
                         * when both sides already look like complete
                         * acceptance statements. This preserves compound
                         * lists such as "coffrage, ferraillage, réservations
                         * et propreté conformes".
                         */
                        if (
                            hasAcceptanceSignal(
                                current
                            ) &&
                            hasAcceptanceSignal(
                                next
                            )
                        ) {
                            commaSafeItems.push(
                                current
                            );
                            current = next;
                        } else {
                            current =
                                normalize(
                                    current +
                                    ', ' +
                                    next
                                );
                        }
                    }

                    if (current) {
                        commaSafeItems.push(
                            current
                        );
                    }
                }
            );

            const conjunctionSafeItems = [];

            commaSafeItems.forEach(
                function (item) {
                    const parts =
                        item.split(
                            /\s+et\s+/i
                        );

                    if (
                        parts.length === 2 &&
                        hasAcceptanceSignal(
                            parts[0]
                        ) &&
                        hasAcceptanceSignal(
                            parts[1]
                        )
                    ) {
                        conjunctionSafeItems.push(
                            normalize(parts[0]),
                            normalize(parts[1])
                        );
                        return;
                    }

                    conjunctionSafeItems.push(
                        item
                    );
                }
            );

            return unique(
                conjunctionSafeItems
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
                typeof safeInput.answers === 'object'
                    ? safeInput.answers
                    : {};

            const missing =
                REQUIRED_FIELDS
                    .filter(function (field) {
                        if (
                            field.key ===
                                'itpActivityScope' &&
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
                    .map(function (field) {
                        return {
                            key:
                                field.key,
                            label:
                                field.label
                        };
                    });

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
         * HOLD / WITNESS / SURVEILLANCE
         * ------------------------------------------------------------
         */

        function classifyCheckpoint(statement) {
            const text =
                normalizeForMatch(
                    statement
                );

            if (
                /\bhold\s*point\b/.test(
                    text
                ) ||
                /\bpoint\s+d\s+arret\b/.test(
                    text
                ) ||
                /\bhp\b/.test(
                    text
                )
            ) {
                return 'HOLD';
            }

            if (
                /\bwitness\s*point\b/.test(
                    text
                ) ||
                /\bpoint\s+temoin\b/.test(
                    text
                ) ||
                /\bwp\b/.test(
                    text
                )
            ) {
                return 'WITNESS';
            }

            if (
                /\bsurveillance\b/.test(
                    text
                ) ||
                /\bmonitoring\b/.test(
                    text
                )
            ) {
                return 'SURVEILLANCE';
            }

            return 'UNCLASSIFIED';
        }

        function structureCheckpoints(
            statements
        ) {
            return (
                statements || []
            ).map(
                function (
                    statement,
                    index
                ) {
                    return {
                        id:
                            'CP-' +
                            String(
                                index + 1
                            ).padStart(
                                3,
                                '0'
                            ),

                        type:
                            classifyCheckpoint(
                                statement
                            ),

                        statement:
                            normalize(
                                statement
                            ),

                        source:
                            'user-provided'
                    };
                }
            );
        }

        /*
         * ------------------------------------------------------------
         * RESPONSIBILITIES
         * ------------------------------------------------------------
         */

        function extractActor(
            statement,
            pattern
        ) {
            const text =
                normalize(statement);

            const match =
                pattern.exec(text);

            if (!match) {
                return null;
            }

            const actor =
                normalize(
                    text.slice(
                        0,
                        match.index
                    )
                );

            return actor ||
                null;
        }

        function structureResponsibilities(
            statements
        ) {
            const matrix = {
                execute:
                    null,

                inspect:
                    null,

                approve:
                    null,

                accept:
                    null
            };

            const patterns = {
                execute:
                    /\b(?:exécute|execute|réalise|realise)\b/i,

                inspect:
                    /\b(?:inspecte|contrôle|controle|vérifie|verifie)\b/i,

                approve:
                    /\b(?:approuve|valide)\b/i,

                accept:
                    /\b(?:accepte|réceptionne|receptionne)\b/i
            };

            (
                statements || []
            ).forEach(
                function (statement) {
                    Object.keys(
                        patterns
                    ).forEach(
                        function (role) {
                            if (
                                matrix[
                                    role
                                ]
                            ) {
                                return;
                            }

                            const actor =
                                extractActor(
                                    statement,
                                    patterns[
                                        role
                                    ]
                                );

                            if (actor) {
                                matrix[
                                    role
                                ] =
                                    actor;
                            }
                        }
                    );
                }
            );

            return matrix;
        }

        /*
         * ------------------------------------------------------------
         * CHECKPOINT ↔ INSPECTION ASSOCIATION
         * ------------------------------------------------------------
         */

        const ASSOCIATION_STOP_WORDS =
            new Set([
                'avant',
                'apres',
                'avec',
                'dans',
                'pour',
                'sans',
                'sous',
                'sur',
                'entre',
                'controle',
                'essai',
                'point',
                'validation',
                'conforme',
                'conformes',
                'specification',
                'inspection',
                'test',
                'tests',
                'hold',
                'witness',
                'et',
                'ou',
                'du',
                'de',
                'des',
                'le',
                'la',
                'les',
                'un',
                'une',
                'au',
                'aux'
            ]);

        function associationTokens(
            value
        ) {
            return unique(
                normalizeForMatch(
                    value
                )
                    .replace(
                        /[^a-z0-9]+/g,
                        ' '
                    )
                    .split(/\s+/)
                    .filter(
                        function (
                            token
                        ) {
                            return (
                                token.length >=
                                    4 &&
                                !ASSOCIATION_STOP_WORDS
                                    .has(
                                        token
                                    )
                            );
                        }
                    )
            );
        }

        function associationScore(
            left,
            right
        ) {
            const leftTokens =
                associationTokens(
                    left
                );

            const rightTokens =
                new Set(
                    associationTokens(
                        right
                    )
                );

            return leftTokens.reduce(
                function (
                    score,
                    token
                ) {
                    return (
                        score +
                        (
                            rightTokens.has(
                                token
                            )
                                ? 1
                                : 0
                        )
                    );
                },
                0
            );
        }

        function criterionForInspection(
            inspection,
            acceptanceCriteria,
            index,
            allowPositionalAssociation
        ) {
            const criteria =
                acceptanceCriteria || [];

            if (
                allowPositionalAssociation &&
                criteria[index]
            ) {
                return {
                    value:
                        normalize(
                            criteria[index]
                        ) ||
                        null,

                    association:
                        'POSITIONAL_EXPLICIT'
                };
            }

            let best = null;
            let bestScore = 0;
            let bestCount = 0;

            criteria.forEach(
                function (criterion) {
                    const score =
                        associationScore(
                            inspection,
                            criterion
                        );

                    if (score > bestScore) {
                        best = criterion;
                        bestScore = score;
                        bestCount = 1;
                        return;
                    }

                    if (
                        score > 0 &&
                        score === bestScore
                    ) {
                        bestCount += 1;
                    }
                }
            );

            /*
             * A lexical association is accepted only when there is
             * a positive and unique best match. Ambiguous or merely
             * plausible relationships remain explicitly undefined.
             */
            if (
                bestScore > 0 &&
                bestCount === 1
            ) {
                return {
                    value:
                        normalize(best),

                    association:
                        'LEXICAL_SAFE'
                };
            }

            return {
                value: null,
                association: null
            };
        }

        function checkpointForInspection(
            inspection,
            checkpoints
        ) {
            let best =
                null;

            let bestScore =
                0;

            (
                checkpoints || []
            ).forEach(
                function (
                    checkpoint
                ) {
                    const score =
                        associationScore(
                            inspection,
                            checkpoint
                                .statement
                        );

                    if (
                        score >
                        bestScore
                    ) {
                        best =
                            checkpoint;

                        bestScore =
                            score;
                    }
                }
            );

            return bestScore >
                0
                ? best
                : null;
        }

        /*
         * ------------------------------------------------------------
         * ROWS
         * ------------------------------------------------------------
         */

        function missingFieldsForRow(
            row
        ) {
            const missing =
                [];

            [
                'method',
                'frequency',
                'acceptanceCriteria',
                'controlPoint',
                'execute',
                'inspect',
                'approve',
                'accept',
                'record'
            ].forEach(
                function (
                    field
                ) {
                    if (
                        row[field] ===
                            null ||
                        row[field] ===
                            ''
                    ) {
                        missing.push(
                            field
                        );
                    }
                }
            );

            return missing;
        }

        function buildRows(
            inspectionsTests,
            acceptanceCriteria,
            checkpoints,
            responsibilityMatrix,
            options
        ) {
            const safeOptions =
                options &&
                typeof options === 'object'
                    ? options
                    : {};
            return (
                inspectionsTests || []
            ).map(
                function (
                    inspection,
                    index
                ) {
                    const checkpoint =
                        checkpointForInspection(
                            inspection,
                            checkpoints
                        );

                    const criterion =
                        criterionForInspection(
                            inspection,
                            acceptanceCriteria,
                            index,
                            Boolean(
                                safeOptions
                                    .allowPositionalCriteria
                            )
                        );

                    const row = {
                        code:
                            'ITP-' +
                            String(
                                index + 1
                            ).padStart(
                                3,
                                '0'
                            ),

                        sequence:
                            index + 1,

                        inspectionTest:
                            normalize(
                                inspection
                            ),

                        /*
                         * Non fournis actuellement.
                         * Civilina ne les invente pas.
                         */
                        method:
                            null,

                        frequency:
                            null,

                        /*
                         * Sprint 2.6 safety rule:
                         * - positional association only when the user
                         *   explicitly structured both lists;
                         * - otherwise only a unique lexical match;
                         * - ambiguous relationships stay null.
                         */
                        acceptanceCriteria:
                            criterion.value,

                        acceptanceCriteriaAssociation:
                            criterion.association,

                        controlPoint:
                            checkpoint
                                ? checkpoint
                                    .type
                                : null,

                        controlPointStatement:
                            checkpoint
                                ? checkpoint
                                    .statement
                                : null,

                        /*
                         * Les responsabilités actuellement collectées
                         * sont globales à l’ITP.
                         */
                        execute:
                            responsibilityMatrix
                                .execute ||
                            null,

                        inspect:
                            responsibilityMatrix
                                .inspect ||
                            null,

                        approve:
                            responsibilityMatrix
                                .approve ||
                            null,

                        accept:
                            responsibilityMatrix
                                .accept ||
                            null,

                        record:
                            null,

                        source:
                            'user-provided'
                    };

                    row.missingFields =
                        missingFieldsForRow(
                            row
                        );

                    row.status =
                        row.missingFields
                            .length
                            ? 'TO_BE_ENRICHED'
                            : 'STRUCTURED';

                    return row;
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

            if (
                !validation.valid
            ) {
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
                    answers
                        .itpActivityScope
                ) ||
                activityScopeFromInitialText(
                    safeInput.initialText
                );

            const references =
                splitBusinessList(
                    answers
                        .itpReferences
                );

            const inspectionsTests =
    splitInspectionItems(
        answers.itpInspectionsTests
    );

            const acceptanceCriteria =
                splitAcceptanceCriteriaItems(
                    answers
                        .itpAcceptanceCriteria
                );

            const allowPositionalCriteria =
                hasExplicitListStructure(
                    answers
                        .itpInspectionsTests
                ) &&
                hasExplicitListStructure(
                    answers
                        .itpAcceptanceCriteria
                ) &&
                inspectionsTests.length ===
                    acceptanceCriteria.length;

            const holdWitnessPoints =
                splitBusinessList(
                    answers
                        .itpHoldWitness
                );

            const responsibilities =
                splitBusinessList(
                    answers
                        .itpResponsibilities
                );

            const structuredCheckpoints =
                structureCheckpoints(
                    holdWitnessPoints
                );

            const responsibilityMatrix =
                structureResponsibilities(
                    responsibilities
                );

            const rows =
                buildRows(
                    inspectionsTests,
                    acceptanceCriteria,
                    structuredCheckpoints,
                    responsibilityMatrix,
                    {
                        allowPositionalCriteria
                    }
                );

            const associatedCriteria =
                new Set(
                    rows
                        .map(function (row) {
                            return row
                                .acceptanceCriteria;
                        })
                        .filter(Boolean)
                );

            const unassociatedAcceptanceCriteria =
                acceptanceCriteria.filter(
                    function (criterion) {
                        return !associatedCriteria
                            .has(criterion);
                    }
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
                            safeInput
                                .initialText
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

                /*
                 * Sprint 2.6 contract.
                 */
                rows,

                inspectionPlan: {
                    inspectionsAndTests:
                        inspectionsTests,

                    acceptanceCriteria,

                    unassociatedAcceptanceCriteria,

                    acceptanceCriteriaAssociationMode:
                        allowPositionalCriteria
                            ? 'POSITIONAL_EXPLICIT'
                            : 'LEXICAL_SAFE',

                    checkpoints:
                        holdWitnessPoints,

                    responsibilities
                },

                controlPoints: {
                    holdWitness:
                        holdWitnessPoints,

                    structuredClassification:
                        structuredCheckpoints
                },

                responsibilities: {
                    statements:
                        responsibilities,

                    roleMatrix:
                        responsibilityMatrix
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
                            answers
                                .itpActivityScope ||
                            activityScope
                        ),

                        createSourceTrace(
                            'itpReferences',
                            answers
                                .itpReferences
                        ),

                        createSourceTrace(
                            'itpInspectionsTests',
                            answers
                                .itpInspectionsTests
                        ),

                        createSourceTrace(
                            'itpAcceptanceCriteria',
                            answers
                                .itpAcceptanceCriteria
                        ),

                        createSourceTrace(
                            'itpHoldWitness',
                            answers
                                .itpHoldWitness
                        ),

                        createSourceTrace(
                            'itpResponsibilities',
                            answers
                                .itpResponsibilities
                        )
                    ],

                    generatedFacts:
                        [],

                    inventedTechnicalRequirements:
                        false
                },

                enrichment: {
                    /*
                     * Ces IDs sont conservés pour compatibilité
                     * avec le contrat Sprint 2.5.
                     *
                     * Les trois premières structures existent
                     * désormais en brouillon et doivent être validées.
                     */
                    required: [
                        {
                            id:
                                'ITP_ROWS_STRUCTURING',

                            label:
                                'Vérifier l’association inspection / essai ↔ critère d’acceptation.',

                            status:
                                'STRUCTURED_DRAFT'
                        },

                        {
                            id:
                                'ITP_HP_WP_CLASSIFICATION',

                            label:
                                'Valider la classification des Hold Points, Witness Points et points de surveillance.',

                            status:
                                'STRUCTURED_DRAFT'
                        },

                        {
                            id:
                                'ITP_RESPONSIBILITY_MATRIX',

                            label:
                                'Valider les rôles Exécute / Inspecte / Approuve / Accepte.',

                            status:
                                'STRUCTURED_DRAFT'
                        },

                        {
                            id:
                                'ITP_RECORDS',

                            label:
                                'Définir les enregistrements et preuves qualité à conserver.',

                            status:
                                'TO_BE_DEFINED'
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