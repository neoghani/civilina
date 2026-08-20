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
        .raci || [];

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
            id: 'raci',
            label: 'RACI',
            family: 'organization'
        },
        {
            freeText:
                freeText ||
                'Crée une matrice RACI pour le projet SP-01.',
            answers:
                answers || {}
        }
    );
}

test(
    'raci-keeps-existing-core-requirements',
    () =>
        Boolean(
            find('raciProcessScope') &&
            find('raciActivities') &&
            find('raciRoles') &&
            find('raciAuthority')
        )
);

test(
    'raci-requires-explicit-assignments',
    () => {
        const requirement =
            find('raciAssignments');

        return Boolean(
            requirement &&
            requirement.id ===
                'RACI_ASSIGNMENTS_REQUIRED' &&
            requirement.importance ===
                'blocking'
        );
    }
);

test(
    'raci-assignment-question-covers-r-a-c-i',
    () => {
        const requirement =
            find('raciAssignments');

        if (!requirement) {
            return false;
        }

        const question =
            String(
                requirement.question || ''
            ).toLowerCase();

        return (
            question.includes('r:') &&
            question.includes('a:') &&
            question.includes('c:') &&
            question.includes('i:')
        );
    }
);

test(
    'raci-assignment-question-does-not-authorize-invention',
    () => {
        const requirement =
            find('raciAssignments');

        if (!requirement) {
            return false;
        }

        const question =
            String(
                requirement.question || ''
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            question.includes('inconn') &&
            (
                question.includes(
                    'invente'
                ) ||
                question.includes(
                    'inventer'
                )
            )
        );
    }
);

test(
    'generic-raci-request-does-not-satisfy-business-context',
    () => {
        const result =
            evaluate(
                {},
                'Crée une matrice RACI pour le projet SP-01.'
            );

        return (
            result.ready === false &&
            result.satisfiedRequirements
                .length === 0
        );
    }
);

const existingFour = {
    raciProcessScope:
        'Projet SP-01 - station de pompage.',

    raciActivities:
        'ACT-001 Validation des plans IFC; ACT-002 Approbation des achats critiques.',

    raciRoles:
        'Chef de projet; Ingénieur études; Responsable achats; QA/QC; Client.',

    raciAuthority:
        'Le Chef de projet approuve les décisions projet. Les engagements contractuels suivent la délégation d’autorité applicable.'
};

const withoutAssignments =
    evaluate(existingFour);

test(
    'existing-four-fields-are-not-enough-for-raci',
    () =>
        withoutAssignments.ready ===
            false
);

test(
    'raci-assignments-appear-as-missing-requirement',
    () =>
        Array.isArray(
            withoutAssignments
                .missingRequirements
        ) &&
        withoutAssignments
            .missingRequirements
            .some(
                function (item) {
                    return (
                        item &&
                        item.answerKey ===
                            'raciAssignments'
                    );
                }
            )
);

const complete =
    evaluate(
        Object.assign(
            {},
            existingFour,
            {
                raciAssignments: [
                    'ACT-001 | activité: Validation des plans IFC | R: Ingénieur études | A: Chef de projet | C: QA/QC | I: Client',
                    'ACT-002 | activité: Approbation des achats critiques | R: Responsable achats | A: Chef de projet | C: QA/QC | I: Client'
                ].join('\n')
            }
        )
    );

test(
    'complete-raci-business-context-is-ready',
    () =>
        complete.ready === true
);

test(
    'complete-raci-has-no-missing-requirements',
    () =>
        Array.isArray(
            complete.missingRequirements
        ) &&
        complete
            .missingRequirements
            .length === 0
);

console.log(
    '\nRACI Journey Requirements Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}