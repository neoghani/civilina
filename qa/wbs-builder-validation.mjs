import { loadCivilina } from './load-civilina-cjs.mjs';

const builder = loadCivilina(
    'civilina-ai-wbs-builder.js'
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
        'Crée une WBS pour le projet de construction de la station de pompage.',

    answers: {
        wbsScopeBasis:
            'Projet de construction de la station de pompage SP-01.',

        wbsDecomposition:
            'Décomposition par ouvrages puis par disciplines et lots de travaux.',

        wbsZoneOuvrage:
            'Zone pompage; bâtiment technique; bassin; réseaux extérieurs.',

        wbsCodification:
            'PRJ-OUV-DISC-LOT-NNN',

        wbsIntegration:
            'La WBS doit servir de référence commune pour le planning, les coûts, le progrès et les documents.'
    }
};

run(
    'complete-wbs-is-buildable',
    function () {
        assert(
            builder.canBuild(
                completeInput
            ) === true,
            'La WBS complète devrait être constructible.'
        );
    }
);

run(
    'complete-wbs-builds-draft',
    function () {
        const result =
            builder.build(
                completeInput
            );

        assert(
            result.ready === true,
            'Le Builder devrait retourner ready=true.'
        );

        assert(
            result.status ===
                'DRAFT_READY',
            'Statut DRAFT_READY attendu.'
        );

        assert(
            result.artifact &&
            result.artifact.type ===
                'wbs',
            'Artifact WBS attendu.'
        );
    }
);

run(
    'wbs-preserves-scope',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            artifact.scope.statement.includes(
                'station de pompage'
            ),
            'Le périmètre projet doit être conservé.'
        );
    }
);

run(
    'wbs-preserves-zone-ouvrage-dimension',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            Array.isArray(
                artifact.context
                    .zonesOuvrages
            ),
            'zonesOuvrages attendu.'
        );

        assert(
            artifact.context
                .zonesOuvrages
                .length === 4,
            '4 zones / ouvrages attendus.'
        );

        assert(
            artifact.context
                .zonesOuvrages
                .includes(
                    'bâtiment technique'
                ),
            'Bâtiment technique attendu.'
        );
    }
);

run(
    'wbs-keeps-zone-ouvrage-separate-from-hierarchy',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            artifact.hierarchy,
            'Hiérarchie WBS attendue.'
        );

        assert(
            artifact.context
                .zonesOuvrages !==
                artifact.hierarchy,
            'Zone/Ouvrage ne doit pas être confondu avec la hiérarchie WBS.'
        );
    }
);

run(
    'wbs-preserves-codification-rule',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            artifact.codification &&
            artifact.codification.rule ===
                'PRJ-OUV-DISC-LOT-NNN',
            'La règle de codification doit être conservée.'
        );
    }
);

run(
    'wbs-exposes-project-controls-integration',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        const integration =
            artifact.integration;

        assert(
            integration,
            'Bloc integration attendu.'
        );

        assert(
            integration.planning ===
                true,
            'Intégration planning attendue.'
        );

        assert(
            integration.cost ===
                true,
            'Intégration coûts attendue.'
        );

        assert(
            integration.progress ===
                true,
            'Intégration progrès attendue.'
        );

        assert(
            integration.documents ===
                true,
            'Intégration documents attendue.'
        );
    }
);

run(
    'wbs-does-not-invent-work-packages',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            Array.isArray(
                artifact.workPackages
            ),
            'workPackages attendu.'
        );

        assert(
            artifact.workPackages
                .length === 0,
            'Le Builder ne doit pas inventer de Work Packages.'
        );
    }
);

run(
    'wbs-exposes-missing-enrichment',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            artifact.enrichment &&
            Array.isArray(
                artifact.enrichment
                    .required
            ),
            'Enrichissement requis attendu.'
        );

        const ids =
            artifact.enrichment
                .required
                .map(function (item) {
                    return item.id;
                });

        assert(
            ids.includes(
                'WBS_HIERARCHY_STRUCTURING'
            ),
            'Structuration hiérarchique attendue.'
        );

        assert(
            ids.includes(
                'WBS_WORK_PACKAGES'
            ),
            'Work Packages attendus comme enrichissement.'
        );

        assert(
            ids.includes(
                'WBS_BASELINE_VERSION'
            ),
            'Baseline/version attendue.'
        );
    }
);

run(
    'wbs-keeps-source-traceability',
    function () {
        const artifact =
            builder.build(
                completeInput
            ).artifact;

        assert(
            artifact.traceability &&
            artifact.traceability
                .inventedBusinessStructure ===
                false,
            'Le Builder ne doit pas inventer la structure métier.'
        );

        assert(
            artifact.traceability
                .sources.length >= 5,
            'Les sources utilisateur doivent être tracées.'
        );
    }
);

run(
    'incomplete-wbs-is-rejected',
    function () {
        const result =
            builder.build({
                initialText:
                    'Crée une WBS.',

                answers: {
                    wbsScopeBasis:
                        'Projet SP-01.'
                }
            });

        assert(
            result.ready === false,
            'Une WBS incomplète doit être refusée.'
        );

        assert(
            result.status ===
                'INCOMPLETE',
            'Statut INCOMPLETE attendu.'
        );
    }
);

console.log('');

console.log(
    'WBS Builder Sprint 2.7: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}