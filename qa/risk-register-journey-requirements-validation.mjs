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

function riskRegistry() {
    const registry =
        requirements &&
        requirements.REQUIREMENT_REGISTRY;

    if (
        !registry ||
        !registry.riskRegister
    ) {
        return [];
    }

    return Array.isArray(
        registry.riskRegister
    )
        ? registry.riskRegister
        : (
            Array.isArray(
                registry.riskRegister
                    .requirements
            )
                ? registry.riskRegister
                    .requirements
                : []
        );
}

function requirementByKey(key) {
    return riskRegistry()
        .find(
            function (item) {
                return (
                    item &&
                    item.answerKey ===
                        key
                );
            }
        ) || null;
}

function evaluate(answers) {
    if (
        !requirements ||
        typeof requirements.evaluate !==
            'function'
    ) {
        return null;
    }

    return requirements.evaluate(
        {
            id:
                'riskRegister',
            label:
                'Risk Register',
            family:
                'riskManagement'
        },
        {
            freeText:
                'Crée un registre des risques pour le projet SP-01.',
            answers:
                answers || {}
        }
    );
}

/*
 * ------------------------------------------------------------
 * REGISTRY CONTRACT
 * ------------------------------------------------------------
 */

test(
    'risk-register-keeps-existing-core-requirements',
    () => {
        const keys =
            riskRegistry()
                .map(
                    function (item) {
                        return item.answerKey;
                    }
                );

        return [
            'riskScope',
            'riskCategories',
            'riskScoring',
            'riskOwners'
        ].every(
            function (key) {
                return keys.includes(
                    key
                );
            }
        );
    }
);

test(
    'risk-register-requires-explicit-risk-items',
    () => {
        const requirement =
            requirementByKey(
                'riskItems'
            );

        return Boolean(
            requirement &&
            requirement.id ===
                'RISK_ITEMS_REQUIRED' &&
            requirement.importance ===
                'blocking'
        );
    }
);

test(
    'risk-items-question-asks-for-risk-cause-and-consequence',
    () => {
        const requirement =
            requirementByKey(
                'riskItems'
            );

        if (!requirement) {
            return false;
        }

        const text =
            String(
                requirement.question ||
                ''
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            text.includes(
                'risque'
            ) &&
            text.includes(
                'cause'
            ) &&
            text.includes(
                'consequence'
            )
        );
    }
);

test(
    'risk-items-question-does-not-authorize-invention',
    () => {
        const requirement =
            requirementByKey(
                'riskItems'
            );

        if (!requirement) {
            return false;
        }

        const text =
            String(
                requirement.question ||
                ''
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            text.includes(
                'conn'
            ) ||
            text.includes(
                'invente'
            ) ||
            text.includes(
                'inconn'
            )
        );
    }
);

test(
    'risk-register-requires-treatment-actions',
    () => {
        const requirement =
            requirementByKey(
                'riskTreatment'
            );

        return Boolean(
            requirement &&
            requirement.id ===
                'RISK_TREATMENT_REQUIRED'
        );
    }
);

test(
    'risk-treatment-question-covers-action-deadline-and-status',
    () => {
        const requirement =
            requirementByKey(
                'riskTreatment'
            );

        if (!requirement) {
            return false;
        }

        const text =
            String(
                requirement.question ||
                ''
            )
                .normalize('NFD')
                .replace(
                    /[\u0300-\u036f]/g,
                    ''
                )
                .toLowerCase();

        return (
            (
                text.includes(
                    'action'
                ) ||
                text.includes(
                    'traitement'
                ) ||
                text.includes(
                    'reponse'
                )
            ) &&
            (
                text.includes(
                    'echeance'
                ) ||
                text.includes(
                    'date'
                )
            ) &&
            (
                text.includes(
                    'statut'
                ) ||
                text.includes(
                    'etat'
                )
            )
        );
    }
);

/*
 * ------------------------------------------------------------
 * READINESS CONTRACT
 * ------------------------------------------------------------
 */

test(
    'existing-four-fields-are-not-enough-to-create-risk-register',
    () => {
        const result =
            evaluate({
                riskScope:
                    'Projet SP-01',

                riskCategories:
                    'Planning; achats; technique; qualité',

                riskScoring:
                    'Matrice 5x5, criticité = probabilité x impact',

                riskOwners:
                    'Responsable achats; Chef de projet'
            });

        return Boolean(
            result &&
            result.applicable ===
                true &&
            result.ready ===
                false
        );
    }
);

test(
    'explicit-risk-items-become-missing-requirement',
    () => {
        const result =
            evaluate({
                riskScope:
                    'Projet SP-01',

                riskCategories:
                    'Planning; achats; technique; qualité',

                riskScoring:
                    'Matrice 5x5, criticité = probabilité x impact',

                riskOwners:
                    'Responsable achats; Chef de projet'
            });

        if (
            !result ||
            !Array.isArray(
                result.missingRequirements
            )
        ) {
            return false;
        }

        return result
            .missingRequirements
            .some(
                function (item) {
                    return (
                        item &&
                        item.answerKey ===
                            'riskItems'
                    );
                }
            );
    }
);

test(
    'risk-register-is-not-ready-with-risks-but-no-treatment',
    () => {
        const result =
            evaluate({
                riskScope:
                    'Projet SP-01',

                riskCategories:
                    'Planning; achats; technique; qualité',

                riskItems:
                    'R-001 : retard de livraison des pompes ; cause : délai fournisseur ; conséquence : retard possible des essais.',

                riskScoring:
                    'Matrice 5x5, criticité = probabilité x impact',

                riskOwners:
                    'R-001 : Responsable achats'
            });

        return Boolean(
            result &&
            result.ready ===
                false &&
            Array.isArray(
                result.missingRequirements
            ) &&
            result
                .missingRequirements
                .some(
                    function (item) {
                        return (
                            item &&
                            item.answerKey ===
                                'riskTreatment'
                        );
                    }
                )
        );
    }
);

test(
    'risk-register-readiness-requires-complete-business-context',
    () => {
        const result =
            evaluate({
                riskScope:
                    'Projet SP-01',

                riskCategories:
                    'Planning; achats; technique; qualité',

                riskItems:
                    'R-001 : retard de livraison des pompes ; cause : délai fournisseur ; conséquence : retard possible des essais.',

                riskScoring:
                    'Matrice 5x5, criticité = probabilité x impact',

                riskOwners:
                    'R-001 : Responsable achats',

                riskTreatment:
                    'R-001 : suivi fournisseur renforcé ; échéance 10/09/2026 ; statut ouvert.'
            });

        return Boolean(
            result &&
            result.applicable ===
                true &&
            result.ready ===
                true
        );
    }
);

console.log(
    '\nRisk Register Journey Requirements Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}