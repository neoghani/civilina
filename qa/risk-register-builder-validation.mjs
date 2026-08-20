import { loadCivilina } from './load-civilina-cjs.mjs';

const builder = loadCivilina(
    'civilina-ai-risk-register-builder.js'
);

let passed = 0;
let failed = 0;

function test(name, assertion) {
    try {
        if (!assertion()) {
            throw new Error('Assertion returned false');
        }

        console.log('PASS', name);
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

function completeInput() {
    return {
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

            riskOwners:
                'R-001 : Responsable achats; R-002 : Chef de projet.',

            riskItems: [
                {
                    id: 'R-001',
                    category: 'achats',
                    risk:
                        'Retard de livraison des pompes principales.',
                    cause:
                        'Délai fournisseur supérieur au délai contractuel prévu.',
                    consequence:
                        'Décalage possible des essais et de la mise en service.',
                    probability: 4,
                    impact: 5,
                    owner:
                        'Responsable achats',
                    response:
                        'Suivi fournisseur renforcé et plan de récupération à établir.',
                    dueDate:
                        '2026-09-10',
                    status:
                        'open'
                },
                {
                    id: 'R-002',
                    category: 'Technique',
                    risk:
                        'Interfaces incomplètes entre génie civil et équipements.',
                    cause:
                        'Données d’interface non encore consolidées.',
                    consequence:
                        'Risque de reprises et de retard de montage.',
                    probability: 3,
                    impact: 4,
                    owner:
                        'Chef de projet',
                    response:
                        'Organiser une revue d’interface multidisciplinaire.',
                    dueDate:
                        '2026-09-05',
                    status:
                        'open'
                }
            ]
        }
    };
}

const complete =
    builder.build(
        completeInput()
    );

test(
    'complete-risk-register-is-buildable',
    () =>
        complete &&
        complete.ready === true &&
        complete.status === 'READY'
);

test(
    'complete-risk-register-builds-draft',
    () =>
        complete.artifact &&
        complete.artifact.type ===
            'riskRegister' &&
        complete.artifact.status ===
            'draft'
);

test(
    'risk-register-preserves-scope',
    () =>
        complete.artifact &&
        complete.artifact.context &&
        complete.artifact.context.scope ===
            'Projet SP-01 - construction de la station de pompage.'
);

test(
    'risk-register-builds-explicit-risk-rows',
    () =>
        Array.isArray(
            complete.artifact.rows
        ) &&
        complete.artifact.rows.length ===
            2 &&
        complete.artifact.rows[0].id ===
            'R-001' &&
        complete.artifact.rows[1].id ===
            'R-002'
);

test(
    'risk-keeps-cause-and-consequence-separate',
    () => {
        const row =
            complete.artifact.rows[0];

        return (
            row.cause ===
                'Délai fournisseur supérieur au délai contractuel prévu.' &&
            row.consequence ===
                'Décalage possible des essais et de la mise en service.'
        );
    }
);

test(
    'risk-probability-impact-are-not-invented',
    () => {
        const input =
            completeInput();

        input.answers.riskItems[0] = {
            id: 'R-001',
            category: 'achats',
            risk:
                'Retard de livraison des pompes principales.',
            cause:
                'Délai fournisseur incertain.',
            consequence:
                'Décalage potentiel de la mise en service.',
            owner:
                'Responsable achats'
        };

        const result =
            builder.build(input);

        const row =
            result.artifact.rows[0];

        return (
            row.probability === null &&
            row.impact === null &&
            row.criticality === null
        );
    }
);

test(
    'criticality-is-deterministic-when-scoring-method-supports-it',
    () => {
        const first =
            complete.artifact.rows[0];

        const second =
            complete.artifact.rows[1];

        return (
            first.probability === 4 &&
            first.impact === 5 &&
            first.criticality === 20 &&
            second.probability === 3 &&
            second.impact === 4 &&
            second.criticality === 12
        );
    }
);

test(
    'risk-owner-response-and-due-date-are-preserved',
    () => {
        const row =
            complete.artifact.rows[0];

        return (
            row.owner ===
                'Responsable achats' &&
            row.response ===
                'Suivi fournisseur renforcé et plan de récupération à établir.' &&
            row.dueDate ===
                '2026-09-10'
        );
    }
);

test(
    'risk-status-is-preserved',
    () =>
        complete.artifact.rows[0]
            .status === 'open'
);

test(
    'risk-register-preserves-categories-and-scoring-method',
    () =>
        Array.isArray(
            complete.artifact.categories
        ) &&
        complete.artifact.categories.includes(
            'Planning'
        ) &&
        complete.artifact.categories.includes(
            'achats'
        ) &&
        complete.artifact.scoring &&
        complete.artifact.scoring.method ===
            'matrix-5x5' &&
        complete.artifact.scoring.formula ===
            'probability-impact-product'
);

test(
    'risk-register-does-not-invent-risks',
    () => {
        const input =
            completeInput();

        input.answers.riskItems = [];

        const result =
            builder.build(input);

        return (
            result.ready === false &&
            result.artifact === null
        );
    }
);

test(
    'risk-register-keeps-source-traceability',
    () => {
        const trace =
            complete.artifact &&
            complete.artifact.traceability;

        return (
            trace &&
            Array.isArray(trace.sources) &&
            trace.sources.length > 0 &&
            trace.inventedRisks === false &&
            trace.inventedScores === false
        );
    }
);

test(
    'risk-rows-identify-missing-enrichment',
    () => {
        const input =
            completeInput();

        input.answers.riskItems[0].response =
            null;
        input.answers.riskItems[0].dueDate =
            null;

        const result =
            builder.build(input);

        const row =
            result.artifact.rows[0];

        return (
            Array.isArray(
                row.missingFields
            ) &&
            row.missingFields.includes(
                'response'
            ) &&
            row.missingFields.includes(
                'dueDate'
            ) &&
            row.rowStatus ===
                'TO_BE_ENRICHED'
        );
    }
);

test(
    'incomplete-risk-register-is-rejected',
    () => {
        const result =
            builder.build({
                initialText:
                    'Crée un registre des risques.',
                answers: {
                    riskScope:
                        'Projet SP-01'
                }
            });

        return (
            result.ready === false &&
            result.status ===
                'INCOMPLETE' &&
            result.artifact === null
        );
    }
);

console.log(
    '\nRisk Register Builder Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}
