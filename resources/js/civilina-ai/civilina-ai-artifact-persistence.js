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

        const LEGACY_ARTIFACT_API_ENDPOINT =
            '/api/artifacts';

        const ORGANIZATIONS_API_ENDPOINT =
            '/api/organizations';

        let activeOrganizationPublicId =
            null;

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

        function normalizeOrganizationPublicId(
            value
        ) {
            const normalized =
                String(
                    value || ''
                ).trim();

            if (!normalized) {
                return null;
            }

            return normalized;
        }

        function setActiveOrganizationPublicId(
            publicId
        ) {
            activeOrganizationPublicId =
                normalizeOrganizationPublicId(
                    publicId
                );

            return activeOrganizationPublicId;
        }

        function getActiveOrganizationPublicId() {
            return activeOrganizationPublicId;
        }

        function organizationArtifactEndpoint(
            publicId
        ) {
            const normalized =
                normalizeOrganizationPublicId(
                    publicId
                );

            if (!normalized) {
                throw new Error(
                    'Organization public id is required.'
                );
            }

            return (
                ORGANIZATIONS_API_ENDPOINT +
                '/' +
                encodeURIComponent(
                    normalized
                ) +
                '/artifacts'
            );
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

        async function parseJsonResponse(
            response
        ) {
            try {
                return await response.json();
            } catch (error) {
                return null;
            }
        }

        function responseErrorMessage(
            body,
            fallback
        ) {
            return (
                body &&
                body.message
                    ? body.message
                    : fallback
            );
        }

        function csrfToken() {
            if (
                !root.document ||
                typeof root.document.querySelector !==
                    'function'
            ) {
                return null;
            }

            const meta =
                root.document.querySelector(
                    'meta[name="csrf-token"]'
                );

            return meta &&
                meta.getAttribute
                ? meta.getAttribute(
                    'content'
                )
                : null;
        }

        function jsonHeaders() {
            const headers = {
                Accept:
                    'application/json',

                'Content-Type':
                    'application/json'
            };

            const token =
                csrfToken();

            if (token) {
                headers[
                    'X-CSRF-TOKEN'
                ] = token;
            }

            return headers;
        }

        async function discoverOrganizations(
            fetchImpl
        ) {
            const response =
                await fetchImpl(
                    ORGANIZATIONS_API_ENDPOINT,
                    {
                        method:
                            'GET',

                        headers: {
                            Accept:
                                'application/json'
                        },

                        credentials:
                            'same-origin'
                    }
                );

            const body =
                await parseJsonResponse(
                    response
                );

            if (!response.ok) {
                if (
                    response.status ===
                    401
                ) {
                    throw new Error(
                        'Authentification requise avant l’enregistrement dans une organisation Civilina.'
                    );
                }

                throw new Error(
                    responseErrorMessage(
                        body,
                        'Impossible de charger les organisations Civilina.'
                    )
                );
            }

            const organizations =
                body &&
                Array.isArray(
                    body.data
                )
                    ? body.data
                    : [];

            return organizations.filter(
                function (
                    organization
                ) {
                    return (
                        organization &&
                        normalizeOrganizationPublicId(
                            organization.public_id
                        )
                    );
                }
            );
        }

        async function resolveOrganizationPublicId(
            fetchImpl,
            options
        ) {
            const safeOptions =
                options || {};

            const explicit =
                normalizeOrganizationPublicId(
                    safeOptions
                        .organizationPublicId
                );

            if (explicit) {
                setActiveOrganizationPublicId(
                    explicit
                );

                return explicit;
            }

            if (
                activeOrganizationPublicId
            ) {
                return activeOrganizationPublicId;
            }

            const organizations =
                await discoverOrganizations(
                    fetchImpl
                );

            if (
                organizations.length === 1
            ) {
                const resolved =
                    normalizeOrganizationPublicId(
                        organizations[0]
                            .public_id
                    );

                setActiveOrganizationPublicId(
                    resolved
                );

                return resolved;
            }

            if (
                organizations.length === 0
            ) {
                throw new Error(
                    'Aucune organisation active n’est disponible pour enregistrer ce livrable.'
                );
            }

            throw new Error(
                'Plusieurs organisations sont disponibles. Sélectionnez l’organisation active avant l’enregistrement.'
            );
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

            /*
             * Explicit endpoint remains available for QA / controlled
             * legacy compatibility. Normal browser persistence is now
             * organization-scoped.
             */
            let endpoint =
                safeOptions.endpoint ||
                null;

            if (!endpoint) {
                const organizationPublicId =
                    await resolveOrganizationPublicId(
                        fetchImpl,
                        safeOptions
                    );

                endpoint =
                    organizationArtifactEndpoint(
                        organizationPublicId
                    );
            }

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

                        headers:
                            jsonHeaders(),

                        credentials:
                            'same-origin',

                        body:
                            JSON.stringify(
                                requestPayload
                            )
                    }
                );

            const body =
                await parseJsonResponse(
                    response
                );

            if (!response.ok) {
                throw new Error(
                    responseErrorMessage(
                        body,
                        'Artifact persistence failed with HTTP ' +
                        response.status +
                        '.'
                    )
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
            ARTIFACT_API_ENDPOINT:
                LEGACY_ARTIFACT_API_ENDPOINT,

            LEGACY_ARTIFACT_API_ENDPOINT,
            ORGANIZATIONS_API_ENDPOINT,
            normalizeArtifactType,
            normalizeArtifactForPersistence,
            organizationArtifactEndpoint,
            setActiveOrganizationPublicId,
            getActiveOrganizationPublicId,
            discoverOrganizations,
            resolveOrganizationPublicId,
            csrfToken,
            persistArtifact
        };
    }
);
