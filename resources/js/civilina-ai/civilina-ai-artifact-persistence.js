(function (root, factory) {
    'use strict';

    const api = factory(root);

    if (
        typeof module === 'object' &&
        module.exports
    ) {
        module.exports = api;
    } else {
        root.CivilinaArtifactPersistence = api;
    }
})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function (root) {
        'use strict';

        const ARTIFACT_API_ENDPOINT =
            '/api/artifacts';

        const TYPE_ALIASES =
            Object.freeze({
                itp: 'itp',

                wbs: 'wbs',

                risk_register:
                    'risk_register',

                'risk-register':
                    'risk_register',

                riskRegister:
                    'risk_register',

                raci: 'raci',

                workflow:
                    'workflow',

                dashboard:
                    'dashboard'
            });

        function normalizeArtifactType(
            type
        ) {
            const normalized =
                TYPE_ALIASES[type];

            if (!normalized) {
                throw new Error(
                    'Unsupported Civilina artifact type: ' +
                    String(type)
                );
            }

            return normalized;
        }

        function normalizeArtifactForPersistence(
            artifact
        ) {
            if (
                !artifact ||
                typeof artifact !== 'object'
            ) {
                throw new Error(
                    'A Civilina artifact object is required.'
                );
            }

            const type =
                normalizeArtifactType(
                    artifact.type ||
                    artifact.artifactType
                );

            const title =
                String(
                    artifact.title || ''
                ).trim();

            if (!title) {
                throw new Error(
                    'Artifact title is required before persistence.'
                );
            }

            const payload =
                artifact.payload ||
                artifact.data ||
                artifact.content;

            if (
                !payload ||
                typeof payload !== 'object' ||
                Array.isArray(payload)
            ) {
                throw new Error(
                    'Artifact structured payload is required before persistence.'
                );
            }

            const provenance =
                Object.assign(
                    {
                        origin:
                            'civilina_ai'
                    },

                    artifact.provenance &&
                    typeof artifact.provenance ===
                        'object'
                        ? artifact.provenance
                        : {}
                );

            const normalized = {
                type,
                title,
                payload,
                provenance,

                source:
                    artifact.source ||
                    'civilina_ai'
            };

            if (
                artifact.metadata &&
                typeof artifact.metadata ===
                    'object'
            ) {
                normalized.metadata =
                    artifact.metadata;
            }

            if (
                artifact.change_summary
            ) {
                normalized.change_summary =
                    artifact.change_summary;
            }

            return normalized;
        }

        async function persistArtifact(
            artifact,
            options
        ) {
            const safeOptions =
                options || {};

            const fetchImpl =
                safeOptions.fetchImpl ||
                root.fetch;

            if (
                typeof fetchImpl !==
                'function'
            ) {
                throw new Error(
                    'Fetch API is unavailable.'
                );
            }

            const endpoint =
                safeOptions.endpoint ||
                ARTIFACT_API_ENDPOINT;

            const requestPayload =
                normalizeArtifactForPersistence(
                    artifact
                );

            const response =
                await fetchImpl(
                    endpoint,
                    {
                        method:
                            'POST',

                        headers: {
                            Accept:
                                'application/json',

                            'Content-Type':
                                'application/json'
                        },

                        body:
                            JSON.stringify(
                                requestPayload
                            )
                    }
                );

            let body = null;

            try {
                body =
                    await response.json();
            } catch (error) {
                body = null;
            }

            if (!response.ok) {
                throw new Error(
                    body &&
                    body.message
                        ? body.message
                        : 'Artifact persistence failed with HTTP ' +
                          response.status +
                          '.'
                );
            }

            if (
                !body ||
                !body.data ||
                !body.data.public_id ||
                !body.data.current_revision ||
                !body.data.current_revision
                    .revision_label
            ) {
                throw new Error(
                    'Artifact API returned an invalid persistence response.'
                );
            }

            return body.data;
        }

        return {
            ARTIFACT_API_ENDPOINT,
            normalizeArtifactType,
            normalizeArtifactForPersistence,
            persistArtifact
        };
    }
);
