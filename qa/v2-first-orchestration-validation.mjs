import { loadCivilina } from './load-civilina-cjs.mjs';

const core = loadCivilina(
    'civilina-ai-reasoning-v2.js'
);

let passed = 0;
let failed = 0;

function assert(condition, message) {
    if (!condition) {
        throw new Error(message);
    }
}

function run(name, verify) {
    try {
        verify();
        passed += 1;
        console.log('PASS ' + name);
    } catch (error) {
        failed += 1;
        console.log('FAIL ' + name);
        console.log('  - ' + error.message);
    }
}

function evaluate(text, answers) {
    return core.evaluate({
        freeText: text,
        answers: answers || {},
        seedFacts: []
    });
}

function v2(result) {
    return result && result.v2
        ? result.v2
        : result;
}

function artifactId(result) {
    const output = v2(result);

    if (
        !output ||
        !output.requestedArtifact
    ) {
        return null;
    }

    return (
        output.requestedArtifact.id ||
        output.requestedArtifact.artifactId ||
        null
    );
}

function interactionMode(result) {
    const output = v2(result);

    return output
        ? output.interactionMode || null
        : null;
}

function readiness(result) {
    const output = v2(result);

    return output &&
        output.artifactReadiness
        ? output.artifactReadiness
        : null;
}

function nextQuestion(result) {
    const output = v2(result);

    if (!output) {
        return null;
    }

    return (
        output.nextBestQuestion ||
        output.nextQuestion ||
        (
            output.artifactReadiness &&
            output.artifactReadiness
                .nextRequirement
        ) ||
        null
    );
}

/*
 * ------------------------------------------------------------
 * 1. ARTIFACT MENTION ≠ CREATE REQUEST
 * ------------------------------------------------------------
 */

run(
    'explain-itp-does-not-trigger-create',
    function () {
        const result =
            evaluate(
                'Explique-moi ce qu’est un ITP.'
            );

        assert(
            interactionMode(result) !==
                'create',
            'Une demande d’explication ne doit pas activer le mode create.'
        );

        assert(
            artifactId(result) ===
                null,
            'Un ITP mentionné pour explication ne doit pas devenir requestedArtifact.'
        );
    }
);

run(
    'contextual-itp-mention-does-not-trigger-create',
    function () {
        const result =
            evaluate(
                'Je travaille actuellement avec un ITP sur mon projet.'
            );

        assert(
            interactionMode(result) !==
                'create',
            'Une mention contextuelle d’ITP ne doit pas activer create.'
        );

        assert(
            artifactId(result) ===
                null,
            'Une mention contextuelle ne doit pas devenir requestedArtifact.'
        );
    }
);

/*
 * ------------------------------------------------------------
 * 2. EXPLICIT CREATE REQUEST
 * ------------------------------------------------------------
 */

run(
    'create-itp-triggers-artifact-create',
    function () {
        const result =
            evaluate(
                'Crée un ITP pour le bétonnage.'
            );

        assert(
            interactionMode(result) ===
                'create',
            'Une demande explicite "Crée" doit activer create.'
        );

        assert(
            artifactId(result) ===
                'itp',
            'ITP attendu comme requestedArtifact.'
        );

        const artifactReadiness =
            readiness(result);

        assert(
            artifactReadiness &&
            artifactReadiness.applicable ===
                true,
            'Artifact readiness attendu pour une création ITP.'
        );
    }
);

run(
    'create-wbs-triggers-artifact-create',
    function () {
        const result =
            evaluate(
                'Construis une WBS pour mon projet.'
            );

        assert(
            interactionMode(result) ===
                'create',
            'Une demande explicite de construction doit activer create.'
        );

        assert(
            artifactId(result) ===
                'wbs',
            'WBS attendu comme requestedArtifact.'
        );
    }
);

/*
 * ------------------------------------------------------------
 * 3. MULTI-ARTIFACT AMBIGUITY
 * ------------------------------------------------------------
 */

run(
    'multiple-artifacts-require-clarification',
    function () {
        const result =
            evaluate(
                'Crée un ITP et une WBS pour mon projet.'
            );

        const output =
            v2(result);

        const artifact =
            artifactId(result);

        const question =
            nextQuestion(result);

        const ambiguity =
            output &&
            (
                output.artifactAmbiguity ===
                    true ||
                output.requiresClarification ===
                    true ||
                (
                    output.ambiguity &&
                    (
                        output.ambiguity
                            .artifact === true ||
                        output.ambiguity
                            .multipleArtifacts === true
                    )
                )
            );

        assert(
            artifact === null ||
            ambiguity ||
            Boolean(question),
            'Une demande simultanée ITP + WBS doit provoquer une clarification, pas un choix silencieux.'
        );
    }
);

