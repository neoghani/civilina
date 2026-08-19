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

const completeInput = {
    initialText:
        'Crée un ITP pour le bétonnage.',

    project:
        'Projet Alpha',

    zone:
        'Zone A',

    ouvrage:
        'Fondations',

    answers: {
        itpActivityScope:
            'Bétonnage des fondations',

        itpReferences:
            'Plan IFC C-204; Spécification béton C30/37; Procédure PR-BET-01',

        itpInspectionsTests:
            'Contrôle du coffrage et des armatures avant bétonnage; Essai d’affaissement; Contrôle de température; Prélèvement d’éprouvettes',

        itpAcceptanceCriteria:
            'Affaissement conforme à la spécification; Température dans la plage admissible; Résistance à la compression conforme au C30/37; Absence de non-conformité visuelle majeure',

        itpHoldWitness:
            'Hold Point avant bétonnage pour validation du coffrage, ferraillage, réservations et propreté; Witness Point pour l’essai d’affaissement et le prélèvement des éprouvettes',

        itpResponsibilities:
            'L’entreprise de travaux exécute; Le contrôleur qualité inspecte; Le responsable QA/QC approuve; Le client ou son représentant accepte'
    }
};

run(
    'complete-itp-is-buildable',
    function () {
        const validation =
            builder.validate(
                completeInput
            );

        assert(
            validation.valid === true,
            'ITP complet devrait être valide.'
        );

        assert(
            validation.completion === 100,
            'Complétude attendue=100 actual=' +
                validation.completion
        );

        assert(
            validation.missing.length === 0,
            'Aucune donnée obligatoire ne devrait manquer.'
        );
    }
);

run(
    'complete-itp-builds-draft',
    function () {
        const result =
            builder.build(
                completeInput
            );

        assert(
            result.ready === true,
            'Builder devrait être prêt.'
        );

        assert(
            result.status === 'DRAFT_READY',
            'Status attendu=DRAFT_READY actual=' +
                result.status
        );

        assert(
            result.artifact &&
            result.artifact.type === 'itp',
            'Artifact ITP attendu.'
        );

        assert(
            result.artifact.context.activity ===
                'Bétonnage des fondations',
            'Activité incorrecte.'
        );
    }
);

run(
    'references-are-structured',
    function () {
        const result =
            builder.build(
                completeInput
            );

        const references =
            result.artifact.references;

        assert(
            references.length === 3,
            '3 références attendues actual=' +
                references.length
        );

        assert(
            references.includes(
                'Plan IFC C-204'
            ),
            'Plan IFC absent.'
        );

        assert(
            references.includes(
                'Spécification béton C30/37'
            ),
            'Spécification béton absente.'
        );

        assert(
            references.includes(
                'Procédure PR-BET-01'
            ),
            'Procédure absente.'
        );
    }
);

run(
    'inspection-data-is-preserved',
    function () {
        const result =
            builder.build(
                completeInput
            );

        const inspections =
            result.artifact
                .inspectionPlan
                .inspectionsAndTests;

        assert(
            inspections.length === 4,
            '4 inspections/essais attendus actual=' +
                inspections.length
        );

        assert(
            inspections.some(
                function (item) {
                    return item.includes(
                        'affaissement'
                    );
                }
            ),
            'Essai d’affaissement absent.'
        );

        assert(
            inspections.some(
                function (item) {
                    return item.includes(
                        'température'
                    );
                }
            ),
            'Contrôle de température absent.'
        );
    }
);

run(
    'incomplete-itp-is-rejected',
    function () {
        const result =
            builder.build({
                answers: {
                    itpActivityScope:
                        'Bétonnage'
                }
            });

        assert(
            result.ready === false,
            'ITP incomplet ne doit pas être prêt.'
        );

        assert(
            result.status === 'INCOMPLETE',
            'Status attendu=INCOMPLETE actual=' +
                result.status
        );

        assert(
            result.artifact === null,
            'Aucun artifact ne doit être construit.'
        );

        assert(
            result.validation.missing.length === 5,
            '5 champs manquants attendus actual=' +
                result.validation.missing.length
        );
    }
);

run(
    'no-invented-technical-requirements',
    function () {
        const result =
            builder.build(
                completeInput
            );

        assert(
            result.artifact
                .traceability
                .inventedTechnicalRequirements ===
                false,
            'Le Builder ne doit inventer aucune exigence technique.'
        );

        assert(
            result.artifact
                .traceability
                .generatedFacts.length === 0,
            'Aucun fait technique généré attendu.'
        );
    }
);

run(
    'source-traceability-is-complete',
    function () {
        const result =
            builder.build(
                completeInput
            );

        const sources =
            result.artifact
                .traceability
                .sources;

        assert(
            sources.length === 6,
            '6 sources utilisateur attendues actual=' +
                sources.length
        );

        assert(
            sources.every(
                function (source) {
                    return (
                        source.source ===
                        'user-provided'
                    );
                }
            ),
            'Toutes les sources doivent être marquées user-provided.'
        );
    }
);

run(
    'builder-exposes-required-enrichment',
    function () {
        const result =
            builder.build(
                completeInput
            );

        const required =
            result.artifact
                .enrichment
                .required
                .map(
                    function (item) {
                        return item.id;
                    }
                );

        assert(
            required.includes(
                'ITP_ROWS_STRUCTURING'
            ),
            'Structuration des lignes ITP absente.'
        );

        assert(
            required.includes(
                'ITP_HP_WP_CLASSIFICATION'
            ),
            'Classification HP/WP absente.'
        );

        assert(
            required.includes(
                'ITP_RESPONSIBILITY_MATRIX'
            ),
            'Matrice de responsabilités absente.'
        );

        assert(
            required.includes(
                'ITP_RECORDS'
            ),
            'Définition des enregistrements absente.'
        );
    }
);

console.log('');
console.log(
    'ITP Builder Sprint 2.5: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}