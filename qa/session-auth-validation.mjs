import assert from 'node:assert/strict';

globalThis.document = {
    querySelector(selector) {
        if (
            selector ===
            'meta[name="csrf-token"]'
        ) {
            return {
                getAttribute(name) {
                    return name ===
                        'content'
                        ? 'csrf-test-token'
                        : null;
                },
            };
        }

        return null;
    },
};

await import(
    '../resources/js/civilina-ai/civilina-ai-artifact-persistence.js'
);

const persistence =
    globalThis.CivilinaArtifactPersistence;

assert.equal(
    persistence.csrfToken(),
    'csrf-test-token'
);

let capturedOptions = null;

const fakeFetch =
    async (
        url,
        options
    ) => {
        if (
            url ===
            '/api/organizations'
        ) {
            return {
                ok: true,
                status: 200,

                async json() {
                    return {
                        data: [
                            {
                                public_id:
                                    'ORG-SESSION',
                            },
                        ],
                    };
                },
            };
        }

        capturedOptions =
            options;

        return {
            ok: true,
            status: 201,

            async json() {
                return {
                    data: {
                        public_id:
                            'ART-SESSION',

                        current_revision: {
                            revision_label:
                                'DRAFT-01',
                        },
                    },
                };
            },
        };
    };

await persistence.persistArtifact(
    {
        type:
            'wbs',

        title:
            'WBS — Session QA',

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
    capturedOptions
        .headers[
            'X-CSRF-TOKEN'
        ],
    'csrf-test-token'
);

assert.equal(
    capturedOptions.credentials,
    'same-origin'
);

console.log(
    '3/3 session auth frontend checks passed.'
);
