(function (root, factory) {
    'use strict';

    const isCommonJs =
        typeof module === 'object' &&
        module.exports;

    const api = factory();

    if (isCommonJs) {
        module.exports = api;
    } else {
        root.CivilinaAIArtifactContext = api;
    }

})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    /*
     * CIVILINA Artifact Context / Affinity
     *
     * Purpose:
     * Refine generic reasoning dimensions when Civilina has identified
     * a specific business artifact.
     *
     * Example:
     * "Cree un ITP pour le betonnage"
     *
     * Generic quality recognition may legitimately activate:
     * - managementSystems
     * - projects
     * - inspection
     * - itp
     * - ncr
     * - correctiveAction
     *
     * But the requested artifact gives stronger contextual evidence:
     * - primary domain = projects
     * - capability = quality
     * - working scope = activity
     * - ITP / inspection are relevant entities
     * - NCR / correctiveAction must not be inferred unless explicitly
     *   mentioned by the user.
     *
     * This layer does NOT change the generic ontology.
     * It applies artifact-specific contextual affinity after the
     * generic dimensions have been built.
     */

    const ARTIFACT_CONTEXT_PROFILES = Object.freeze({
        itp: Object.freeze({
            primaryDomain: 'projects',
            relatedDomains: [
                'managementSystems'
            ],
            capabilities: [
                'quality'
            ],
            fallbackScope: 'activity',
            includeEntities: [
                'itp',
                'inspection'
            ],
            suppressUnlessExplicit: [
                'ncr',
                'correctiveAction'
            ]
        }),

        wbs: Object.freeze({
            primaryDomain: 'projects',
            relatedDomains: [],
            capabilities: [
                'projectControls'
            ],
            fallbackScope: 'project',
            includeEntities: [
                'wbs'
            ],
            suppressUnlessExplicit: []
        }),

        riskRegister: Object.freeze({
            primaryDomain: 'projects',
            relatedDomains: [],
            capabilities: [
                'riskManagement'
            ],
            fallbackScope: 'project',
            includeEntities: [
                'risk'
            ],
            suppressUnlessExplicit: []
        })
    });

    function unique(values) {
        return Array.from(
            new Set(
                (values || []).filter(Boolean)
            )
        );
    }

    function explicitEntities(recognition) {
        if (
            !recognition ||
            !recognition.byCategory
        ) {
            return [];
        }

        const entitySignals =
            recognition.byCategory.entity || [];

        const scopeSignals =
            recognition.byCategory.scope || [];

        return unique(
            entitySignals.concat(
                scopeSignals
            )
        );
    }

    function refineScope(
        scope,
        profile
    ) {
        const nextScope =
            Object.assign(
                {},
                scope || {}
            );

        if (
            !nextScope.level ||
            nextScope.level === 'enterprise'
        ) {
            nextScope.level =
                profile.fallbackScope ||
                nextScope.level ||
                'enterprise';
        }

        return nextScope;
    }

    function refineEntities(
        currentEntities,
        profile,
        recognition
    ) {
        const explicit =
            explicitEntities(
                recognition
            );

        const suppressed =
            profile.suppressUnlessExplicit || [];

        return unique(
            (currentEntities || [])
                .concat(
                    profile.includeEntities || []
                )
        ).filter(function (entityId) {
            if (
                !suppressed.includes(entityId)
            ) {
                return true;
            }

            return explicit.includes(entityId);
        });
    }

    function refineDomains(
        dimensions,
        profile
    ) {
        const previousPrimary =
            dimensions.primaryDomain || null;

        const primaryDomain =
            profile.primaryDomain ||
            previousPrimary;

        const relatedDomains =
            unique(
                []
                    .concat(
                        profile.relatedDomains || []
                    )
                    .concat(
                        previousPrimary &&
                        previousPrimary !== primaryDomain
                            ? [previousPrimary]
                            : []
                    )
                    .concat(
                        dimensions.relatedDomains || []
                    )
            ).filter(function (domainId) {
                return domainId !== primaryDomain;
            });

        return {
            primaryDomain,
            relatedDomains
        };
    }

    function refine(
        requestedArtifact,
        dimensions,
        recognition
    ) {
        if (
            !requestedArtifact ||
            !requestedArtifact.id ||
            !dimensions
        ) {
            return dimensions;
        }

        const profile =
            ARTIFACT_CONTEXT_PROFILES[
                requestedArtifact.id
            ];

        if (!profile) {
            return dimensions;
        }

        const domains =
            refineDomains(
                dimensions,
                profile
            );

        const entities =
    refineEntities(
        dimensions.scope &&
        Array.isArray(dimensions.scope.entities)
            ? dimensions.scope.entities
            : [],
        profile,
        recognition
    );

        const scope =
            refineScope(
                dimensions.scope,
                profile
            );

        scope.entities =
            entities.slice();

        return Object.assign(
            {},
            dimensions,
            {
                primaryDomain:
                    domains.primaryDomain,

                relatedDomains:
                    domains.relatedDomains,

                capabilities:
                    unique(
                        []
                            .concat(
                                profile.capabilities || []
                            )
                            .concat(
                                dimensions.capabilities || []
                            )
                    ),

                entities,

                scope,

                artifactContext: {
                    applied: true,
                    artifactId:
                        requestedArtifact.id,
                    profile:
                        requestedArtifact.id
                }
            }
        );
    }

    return {
        ARTIFACT_CONTEXT_PROFILES,
        refine
    };
});