/*
 * ------------------------------------------------------------
 * 4. ACTION ORCHESTRATION
 * ------------------------------------------------------------
 */

run(
    'digitalize-process-is-explicit-action',
    function () {
        const result =
            evaluate(
                'Digitalise notre processus de validation qualité.'
            );

        const output =
            v2(result);

        assert(
            Boolean(
                output &&
                (
                    output.interactionMode ===
                        'create' ||
                    output.interactionMode ===
                        'action' ||
                    output.actionIntent ||
                    output.requestedAction
                )
            ),
            '"Digitalise notre processus" doit être reconnu comme une action explicite.'
        );
    }
);

/*
 * ------------------------------------------------------------
 * 5. ARTIFACT READINESS ↔ ANSWERABILITY
 * ------------------------------------------------------------
 */

run(
    'incomplete-artifact-requires-question',
    function () {
        const result =
            evaluate(
                'Crée un ITP.'
            );

        const artifactReadiness =
            readiness(result);

        assert(
            artifactReadiness &&
            artifactReadiness.ready ===
                false,
            'Un ITP sans contexte suffisant ne doit pas être prêt.'
        );

        assert(
            Boolean(
                artifactReadiness
                    .nextRequirement ||
                nextQuestion(result)
            ),
            'Une question suivante doit être fournie pour un livrable incomplet.'
        );
    }
);

run(
    'ready-itp-has-no-blocking-requirement',
    function () {
        const result =
            evaluate(
                'Crée un ITP pour le bétonnage.',
                {
                    itpReferences:
                        'Plan IFC C-204; spécification béton C30/37',

                    itpInspectionsTests:
                        'Contrôle du coffrage; essai d’affaissement',

                    itpAcceptanceCriteria:
                        'Coffrage conforme; affaissement conforme',

                    itpHoldWitness:
                        'Hold Point avant bétonnage',

                    itpResponsibilities:
                        'Entreprise exécute; qualité inspecte; QA/QC approuve; client accepte'
                }
            );

        const artifactReadiness =
            readiness(result);

        assert(
            artifactReadiness,
            'Artifact readiness attendu.'
        );

        assert(
            artifactReadiness.ready ===
                true,
            'L’ITP complet devrait être prêt.'
        );

        assert(
            !artifactReadiness
                .nextRequirement,
            'Aucune exigence bloquante suivante attendue lorsque le livrable est prêt.'
        );
    }
);

/*
 * ------------------------------------------------------------
 * 6. V2-FIRST NEXT QUESTION
 * ------------------------------------------------------------
 */

run(
    'artifact-next-question-is-v2-authoritative',
    function () {
        const result =
            evaluate(
                'Crée une WBS pour le projet SP-01.'
            );

        const output =
            v2(result);

        const artifactReadiness =
            readiness(result);

        assert(
            artifactId(result) ===
                'wbs',
            'WBS attendue.'
        );

        assert(
            artifactReadiness &&
            artifactReadiness.ready ===
                false,
            'La WBS doit être incomplète à ce stade.'
        );

        assert(
            Boolean(
                artifactReadiness
                    .nextRequirement
            ),
            'V2 doit exposer la prochaine exigence artifact.'
        );

        if (
            output.nextBestQuestion &&
            artifactReadiness
                .nextRequirement
        ) {
            const genericId =
                output.nextBestQuestion
                    .requirementId ||
                output.nextBestQuestion.id ||
                null;

            const artifactIdValue =
                artifactReadiness
                    .nextRequirement.id ||
                null;

            assert(
                !genericId ||
                !artifactIdValue ||
                genericId ===
                    artifactIdValue,
                'La question générique ne doit pas contredire la prochaine exigence artifact V2.'
            );
        }
    }
);

/*
 * ------------------------------------------------------------
 * 7. ENTITY REQUESTED VS CONTEXTUAL
 * ------------------------------------------------------------
 */

run(
    'contextual-quality-concepts-do-not-replace-requested-artifact',
    function () {
        const result =
            evaluate(
                'Crée une WBS pour organiser les travaux qualité et les inspections du projet.'
            );

        assert(
            artifactId(result) ===
                'wbs',
            'Les concepts qualité contextuels ne doivent pas remplacer la WBS explicitement demandée.'
        );
    }
);

console.log('');

console.log(
    'V2-first Orchestration Sprint 2.8: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}