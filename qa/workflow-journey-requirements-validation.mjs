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
        .workflow || [];

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

function evaluate(answers, freeText) {
    return requirements.evaluate(
        {
            id: 'workflow',
            label: 'Workflow'
        },
        {
            freeText:
                freeText ||
                'Crée un workflow de validation documentaire.',
            answers:
                answers || {}
        }
    );
}

test(
    'workflow-keeps-five-core-requirements',
    () =>
        Boolean(
            find('workflowScope') &&
            find('workflowSteps') &&
            find('workflowActors') &&
            find('workflowRules') &&
            find('workflowTraceability')
        )
);

test(
    'workflow-steps-are-blocking',
    () =>
        find('workflowSteps')
            .importance === 'blocking'
);

test(
    'workflow-step-question-requires-explicit-identifiers',
    () =>
        String(
            find('workflowSteps').question
        ).includes('STEP-001')
);

test(
    'workflow-step-question-covers-actor-action-transition',
    () => {
        const question =
            String(
                find('workflowSteps')
                    .question
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            question.includes(
                'acteur:'
            ) &&
            question.includes(
                'action:'
            ) &&
            question.includes(
                'suivant:'
            )
        );
    }
);

test(
    'workflow-question-does-not-authorize-invention',
    () => {
        const question =
            String(
                find('workflowSteps')
                    .question
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            question.includes(
                'inconn'
            ) &&
            question.includes(
                'invente'
            )
        );
    }
);

test(
    'generic-workflow-request-does-not-satisfy-context',
    () => {
        const result =
            evaluate(
                {},
                'Crée un workflow de validation documentaire.'
            );

        return (
            result.ready === false &&
            result
                .satisfiedRequirements
                .length === 0
        );
    }
);

const partial =
    evaluate({
        workflowScope:
            'Validation documentaire',
        workflowActors:
            'Émetteur; Responsable technique; Client',
        workflowRules:
            'Retour si document rejeté.',
        workflowTraceability:
            'Historique des validations.'
    });

test(
    'workflow-without-steps-is-not-ready',
    () =>
        partial.ready === false
);

test(
    'workflow-steps-appear-as-missing',
    () =>
        partial
            .missingRequirements
            .some(
                function (item) {
                    return (
                        item.answerKey ===
                        'workflowSteps'
                    );
                }
            )
);

const complete =
    evaluate({
        workflowScope:
            'Validation documentaire',
        workflowSteps:
            'STEP-001 | étape: Soumission | acteur: Émetteur | action: Soumettre le document | suivant: STEP-002 | sortie: Document soumis',
        workflowActors:
            'Émetteur; Responsable technique; Client',
        workflowRules:
            'Retour si document rejeté.',
        workflowTraceability:
            'Historique des validations.'
    });

test(
    'complete-workflow-context-is-ready',
    () =>
        complete.ready === true
);

console.log(
    '\nWorkflow Journey Requirements Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}