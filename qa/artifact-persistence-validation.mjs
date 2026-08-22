import assert from 'node:assert/strict';

await import(
    '../resources/js/civilina-ai/civilina-ai-artifact-persistence.js'
);

const {
    normalizeArtifactForPersistence,
    normalizeArtifactType,
    persistArtifact,
} = globalThis.CivilinaArtifactPersistence;

let passed = 0;

function test(name, callback) {
    try {
        callback();
        passed += 1;
        console.log(`PASS ${name}`);
    } catch (error) {
        console.error(`FAIL ${name}`);
        throw error;
    }
}

async function asyncTest(
    name,
    callback
) {
    try {
        await callback();
        passed += 1;
        console.log(`PASS ${name}`);
    } catch (error) {
        console.error(`FAIL ${name}`);
        throw error;
    }
}

test(
    'normalizes Risk Register alias',
    () => {
        assert.equal(
            normalizeArtifactType(
                'riskRegister'
            ),
            'risk_register'
        );
    }
);

test(
    'preserves backend artifact types',
    () => {
        assert.equal(
            normalizeArtifactType('itp'),
            'itp'
        );

        assert.equal(
            normalizeArtifactType('wbs'),
            'wbs'
        );

        assert.equal(
            normalizeArtifactType('raci'),
            'raci'
        );

        assert.equal(
            normalizeArtifactType(
                'workflow'
            ),
            'workflow'
        );

        assert.equal(
            normalizeArtifactType(
                'dashboard'
            ),
            'dashboard'
        );
    }
);

test(
    'rejects unsupported artifact type',
    () => {
        assert.throws(
            () =>
                normalizeArtifactType(
                    'unknown'
                ),
            /Unsupported Civilina artifact type/
        );
    }
);

test(
    'builds deterministic API payload',
    () => {
        const normalized =
            normalizeArtifactForPersistence(
                {
                    type:
                        'riskRegister',

                    title:
                        'Risk Register — SP-01',

                    payload: {
                        rows: [
                            {
                                id:
                                    'R-001',
                            },
                        ],
                    },

                    provenance: {
                        invented_facts:
                            false,
                    },
                }
            );

        assert.equal(
            normalized.type,
            'risk_register'
        );

        assert.equal(
            normalized.title,
            'Risk Register — SP-01'
        );

        assert.equal(
            normalized.provenance
                .origin,
            'civilina_ai'
        );

        assert.equal(
            normalized.provenance
                .invented_facts,
            false
        );

        assert.equal(
            normalized.payload
                .rows[0].id,
            'R-001'
        );
    }
);

test(
    'does not invent missing title',
    () => {
        assert.throws(
            () =>
                normalizeArtifactForPersistence(
                    {
                        type:
                            'dashboard',

                        payload: {
                            rows: [],
                        },
                    }
                ),
            /Artifact title is required/
        );
    }
);

test(
    'does not accept unstructured payload',
    () => {
        assert.throws(
            () =>
                normalizeArtifactForPersistence(
                    {
                        type:
                            'dashboard',

                        title:
                            'Dashboard',

                        payload:
                            'some text',
                    }
                ),
            /structured payload/
        );
    }
);

await asyncTest(
    'posts artifact to Laravel API',
    async () => {
        let capturedUrl = null;
        let capturedOptions = null;

        const fakeFetch =
            async (
                url,
                options
            ) => {
                capturedUrl = url;
                capturedOptions =
                    options;

                return {
                    ok: true,
                    status: 201,

                    async json() {
                        return {
                            data: {
                                public_id:
                                    '01TEST',

                                type:
                                    'dashboard',

                                current_revision: {
                                    revision_label:
                                        'DRAFT-01',
                                },
                            },
                        };
                    },
                };
            };

        const result =
            await persistArtifact(
                {
                    type:
                        'dashboard',

                    title:
                        'Executive Dashboard — SP-01',

                    payload: {
                        rows: [],
                    },
                },
                {
                    fetchImpl:
                        fakeFetch,
                }
            );

        assert.equal(
            capturedUrl,
            '/api/artifacts'
        );

        assert.equal(
            capturedOptions.method,
            'POST'
        );

        const sent =
            JSON.parse(
                capturedOptions.body
            );

        assert.equal(
            sent.type,
            'dashboard'
        );

        assert.equal(
            sent.provenance.origin,
            'civilina_ai'
        );

        assert.equal(
            result.public_id,
            '01TEST'
        );

        assert.equal(
            result.current_revision
                .revision_label,
            'DRAFT-01'
        );
    }
);

await asyncTest(
    'propagates Laravel API failure',
    async () => {
        const fakeFetch =
            async () => ({
                ok: false,
                status: 422,

                async json() {
                    return {
                        message:
                            'Artifact rejected.',
                    };
                },
            });

        await assert.rejects(
            () =>
                persistArtifact(
                    {
                        type:
                            'raci',

                        title:
                            'RACI — SP-01',

                        payload: {
                            rows: [],
                        },
                    },
                    {
                        fetchImpl:
                            fakeFetch,
                    }
                ),
            /Artifact rejected/
        );
    }
);

console.log(
    `\n${passed}/8 artifact persistence checks passed.`
);
