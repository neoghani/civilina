import { loadCivilina } from './load-civilina-cjs.mjs';

const builder = loadCivilina('civilina-ai-itp-builder.js');

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

const naturalInput = {
    initialText: 'Crée un ITP pour le bétonnage.',
    answers: {
        itpReferences:
            'Plan IFC C-204, spécification béton C30/37 et procédure de bétonnage PR-BET-01.',
        itpInspectionsTests:
            'Contrôle du coffrage et des armatures avant bétonnage, essai d’affaissement, contrôle de température et prélèvement d’éprouvettes.',
        itpAcceptanceCriteria:
            'Affaissement conforme à la spécification, température du béton dans la plage admissible, résistance à la compression conforme au C30/37 et absence de non-conformité visuelle majeure.',
        itpHoldWitness:
            'Hold Point avant bétonnage pour validation du coffrage, ferraillage, réservations et propreté. Witness Point pour l’essai d’affaissement et le prélèvement des éprouvettes.',
        itpResponsibilities:
            'L’entreprise de travaux exécute. Le contrôleur qualité inspecte. Le responsable QA/QC approuve. Le client ou son représentant accepte selon les points définis dans l’ITP.'
    }
};

run('natural-answer-keeps-four-inspection-rows', function () {
    const rows = builder.build(naturalInput).artifact.rows;
    assert(rows.length === 4, '4 lignes ITP attendues actual=' + rows.length);
});

run('coffrage-row-does-not-receive-unrelated-criterion', function () {
    const rows = builder.build(naturalInput).artifact.rows;
    assert(
        rows[0].acceptanceCriteria === null,
        'La ligne coffrage doit rester sans critère si aucun lien explicite ou lexical sûr n’existe.'
    );
});

run('affaissement-criterion-is-safely-associated', function () {
    const row = builder.build(naturalInput).artifact.rows[1];
    assert(
        /Affaissement/i.test(row.acceptanceCriteria || ''),
        'Critère affaissement attendu ligne 2.'
    );
    assert(
        row.acceptanceCriteriaAssociation === 'LEXICAL_SAFE',
        'Association lexicale sûre attendue.'
    );
});

run('temperature-criterion-is-safely-associated', function () {
    const row = builder.build(naturalInput).artifact.rows[2];
    assert(
        /température/i.test(row.acceptanceCriteria || ''),
        'Critère température attendu ligne 3.'
    );
    assert(
        row.acceptanceCriteriaAssociation === 'LEXICAL_SAFE',
        'Association lexicale sûre attendue.'
    );
});

run('specimen-row-does-not-invent-compression-link', function () {
    const row = builder.build(naturalInput).artifact.rows[3];
    assert(
        row.acceptanceCriteria === null,
        'Le prélèvement ne doit pas être automatiquement associé à la résistance C30/37.'
    );
});

run('unassociated-criteria-are-preserved', function () {
    const plan = builder.build(naturalInput).artifact.inspectionPlan;
    assert(
        plan.unassociatedAcceptanceCriteria.some(function (item) {
            return /compression/i.test(item);
        }),
        'Le critère de résistance non associé doit être conservé.'
    );
    assert(
        plan.unassociatedAcceptanceCriteria.some(function (item) {
            return /non-conformité visuelle/i.test(item);
        }),
        'Le critère visuel non associé doit être conservé.'
    );
});

run('hold-witness-associations-remain-intact', function () {
    const rows = builder.build(naturalInput).artifact.rows;
    assert(rows[0].controlPoint === 'HOLD', 'Hold Point attendu ligne 1.');
    assert(rows[1].controlPoint === 'WITNESS', 'Witness Point attendu ligne 2.');
    assert(rows[3].controlPoint === 'WITNESS', 'Witness Point attendu ligne 4.');
});

run('explicit-structured-lists-preserve-positional-contract', function () {
    const result = builder.build({
        initialText: 'Crée un ITP pour le bétonnage.',
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
    });

    assert(
        result.artifact.rows[3].acceptanceCriteria.includes('C30/37'),
        'Le contrat positionnel explicite doit rester compatible.'
    );
    assert(
        result.artifact.rows.every(function (row) {
            return row.acceptanceCriteriaAssociation === 'POSITIONAL_EXPLICIT';
        }),
        'Le mode POSITIONAL_EXPLICIT attendu sur les listes explicitement structurées.'
    );
});

console.log('');
console.log(
    'ITP Natural Language Association Sprint 2.6: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}
