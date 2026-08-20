import { loadCivilina } from './load-civilina-cjs.mjs';

const bridge =
    loadCivilina(
        'civilina-ai-risk-register-journey-bridge.js'
    );

const builder =
    loadCivilina(
        'civilina-ai-risk-register-builder.js'
    );

let passed = 0;
let failed = 0;

function test(name, assertion) {
    try {
        if (!assertion()) {
            throw new Error(
                'Assertion returned false'
            );
        }

        console.log(
            'PASS',
            name
        );

        passed += 1;
    } catch (error) {
        console.error(
            'FAIL',
            name,
            '-',
            error.message
        );

        failed += 1;
    }
}

const journeyInput = {
    initialText:
        'Crée un registre des risques pour le projet SP-01.',

    project:
        'SP-01',

    answers: {
        riskScope:
            'Projet SP-01 - construction de la station de pompage.',

        riskCategories:
            'Planning; achats; technique; qualité.',

        riskScoring:
            'Matrice 5x5. Probabilité de 1 à 5, impact de 1 à 5, criticité = probabilité x impact.',

        riskItems:
            [
                'R-001 | risque: Retard de livraison des pompes principales | cause: Délai fournisseur supérieur au délai contractuel prévu | conséquence: Décalage possible des essais et de la mise en service | probabilité: 4 | impact: 5',
                'R-002 | risque: Interfaces incomplètes entre génie civil et équipements | cause: Données d’interface non encore consolidées | conséquence: Risque de reprises et de retard de montage | probabilité: 3 | impact: 4'
            ].join('\n'),

        riskOwners:
            'R-001 : Responsable achats; R-002 : Chef de projet.',

        riskTreatment:
            [
                'R-001 | réponse: Suivi fournisseur renforcé et plan de récupération à établir | échéance: 2026-09-10 | statut: open',
                'R-002 | réponse: Organiser une revue d’interface multidisciplinaire | échéance: 2026-09-05 | statut: open'
            ].join('\n')
    }
};

const prepared =
    bridge.prepare(
        journeyInput
    );

const preparedRows =
    prepared &&
    prepared.builderInput &&
    prepared.builderInput.answers &&
    Array.isArray(
        prepared.builderInput
            .answers
            .riskItems
    )
        ? prepared.builderInput
            .answers
            .riskItems
        : [];

const row1 =
    preparedRows.find(
        function (row) {
            return (
                row &&
                row.id === 'R-001'
            );
        }
    ) || null;

const row2 =
    preparedRows.find(
        function (row) {
            return (
                row &&
                row.id === 'R-002'
            );
        }
    ) || null;

/*
 * ------------------------------------------------------------
 * BRIDGE CONTRACT
 * ------------------------------------------------------------
 */

test(
    'journey-risk-text-is-prepared-for-builder',
    () =>
        Boolean(
            prepared &&
            prepared.ready === true &&
            prepared.status ===
                'READY_FOR_BUILDER' &&
            prepared.builderInput
        )
);

test(
    'journey-risk-items-become-structured-array',
    () =>
        Array.isArray(
            preparedRows
        ) &&
        preparedRows.length === 2
);

test(
    'bridge-preserves-explicit-risk-identifiers',
    () =>
        Boolean(
            row1 &&
            row2
        )
);

test(
    'bridge-keeps-risk-cause-and-consequence-separate',
    () =>
        Boolean(
            row1 &&
            row1.risk ===
                'Retard de livraison des pompes principales' &&
            row1.cause ===
                'Délai fournisseur supérieur au délai contractuel prévu' &&
            row1.consequence ===
                'Décalage possible des essais et de la mise en service'
        )
);

test(
    'bridge-preserves-explicit-probability-and-impact',
    () =>
        Boolean(
            row1 &&
            row1.probability === 4 &&
            row1.impact === 5 &&
            row2 &&
            row2.probability === 3 &&
            row2.impact === 4
        )
);

