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

const input = {
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
    'zone-ouvrage-never-becomes-wbs-level',
    function () {
        const artifact = builder.build(input).artifact;
        const dimensions = artifact.hierarchy.declaredDimensions;

        assert(
            !dimensions.includes('zone'),
            'Zone ne doit pas devenir un niveau WBS.'
        );

        assert(
            !dimensions.includes('ouvrage'),
            'Ouvrage ne doit pas devenir un niveau WBS.'
        );
    }
);

run(
    'wbs-hierarchy-keeps-real-decomposition-signals',
    function () {
        const artifact = builder.build(input).artifact;
        const dimensions = artifact.hierarchy.declaredDimensions;

        assert(
            dimensions.includes('discipline'),
            'Discipline doit rester un signal de hiérarchie WBS.'
        );

        assert(
            dimensions.includes('lot'),
            'Lot doit rester un signal de hiérarchie WBS.'
        );
    }
);

run(
    'ouvrage-intent-is-preserved-as-context',
    function () {
        const artifact = builder.build(input).artifact;

        assert(
            artifact.decomposition.contextualDimensions.includes('ouvrage'),
            'Le signal ouvrage doit être conservé comme dimension contextuelle.'
        );

        assert(
            artifact.decomposition.allDeclaredSignals.includes('ouvrage'),
            'Le signal utilisateur ouvrage doit rester traçable.'
        );
    }
);

run(
    'zone-ouvrage-dimension-remains-explicitly-separate',
    function () {
        const artifact = builder.build(input).artifact;

        assert(
            artifact.context.zoneOuvrageDimension.separateFromHierarchy === true,
            'Zone/Ouvrage doit rester explicitement séparé de la hiérarchie.'
        );

        assert(
            artifact.decomposition.zoneOuvrageSeparated === true,
            'Le contrat de décomposition doit déclarer la séparation.'
        );
    }
);

run(
    'no-wbs-node-is-invented-by-separation',
    function () {
        const artifact = builder.build(input).artifact;

        assert(
            artifact.hierarchy.nodes.length === 0,
            'La correction ne doit inventer aucun nœud WBS.'
        );

        assert(
            artifact.workPackages.length === 0,
            'La correction ne doit inventer aucun Work Package.'
        );
    }
);

console.log('');
console.log(
    'WBS Zone/Ouvrage Separation Sprint 2.7: ' +
        passed +
        '/' +
        (passed + failed) +
        ' passed'
);

if (failed > 0) {
    process.exitCode = 1;
}
