import { loadCivilina } from './load-civilina-cjs.mjs';

const builder =
    loadCivilina(
        'civilina-ai-dashboard-builder.js'
    );

const bridge =
    loadCivilina(
        'civilina-ai-dashboard-journey-bridge.js'
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

const input = {
    initialText:
        'Crée un dashboard exécutif pour le projet SP-01.',

    answers: {
        dashboardDecision:
            'Arbitrer les dérives coût et délai et identifier les actions prioritaires.',

        dashboardAudience:
            'Direction de projet et DG',

        dashboardKpi: [
            'KPI-001 | indicateur: Avancement physique | valeur: 68 | unité: % | cible: 75 | tendance: stable',
            'KPI-002 | indicateur: Écart coût | valeur: 1.2 | unité: M€ | cible: 0 | tendance: hausse'
        ].join('\n'),

        dashboardDataSource: [
            'KPI-001 | source: Planning projet | propriétaire: Project Controls',
            'KPI-002 | source: ERP coûts | propriétaire: Cost Control'
        ].join('\n'),

        dashboardFrequency:
            'Hebdomadaire, date de statut chaque vendredi.'
    }
};

const prepared =
    bridge.prepare(input);

const built =
    prepared.ready
        ? builder.build(
            prepared.builderInput
        )
        : null;

const rows =
    built &&
    built.artifact
        ? built.artifact.rows
        : [];

const kpi1 =
    rows.find(
        row =>
            row.id === 'KPI-001'
    );

const kpi2 =
    rows.find(
        row =>
            row.id === 'KPI-002'
    );

test(
    'dashboard-text-is-prepared-for-builder',
    () =>
        prepared.ready === true
);

test(
    'dashboard-bridge-builds-two-kpis',
    () =>
        prepared
            .builderInput
            .answers
            .dashboardKpi
            .length === 2
);

test(
    'dashboard-preserves-kpi-identifiers',
    () =>
        Boolean(
            kpi1 &&
            kpi2
        )
);

test(
    'dashboard-preserves-kpi-name',
    () =>
        kpi1.name ===
        'Avancement physique'
);

test(
    'dashboard-preserves-current-value',
    () =>
        kpi1.value === '68'
);

test(
    'dashboard-preserves-unit-and-target',
    () =>
        kpi1.unit === '%' &&
        kpi1.target === '75'
);

test(
    'dashboard-preserves-explicit-trend',
    () =>
        kpi2.trend === 'hausse'
);

test(
    'dashboard-associates-source-by-kpi-id',
    () =>
        kpi1.source ===
            'Planning projet' &&
        kpi2.source ===
            'ERP coûts'
);

test(
    'dashboard-associates-data-owner-by-kpi-id',
    () =>
        kpi1.dataOwner ===
            'Project Controls' &&
        kpi2.dataOwner ===
            'Cost Control'
);

const wrongAssociation =
    bridge.prepare({
        answers: {
            dashboardKpi:
                'KPI-100 | indicateur: Marge | valeur: 8 | unité: % | cible: 10 | tendance: stable',

            dashboardDataSource:
                'KPI-999 | source: ERP | propriétaire: Finance'
        }
    });

const wrongRow =
    wrongAssociation
        .builderInput
        .answers
        .dashboardKpi[0];

test(
    'dashboard-does-not-associate-source-by-position',
    () =>
        wrongRow.source === null &&
        wrongRow.dataOwner === null
);

const unknown =
    bridge.prepare({
        answers: {
            dashboardKpi:
                'KPI-200 | indicateur: Prévision délai | valeur: inconnue | unité: jours | cible: inconnue | tendance: inconnue',

            dashboardDataSource:
                'KPI-200 | source: inconnue | propriétaire: inconnue'
        }
    });

const unknownRow =
    unknown
        .builderInput
        .answers
        .dashboardKpi[0];

test(
    'dashboard-does-not-invent-unknown-values',
    () =>
        unknownRow.value === null &&
        unknownRow.target === null &&
        unknownRow.trend === null &&
        unknownRow.source === null &&
        unknownRow.dataOwner === null
);

test(
    'dashboard-builder-is-ready',
    () =>
        Boolean(
            built &&
            built.ready &&
            built.artifact
        )
);

test(
    'dashboard-preserves-decision',
    () =>
        built.artifact
            .context
            .decision
            .includes('Arbitrer')
);

test(
    'dashboard-preserves-audience',
    () =>
        built.artifact
            .context
            .audience ===
        'Direction de projet et DG'
);

test(
    'dashboard-preserves-frequency',
    () =>
        built.artifact
            .context
            .frequency
            .includes('vendredi')
);

test(
    'dashboard-traceability-declares-no-invention',
    () =>
        built.artifact
            .traceability
            .inventedKpis === false &&
        built.artifact
            .traceability
            .inventedValues === false &&
        built.artifact
            .traceability
            .inventedTargets === false &&
        built.artifact
            .traceability
            .inventedDataSources === false &&
        built.artifact
            .traceability
            .inventedOwners === false
);

test(
    'dashboard-does-not-create-kpi-from-id-only',
    () =>
        bridge.parseKpis(
            'KPI-999 | valeur: 12 | unité: %'
        ).length === 0
);

const incomplete =
    builder.build({
        answers: {
            dashboardDecision:
                'Décider',

            dashboardAudience:
                'DG',

            dashboardKpi: [],

            dashboardDataSource: [],

            dashboardFrequency:
                'Hebdomadaire'
        }
    });

test(
    'dashboard-builder-rejects-missing-structured-data',
    () =>
        incomplete.ready === false &&
        incomplete.artifact === null
);

console.log(
    '\nDashboard Builder + Journey Bridge Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}