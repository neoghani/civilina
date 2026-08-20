import { loadCivilina } from './load-civilina-cjs.mjs';

const builder =
    loadCivilina(
        'civilina-ai-raci-builder.js'
    );

const bridge =
    loadCivilina(
        'civilina-ai-raci-journey-bridge.js'
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

const input = {
    initialText:
        'Crée une matrice RACI pour le projet SP-01.',

    project:
        'SP-01',

    answers: {
        raciProcessScope:
            'Projet SP-01 - station de pompage.',

        raciActivities:
            'ACT-001 Validation des plans IFC; ACT-002 Approbation des achats critiques.',

        raciRoles:
            'Chef de projet; Ingénieur études; Responsable achats; QA/QC; Client.',

        raciAuthority:
            'Le Chef de projet approuve les décisions projet.',

        raciAssignments: [
            'ACT-001 | activité: Validation des plans IFC | R: Ingénieur études | A: Chef de projet | C: QA/QC | I: Client',
            'ACT-002 | activité: Approbation des achats critiques | R: Responsable achats | A: Chef de projet | C: QA/QC | I: Client'
        ].join('\n')
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

test(
    'raci-journey-text-is-prepared',
    () =>
        prepared.ready === true &&
        prepared.status ===
            'READY_FOR_BUILDER'
);

test(
    'raci-bridge-builds-two-assignments',
    () =>
        prepared
            .builderInput
            .answers
            .raciAssignments
            .length === 2
);

const rows =
    built &&
    built.artifact
        ? built.artifact.rows
        : [];

const row1 =
    rows.find(
        function (row) {
            return row.id ===
                'ACT-001';
        }
    );

const row2 =
    rows.find(
        function (row) {
            return row.id ===
                'ACT-002';
        }
    );

test(
    'raci-builder-is-ready',
    () =>
        Boolean(
            built &&
            built.ready &&
            built.artifact &&
            built.artifact.type ===
                'raci'
        )
);

test(
    'raci-preserves-activity-ids',
    () =>
        Boolean(
            row1 &&
            row2
        )
);

test(
    'raci-preserves-responsible-role',
    () =>
        Boolean(
            row1 &&
            row1.responsible.includes(
                'Ingénieur études'
            ) &&
            row2 &&
            row2.responsible.includes(
                'Responsable achats'
            )
        )
);

test(
    'raci-preserves-accountable-role',
    () =>
        Boolean(
            row1 &&
            row1.accountable.includes(
                'Chef de projet'
            ) &&
            row2 &&
            row2.accountable.includes(
                'Chef de projet'
            )
        )
);

test(
    'raci-preserves-consulted-and-informed',
    () =>
        Boolean(
            row1 &&
            row1.consulted.includes(
                'QA/QC'
            ) &&
            row1.informed.includes(
                'Client'
            )
        )
);

test(
    'raci-preserves-process-scope',
    () =>
        built.artifact.context
            .processScope ===
        'Projet SP-01 - station de pompage.'
);

test(
    'raci-preserves-authority-statement',
    () =>
        built.artifact.authority
            .statement ===
        'Le Chef de projet approuve les décisions projet.'
);

const unknown =
    bridge.prepare({
        answers: {
            raciAssignments:
                'ACT-003 | activité: Réception fournisseur | R: inconnue | A: Chef de projet | C: inconnue | I: Client'
        }
    });

const unknownRow =
    unknown
        .builderInput
        .answers
        .raciAssignments[0];

test(
    'raci-bridge-does-not-invent-unknown-roles',
    () =>
        unknownRow
            .responsible
            .length === 0 &&
        unknownRow
            .consulted
            .length === 0
);

test(
    'raci-bridge-does-not-create-activity-from-id-only',
    () =>
        bridge.parseAssignments(
            'ACT-999 | R: Chef de projet | A: Direction'
        ).length === 0
);

test(
    'raci-traceability-declares-no-invention',
    () =>
        Boolean(
            built.artifact
                .traceability &&
            built.artifact
                .traceability
                .inventedActivities ===
                    false &&
            built.artifact
                .traceability
                .inventedRoles ===
                    false &&
            built.artifact
                .traceability
                .inventedAssignments ===
                    false
        )
);

const incomplete =
    builder.build({
        answers: {
            raciProcessScope:
                'Projet SP-01',
            raciActivities:
                'ACT-001',
            raciRoles:
                'Chef de projet',
            raciAuthority:
                'Aucune règle spécifique'
        }
    });

test(
    'raci-builder-rejects-missing-assignments',
    () =>
        incomplete.ready ===
            false &&
        incomplete.artifact ===
            null
);

console.log(
    '\nRACI Builder + Journey Bridge Sprint 2.9: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exit(1);
}