import { loadCivilina } from './load-civilina-cjs.mjs';

const builder = loadCivilina(
    'civilina-ai-itp-builder.js'
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

const input = {
    initialText:
        'Crée un ITP pour le bétonnage.',

    answers: {
        itpReferences:
            'Plan IFC C-204; Spécification béton C30/37; Procédure PR-BET-01',

        itpInspectionsTests:
            'Contrôle du coffrage et des armatures avant bétonnage; Essai d’affaissement; Contrôle de température; Prélèvement d’éprouvettes',

        itpAcceptanceCriteria:
            'Coffrage, ferraillage, réservations et propreté conformes; Affaissement conforme à la spécification; Température dans la plage admissible; Résistance à la compression conforme au C30/37',

        itpHoldWitness:
            'Hold Point avant bétonnage pour validation du coffrage, ferraillage, réservations et propreté; Witness Point pour l’essai d’affaissement et le prélèvement des éprouvettes',

        itpResponsibilities:
            'L’entreprise de travaux exécute; Le contrôleur qualité inspecte; Le responsable QA/QC approuve; Le client ou son représentant accepte'
    }
};

run(
    'builder-produces-structured-rows',
    function () {
        const result = builder.build(input);

        assert(
            result.ready === true,
            'ITP devrait être constructible.'
        );

        assert(
            Array.isArray(result.artifact.rows),
            'artifact.rows attendu.'
        );

        assert(
            result.artifact.rows.length === 4,
            '4 lignes ITP attendues actual=' +
                result.artifact.rows.length
        );
    }
);

run(
    'rows-have-stable-codes',
    function () {
        const result = builder.build(input);
        const rows = result.artifact.rows;

        assert(
            rows[0].code === 'ITP-001',
            'Code ligne 1 incorrect.'
        );

        assert(
            rows[3].code === 'ITP-004',
            'Code ligne 4 incorrect.'
        );
    }
);

run(
    'inspection-is-preserved-per-row',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows[0].inspectionTest.includes(
                'coffrage'
            ),
            'Contrôle coffrage attendu ligne 1.'
        );

        assert(
            rows[1].inspectionTest.includes(
                'affaissement'
            ),
            'Affaissement attendu ligne 2.'
        );

        assert(
            rows[2].inspectionTest.includes(
                'température'
            ),
            'Température attendue ligne 3.'
        );

        assert(
            rows[3].inspectionTest.includes(
                'éprouvettes'
            ),
            'Éprouvettes attendues ligne 4.'
        );
    }
);

run(
    'criteria-are-associated-by-position',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows[1].acceptanceCriteria.includes(
                'Affaissement'
            ),
            'Critère affaissement attendu ligne 2.'
        );

        assert(
            rows[3].acceptanceCriteria.includes(
                'C30/37'
            ),
            'Critère résistance attendu ligne 4.'
        );
    }
);

run(
    'missing-method-is-not-invented',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows.every(function (row) {
                return row.method === null;
            }),
            'La méthode ne doit pas être inventée.'
        );
    }
);

run(
    'missing-frequency-is-not-invented',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows.every(function (row) {
                return row.frequency === null;
            }),
            'La fréquence ne doit pas être inventée.'
        );
    }
);

run(
    'missing-record-is-not-invented',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows.every(function (row) {
                return row.record === null;
            }),
            'Les enregistrements ne doivent pas être inventés.'
        );
    }
);

run(
    'hold-and-witness-are-classified',
    function () {
        const artifact =
            builder.build(input)
                .artifact;

        const checkpoints =
            artifact.controlPoints
                .structuredClassification;

        assert(
            checkpoints.some(function (item) {
                return item.type === 'HOLD';
            }),
            'Hold Point structuré attendu.'
        );

        assert(
            checkpoints.some(function (item) {
                return item.type === 'WITNESS';
            }),
            'Witness Point structuré attendu.'
        );
    }
);

run(
    'responsibilities-are-structured',
    function () {
        const artifact =
            builder.build(input)
                .artifact;

        const matrix =
            artifact.responsibilities
                .roleMatrix;

        assert(
            matrix.execute,
            'Responsable exécution attendu.'
        );

        assert(
            matrix.inspect,
            'Responsable inspection attendu.'
        );

        assert(
            matrix.approve,
            'Responsable approbation attendu.'
        );

        assert(
            matrix.accept,
            'Responsable acceptation attendu.'
        );
    }
);

run(
    'rows-identify-missing-enrichment',
    function () {
        const rows =
            builder.build(input)
                .artifact.rows;

        assert(
            rows.every(function (row) {
                return Array.isArray(
                    row.missingFields
                );
            }),
            'missingFields attendu pour chaque ligne.'
        );

        assert(
            rows[0].missingFields.includes(
                'method'
            ),
            'Méthode manquante attendue.'
        );

        assert(
            rows[0].missingFields.includes(
                'frequency'
            ),
            'Fréquence manquante attendue.'
        );

        assert(
            rows[0].missingFields.includes(
                'record'
            ),
            'Enregistrement manquant attendu.'
        );
    }
);

console.log('');
console.log(
    'ITP Structured Rows Sprint 2.6: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}