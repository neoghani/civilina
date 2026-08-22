import assert from 'node:assert/strict';

await import(
    '../resources/js/civilina-ai/civilina-ai-artifact-persistence.js'
);

const {
    normalizeArtifactForPersistence,
    normalizeArtifactType,
    organizationArtifactEndpoint,
    setActiveOrganizationPublicId,
    getActiveOrganizationPublicId,
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

function artifact(
    overrides = {}
) {
    return Object.assign(
        {
            type:
                'dashboard',

            title:
                'Executive Dashboard — SP-01',

            payload: {
                rows: [],
            },

            provenance: {
                origin:
                    'civilina_ai',
            },
        },
        overrides
    );
}

function response(
    status,
    body
) {
    return {
        ok:
            status >= 200 &&
            status < 300,

        status,

        async json() {
            return body;
        },
    };
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

test(
    'builds organization scoped endpoint',
    () => {
        assert.equal(
            organizationArtifactEndpoint(
                'ORG-01'
            ),
            '/api/organizations/ORG-01/artifacts'
        );
    }
);

await asyncTest(
    'explicit endpoint preserves controlled legacy compatibility',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        let capturedUrl = null;

        const fakeFetch =
            async (
                url
            ) => {
                capturedUrl = url;

                return response(
                    201,
                    {
                        data: {
                            public_id:
                                '01LEGACY',

                            current_revision: {
                                revision_label:
                                    'DRAFT-01',
                            },
                        },
                    }
                );
            };

        await persistArtifact(
            artifact(),
            {
                fetchImpl:
                    fakeFetch,

                endpoint:
                    '/api/artifacts',
            }
        );

        assert.equal(
            capturedUrl,
            '/api/artifacts'
        );
    }
);

await asyncTest(
    'single organization is auto resolved and scoped',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        const calls = [];

        const fakeFetch =
            async (
                url,
                options
            ) => {
                calls.push({
                    url,
                    method:
                        options.method,
                });

                if (
                    url ===
                    '/api/organizations'
                ) {
                    return response(
                        200,
                        {
                            data: [
                                {
                                    public_id:
                                        'ORG-ONE',
                                },
                            ],
                        }
                    );
                }

                return response(
                    201,
                    {
                        data: {
                            public_id:
                                '01SCOPED',

                            organization_id:
                                7,

                            current_revision: {
                                revision_label:
                                    'DRAFT-01',
                            },
                        },
                    }
                );
            };

        const result =
            await persistArtifact(
                artifact(),
                {
                    fetchImpl:
                        fakeFetch,
                }
            );

        assert.deepEqual(
            calls,
            [
                {
                    url:
                        '/api/organizations',
                    method:
                        'GET',
                },
                {
                    url:
                        '/api/organizations/ORG-ONE/artifacts',
                    method:
                        'POST',
                },
            ]
        );

        assert.equal(
            getActiveOrganizationPublicId(),
            'ORG-ONE'
        );

        assert.equal(
            result.organization_id,
            7
        );
    }
);

await asyncTest(
    'explicit active organization skips discovery',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        const calls = [];

        const fakeFetch =
            async (
                url,
                options
            ) => {
                calls.push({
                    url,
                    method:
                        options.method,
                });

                return response(
                    201,
                    {
                        data: {
                            public_id:
                                '01EXPLICIT',

                            current_revision: {
                                revision_label:
                                    'DRAFT-01',
                            },
                        },
                    }
                );
            };

        await persistArtifact(
            artifact(),
            {
                fetchImpl:
                    fakeFetch,

                organizationPublicId:
                    'ORG-EXPLICIT',
            }
        );

        assert.deepEqual(
            calls,
            [
                {
                    url:
                        '/api/organizations/ORG-EXPLICIT/artifacts',
                    method:
                        'POST',
                },
            ]
        );
    }
);

await asyncTest(
    'multiple organizations require explicit selection',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        let postCount = 0;

        const fakeFetch =
            async (
                url,
                options
            ) => {
                if (
                    options.method ===
                    'POST'
                ) {
                    postCount += 1;
                }

                return response(
                    200,
                    {
                        data: [
                            {
                                public_id:
                                    'ORG-A',
                            },
                            {
                                public_id:
                                    'ORG-B',
                            },
                        ],
                    }
                );
            };

        await assert.rejects(
            () =>
                persistArtifact(
                    artifact(),
                    {
                        fetchImpl:
                            fakeFetch,
                    }
                ),
            /Plusieurs organisations/
        );

        assert.equal(
            postCount,
            0
        );
    }
);

await asyncTest(
    'no organization rejects persistence',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        const fakeFetch =
            async () =>
                response(
                    200,
                    {
                        data: [],
                    }
                );

        await assert.rejects(
            () =>
                persistArtifact(
                    artifact(),
                    {
                        fetchImpl:
                            fakeFetch,
                    }
                ),
            /Aucune organisation active/
        );
    }
);

await asyncTest(
    'authentication failure is explicit',
    async () => {
        setActiveOrganizationPublicId(
            null
        );

        const fakeFetch =
            async () =>
                response(
                    401,
                    {
                        message:
                            'Unauthenticated.',
                    }
                );

        await assert.rejects(
            () =>
                persistArtifact(
                    artifact(),
                    {
                        fetchImpl:
                            fakeFetch,
                    }
                ),
            /Authentification requise/
        );
    }
);

await asyncTest(
    'propagates scoped Laravel API failure',
    async () => {
        setActiveOrganizationPublicId(
            'ORG-FAIL'
        );

        const fakeFetch =
            async () =>
                response(
                    422,
                    {
                        message:
                            'Artifact rejected.',
                    }
                );

        await assert.rejects(
            () =>
                persistArtifact(
                    artifact({
                        type:
                            'raci',

                        title:
                            'RACI — SP-01',
                    }),
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
    `\n${passed}/14 artifact organization persistence checks passed.`
);
