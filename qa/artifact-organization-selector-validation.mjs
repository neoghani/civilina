import assert from 'node:assert/strict';
import fs from 'node:fs';

const source =
    fs.readFileSync(
        new URL(
            '../resources/js/civilina-ai/civilina-ai-journey.js',
            import.meta.url
        ),
        'utf8'
    );

const checks = [
    [
        'selector is rendered in artifact persistence block',
        /dataset\.organizationSelector\s*=\s*['"]true['"]/,
    ],
    [
        'organizations are loaded through persistence module',
        /\.discoverOrganizations\s*\(/,
    ],
    [
        'active organization is explicitly set',
        /\.setActiveOrganizationPublicId\s*\(/,
    ],
    [
        'save button is blocked before tenant resolution',
        /button\.disabled\s*=\s*true/,
    ],
    [
        'single organization can be auto selected',
        /organizations\.length\s*===\s*1/,
    ],
    [
        'multiple organizations require explicit choice',
        /Plusieurs organisations sont disponibles/,
    ],
    [
        'organization public id is used as selector value',
        /option\.value\s*=\s*organization\.public_id/,
    ],
    [
        'tenant discovery failure keeps save disabled',
        /\.catch\([\s\S]*button\.disabled\s*=\s*true/,
    ],
    [
        'persist handler reads selected organization',
        /querySelector\(\s*['"]\[data-organization-selector\]['"]\s*\)[\s\S]*organizationPublicId/,
    ],
    [
        'persist handler forces organization scoped endpoint',
        /['"]\/api\/organizations\/['"][\s\S]*encodeURIComponent\([\s\S]*organizationPublicId[\s\S]*['"]\/artifacts['"]/,
    ],
    [
        'persist call passes explicit organization id',
        /\.persistArtifact\([\s\S]*pendingArtifactPersistence[\s\S]*organizationPublicId\s*:\s*organizationPublicId/,
    ],
    [
        'persist call passes explicit scoped endpoint',
        /\.persistArtifact\([\s\S]*endpoint\s*:\s*organizationEndpoint/,
    ],
];

let passed = 0;

checks.forEach(function ([name, pattern]) {
    assert.match(source, pattern);
    passed += 1;
    console.log(`PASS ${name}`);
});

console.log(
    `\n${passed}/${checks.length} browser tenant hardening checks passed.`
);