test(
    'bridge-associates-owner-by-risk-id',
    () =>
        Boolean(
            row1 &&
            row1.owner ===
                'Responsable achats' &&
            row2 &&
            row2.owner ===
                'Chef de projet'
        )
);

test(
    'bridge-associates-treatment-by-risk-id',
    () =>
        Boolean(
            row1 &&
            row1.response ===
                'Suivi fournisseur renforcé et plan de récupération à établir' &&
            row2 &&
            row2.response ===
                'Organiser une revue d’interface multidisciplinaire'
        )
);

test(
    'bridge-preserves-explicit-due-date-and-status',
    () =>
        Boolean(
            row1 &&
            row1.dueDate ===
                '2026-09-10' &&
            row1.status ===
                'open' &&
            row2 &&
            row2.dueDate ===
                '2026-09-05' &&
            row2.status ===
                'open'
        )
);

/*
 * ------------------------------------------------------------
 * NO-INVENTION CONTRACT
 * ------------------------------------------------------------
 */

const incomplete =
    bridge.prepare({
        initialText:
            'Crée un registre des risques.',

        answers: {
            riskScope:
                'Projet SP-01',

            riskCategories:
                'Achats',

            riskScoring:
                'Matrice 5x5, criticité = probabilité x impact',

            riskItems:
                'R-003 | risque: Retard possible de fourniture | cause: inconnue | conséquence: retard potentiel',

            riskOwners:
                'R-001 : Responsable achats',

            riskTreatment:
                'R-999 | réponse: Relancer le fournisseur | échéance: 2026-09-15 | statut: open'
        }
    });

const incompleteRows =
    incomplete &&
    incomplete.builderInput &&
    incomplete.builderInput.answers &&
    Array.isArray(
        incomplete.builderInput
            .answers
            .riskItems
    )
        ? incomplete.builderInput
            .answers
            .riskItems
        : [];

const row3 =
    incompleteRows[0] ||
    null;

test(
    'bridge-does-not-invent-unknown-cause',
    () =>
        Boolean(
            row3 &&
            row3.cause === null
        )
);

test(
    'bridge-does-not-invent-probability-or-impact',
    () =>
        Boolean(
            row3 &&
            row3.probability === null &&
            row3.impact === null
        )
);

test(
    'bridge-does-not-associate-owner-by-position',
    () =>
        Boolean(
            row3 &&
            row3.owner === null
        )
);

test(
    'bridge-does-not-associate-treatment-with-wrong-risk-id',
    () =>
        Boolean(
            row3 &&
            row3.response === null &&
            row3.dueDate === null &&
            row3.status === null
        )
);

test(
    'bridge-declares-no-invented-business-facts',
    () =>
        Boolean(
            prepared &&
            prepared.traceability &&
            prepared.traceability
                .inventedFacts === false
        )
);

/*
 * ------------------------------------------------------------
 * BUILDER INTEGRATION CONTRACT
 * ------------------------------------------------------------
 */

const built =
    prepared &&
    prepared.ready
        ? builder.build(
            prepared.builderInput
        )
        : null;

test(
    'bridge-output-is-accepted-by-risk-register-builder',
    () =>
        Boolean(
            built &&
            built.ready === true &&
            built.artifact &&
            built.artifact.type ===
                'riskRegister'
        )
);

test(
    'builder-computes-criticality-after-safe-bridge',
    () => {
        if (
            !built ||
            !built.artifact ||
            !Array.isArray(
                built.artifact.rows
            )
        ) {
            return false;
        }

        const first =
            built.artifact.rows.find(
                function (row) {
                    return (
                        row &&
                        row.id ===
                            'R-001'
                    );
                }
            );

        const second =
            built.artifact.rows.find(
                function (row) {
                    return (
                        row &&
                        row.id ===
                            'R-002'
                    );
                }
            );

        return Boolean(
            first &&
            first.criticality === 20 &&
            second &&
            second.criticality === 12
        );
    }
);

console.log(
    '\nRisk Register Journey → Builder Bridge Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}