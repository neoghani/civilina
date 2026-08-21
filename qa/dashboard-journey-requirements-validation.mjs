import { loadCivilina } from './load-civilina-cjs.mjs';

const requirements =
    loadCivilina(
        'civilina-ai-artifact-requirements.js'
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

const registry =
    requirements
        .REQUIREMENT_REGISTRY
        .dashboard || [];

function find(answerKey) {
    return registry.find(
        function (item) {
            return (
                item &&
                item.answerKey === answerKey
            );
        }
    ) || null;
}

function evaluate(
    answers,
    freeText
) {
    return requirements.evaluate(
        {
            id: 'dashboard',
            label: 'Dashboard'
        },
        {
            freeText:
                freeText ||
                'Crée un dashboard de pilotage.',
            answers:
                answers || {}
        }
    );
}

test(
    'dashboard-keeps-five-core-requirements',
    () =>
        Boolean(
            find('dashboardDecision') &&
            find('dashboardAudience') &&
            find('dashboardKpi') &&
            find('dashboardDataSource') &&
            find('dashboardFrequency')
        )
);

test(
    'dashboard-kpis-are-blocking',
    () =>
        find('dashboardKpi')
            .importance === 'blocking'
);

test(
    'dashboard-data-sources-are-blocking',
    () =>
        find('dashboardDataSource')
            .importance === 'blocking'
);

test(
    'dashboard-kpi-question-requires-explicit-id',
    () =>
        String(
            find('dashboardKpi')
                .question
        ).includes('KPI-001')
);

test(
    'dashboard-kpi-question-covers-value-unit-target',
    () => {
        const question =
            String(
                find('dashboardKpi')
                    .question
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            question.includes('valeur:') &&
            question.includes('unite:') &&
            question.includes('cible:')
        );
    }
);

test(
    'dashboard-data-source-question-uses-kpi-id',
    () => {
        const question =
            String(
                find('dashboardDataSource')
                    .question
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            question.includes('kpi-001') &&
            question.includes('source:') &&
            question.includes(
                'proprietaire:'
            )
        );
    }
);

test(
    'dashboard-questions-do-not-authorize-invention',
    () => {
        const text =
            (
                find('dashboardKpi')
                    .question +
                ' ' +
                find('dashboardDataSource')
                    .question
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            text.includes('inconn') &&
            text.includes('invente')
        );
    }
);

test(
    'generic-dashboard-request-does-not-satisfy-context',
    () => {
        const result =
            evaluate(
                {},
                'Crée un dashboard de pilotage.'
            );

        return (
            result.ready === false &&
            result
                .satisfiedRequirements
                .length === 0
        );
    }
);

test(
    'complete-dashboard-context-is-ready',
    () =>
        evaluate({
            dashboardDecision:
                'Arbitrer les dérives coût et délai.',

            dashboardAudience:
                'Direction de projet',

            dashboardKpi:
                'KPI-001 | indicateur: Avancement physique | valeur: 68 | unité: % | cible: 75 | tendance: stable',

            dashboardDataSource:
                'KPI-001 | source: Planning projet | propriétaire: Project Controls',

            dashboardFrequency:
                'Hebdomadaire, date de statut chaque vendredi.'
        }).ready === true
);

console.log(
    '\nDashboard Journey Requirements Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}