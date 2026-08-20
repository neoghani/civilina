import { loadCivilina } from './load-civilina-cjs.mjs';

const builder =
    loadCivilina(
        'civilina-ai-workflow-builder.js'
    );

const bridge =
    loadCivilina(
        'civilina-ai-workflow-journey-bridge.js'
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
        'Crée un workflow de validation documentaire.',

    answers: {
        workflowScope:
            'Validation documentaire du projet SP-01.',

        workflowSteps: [
            'STEP-001 | étape: Soumission | acteur: Émetteur | action: Soumettre le document | suivant: STEP-002 | sortie: Document soumis',
            'STEP-002 | étape: Revue technique | acteur: Responsable technique | action: Vérifier le document | suivant: STEP-003 | sortie: Document revu',
            'STEP-003 | étape: Approbation | acteur: Client | action: Approuver le document | suivant: FIN | sortie: Document approuvé'
        ].join('\n'),

        workflowActors:
            'Émetteur; Responsable technique; Client',

        workflowRules:
            'Tout rejet retourne le document à l’émetteur. Délai cible de revue : 5 jours.',

        workflowTraceability:
            'Conserver auteur, date, décision, commentaire et historique des statuts.'
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

const step1 =
    rows.find(
        row =>
            row.id === 'STEP-001'
    );

const step2 =
    rows.find(
        row =>
            row.id === 'STEP-002'
    );

const step3 =
    rows.find(
        row =>
            row.id === 'STEP-003'
    );

test(
    'workflow-text-is-prepared-for-builder',
    () =>
        prepared.ready === true
);

test(
    'workflow-bridge-builds-three-steps',
    () =>
        prepared
            .builderInput
            .answers
            .workflowSteps
            .length === 3
);

test(
    'workflow-preserves-step-identifiers',
    () =>
        Boolean(
            step1 &&
            step2 &&
            step3
        )
);

test(
    'workflow-preserves-step-name',
    () =>
        step1.name ===
        'Soumission'
);

test(
    'workflow-preserves-explicit-actor',
    () =>
        step2.actor ===
        'Responsable technique'
);

test(
    'workflow-preserves-explicit-action',
    () =>
        step2.action ===
        'Vérifier le document'
);

test(
    'workflow-preserves-explicit-transition',
    () =>
        step1.nextStep ===
            'STEP-002' &&
        step2.nextStep ===
            'STEP-003'
);

test(
    'workflow-recognizes-explicit-terminal-step',
    () =>
        step3.terminal === true &&
        step3.nextStep === null
);

test(
    'workflow-builder-is-ready',
    () =>
        Boolean(
            built &&
            built.ready &&
            built.artifact &&
            built.artifact.type ===
                'workflow'
        )
);

test(
    'workflow-preserves-scope',
    () =>
        built.artifact.context
            .scope ===
        'Validation documentaire du projet SP-01.'
);

test(
    'workflow-preserves-declared-actors',
    () =>
        built.artifact
            .declaredActors
            .includes(
                'Responsable technique'
            )
);

test(
    'workflow-preserves-rules',
    () =>
        built.artifact
            .rules
            .statement
            .includes(
                '5 jours'
            )
);

test(
    'workflow-preserves-traceability-requirements',
    () =>
        built.artifact
            .traceabilityRequirements
            .statement
            .includes(
                'historique'
            )
);

const unknown =
    bridge.parseSteps(
        'STEP-010 | étape: Contrôle | acteur: inconnue | action: Vérifier | suivant: STEP-011 | sortie: inconnue'
    )[0];

test(
    'workflow-does-not-invent-unknown-actor-or-output',
    () =>
        unknown.actor === null &&
        unknown.outcome === null
);

const invalidTransition =
    bridge.parseSteps(
        'STEP-020 | étape: Validation | acteur: Manager | action: Valider | suivant: étape suivante | sortie: Décision'
    )[0];

test(
    'workflow-does-not-invent-transition-id',
    () =>
        invalidTransition
            .nextStep === null &&
        invalidTransition
            .terminal === false
);

test(
    'workflow-does-not-create-step-from-id-only',
    () =>
        bridge.parseSteps(
            'STEP-999 | acteur: Manager | action: Valider'
        ).length === 0
);

test(
    'workflow-traceability-declares-no-invention',
    () =>
        Boolean(
            built.artifact
                .traceability &&
            built.artifact
                .traceability
                .inventedSteps ===
                    false &&
            built.artifact
                .traceability
                .inventedActors ===
                    false &&
            built.artifact
                .traceability
                .inventedTransitions ===
                    false &&
            built.artifact
                .traceability
                .inventedRules ===
                    false
        )
);

const incomplete =
    builder.build({
        answers: {
            workflowScope:
                'Validation documentaire',
            workflowActors:
                'Émetteur',
            workflowRules:
                'Aucune règle spécifique',
            workflowTraceability:
                'Historique requis'
        }
    });

test(
    'workflow-builder-rejects-missing-steps',
    () =>
        incomplete.ready ===
            false &&
        incomplete.artifact ===
            null
);

console.log(
    '\nWorkflow Builder + Journey Bridge Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}