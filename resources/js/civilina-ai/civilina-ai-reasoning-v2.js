(function (root, factory) {
    'use strict';

    const isCommonJs =
        typeof module === 'object' &&
        module.exports;

    const api = factory(
        isCommonJs
            ? require('./civilina-ai-ontology.js')
            : root.CivilinaAIOntology,

        isCommonJs
            ? require('./civilina-ai-recognizer.js')
            : root.CivilinaAIRecognizer,

        isCommonJs
            ? require('./civilina-ai-action-recognizer.js')
            : root.CivilinaAIActionRecognizer,

        isCommonJs
            ? require('./civilina-ai-artifact-recognizer.js')
            : root.CivilinaAIArtifactRecognizer,

        isCommonJs
            ? require('./civilina-ai-artifact-requirements.js')
            : root.CivilinaAIArtifactRequirements,

        isCommonJs
            ? require('./civilina-ai-facts-v2.js')
            : root.CivilinaAIFactsV2,

        isCommonJs
            ? require('./civilina-ai-questions-v2.js')
            : root.CivilinaAIQuestionsV2,

        isCommonJs
            ? require('./civilina-ai-reasoning.js')
            : root.CivilinaAIReasoning
    );

    if (isCommonJs) {
        module.exports = api;
    } else {
        root.CivilinaAIReasoningV2 = api;
    }

})(
    typeof globalThis !== 'undefined'
        ? globalThis
        : this,

    function (
        ontology,
        recognizer,
        actionRecognizer,
        artifactRecognizer,
        artifactRequirements,
        factEngine,
        questionEngine,
        legacyReasoning
    ) {
        'use strict';

        const REASONING_CORE_VERSION =
            '2.0.0';

        const REVIEW_MODES = [
            'review',
            'critique',
            'audit'
        ];

        const CREATION_MODES = [
            'create',
            'design'
        ];

        function unique(values) {
            return Array.from(
                new Set(values)
            );
        }

        function evidenceForRecommendation(
            definition,
            dimensions
        ) {
            return dimensions.evidence.filter(
                function (item) {
                    return definition.concepts.includes(
                        item.concept
                    );
                }
            );
        }

        function rankRecommendations(
            dimensions
        ) {
            const candidates =
                Object.keys(
                    ontology.RECOMMENDATIONS
                )
                    .map(
                        function (id) {
                            const definition =
                                ontology.RECOMMENDATIONS[id];

                            const evidence =
                                evidenceForRecommendation(
                                    definition,
                                    dimensions
                                );

                            const conceptMatches =
                                definition.concepts.filter(
                                    function (concept) {
                                        return dimensions.concepts.includes(
                                            concept
                                        );
                                    }
                                );

                            const blocked =
                                (
                                    definition.blockedBy ||
                                    []
                                ).some(
                                    function (
                                        uncertainty
                                    ) {
                                        return dimensions
                                            .uncertainties
                                            .some(
                                                function (
                                                    item
                                                ) {
                                                    return (
                                                        item.id ===
                                                        uncertainty
                                                    );
                                                }
                                            );
                                    }
                                );

                            const score =
                                conceptMatches.length *
                                    12 +
                                evidence.length *
                                    4 +
                                (
                                    dimensions
                                        .capabilities
                                        .includes(
                                            definition.capability
                                        )
                                        ? 3
                                        : 0
                                );

                            return {
                                id,
                                label:
                                    definition.label,
                                capability:
                                    definition.capability,
                                score,
                                blocked,
                                evidence,
                                justification:
                                    evidence.length
                                        ? 'Recommandation reliée à un signal métier explicite.'
                                        : null
                            };
                        }
                    )
                    .filter(
                        function (item) {
                            return (
                                item.score > 0 &&
                                item.evidence.length >
                                    0 &&
                                item.justification
                            );
                        }
                    )
                    .sort(
                        function (
                            left,
                            right
                        ) {
                            return (
                                right.score -
                                    left.score ||
                                left.id.localeCompare(
                                    right.id
                                )
                            );
                        }
                    );

            if (
                dimensions.peopleGuardrail
            ) {
                return candidates
                    .filter(
                        function (item) {
                            return (
                                item.id !==
                                'people-decision-framework'
                            );
                        }
                    )
                    .slice(0, 2)
                    .map(
                        function (item) {
                            return Object.assign(
                                {},
                                item,
                                {
                                    priority:
                                        'explore'
                                }
                            );
                        }
                    );
            }

            return candidates
                .slice(0, 5)
                .map(
                    function (
                        item,
                        index
                    ) {
                        return Object.assign(
                            {},
                            item,
                            {
                                priority:
                                    index < 3
                                        ? 'immediate'
                                        : 'explore'
                            }
                        );
                    }
                );
        }

        function answerability(
            dimensions,
            evidencePack
        ) {
            if (
                dimensions.externalVerificationRequired
            ) {
                return 'EXTERNAL_VERIFICATION_REQUIRED';
            }

            if (
                dimensions.safetyCritical ||
                dimensions.contractInterpretation ||
                dimensions.peopleGuardrail
            ) {
                return 'HUMAN_EXPERT_RECOMMENDED';
            }

            if (
                REVIEW_MODES.includes(
                    dimensions.interactionMode
                ) &&
                !dimensions.hasDocumentReference
            ) {
                return 'NEED_DOCUMENT';
            }

            if (
                dimensions.interactionMode ===
                    'calculate' &&
                !dimensions.hasNumbers
            ) {
                return 'NEED_DATA';
            }

            if (
                CREATION_MODES.includes(
                    dimensions.interactionMode
                ) &&
                (
                    !dimensions.hasDocumentReference ||
                    !dimensions.controlDimensions.includes(
                        'decision'
                    )
                )
            ) {
                return 'NEED_CLARIFICATION';
            }

            if (
                dimensions.uncertainties.some(
                    function (item) {
                        return item.blocking;
                    }
                )
            ) {
                return 'NEED_CLARIFICATION';
            }

            if (
                evidencePack &&
                evidencePack.required.length >
                    dimensions.evidence.length +
                        2
            ) {
                return 'NEED_DATA';
            }

            if (
                !dimensions.primaryDomain
            ) {
                return 'OUT_OF_SCOPE';
            }

            return 'CAN_ANSWER';
        }

        function expertLens(
            dimensions
        ) {
            const capabilityToLens = {
                contractsClaims:
                    'Contracts & Claims',

                hse:
                    'HSE',

                quality:
                    'QA/QC',

                procurement:
                    'Procurement',

                technicalDocumentManagement:
                    'Engineering',

                planning:
                    'Project Controls',

                costControl:
                    'Project Controls',

                projectControls:
                    'Project Controls',

                resourcesProductivity:
                    'Construction',

                dataReporting:
                    'Data Governance',

                processManagement:
                    'Organisation',

                integrationAutomation:
                    'Digital Transformation'
            };

            return unique(
                dimensions.capabilities
                    .map(
                        function (id) {
                            return capabilityToLens[
                                id
                            ];
                        }
                    )
                    .filter(Boolean)
            ).slice(0, 3);
        }

        function suggestedArtifact(
            dimensions
        ) {
            const mode =
                dimensions.interactionMode;

            if (
                mode === 'audit'
            ) {
                return 'grille de revue et registre des écarts';
            }

            if (
                mode === 'compare'
            ) {
                return 'matrice de comparaison avec critères et hypothèses';
            }

            if (
                mode === 'prioritize'
            ) {
                return 'matrice de priorisation traçable';
            }

            if (
                CREATION_MODES.includes(
                    mode
                )
            ) {
                return 'structure de livrable à valider avant production';
            }

            if (
                dimensions.concepts.includes(
                    'wbs'
                )
            ) {
                return 'dictionnaire WBS et matrice WBS-zone-ouvrage';
            }

            if (
                dimensions.concepts.includes(
                    'evm'
                )
            ) {
                return 'fiche EVM à date de statut avec hypothèses';
            }

            if (
                dimensions.concepts.includes(
                    'contractsClaims'
                )
            ) {
                return 'chronologie factuelle et registre de preuves';
            }

            return 'note de décision structurée';
        }

        function decisionNeeds(
            dimensions
        ) {
            const needs = [];

            if (
                dimensions.controlDimensions.includes(
                    'decision'
                )
            ) {
                needs.push(
                    'clarifier la décision et son responsable'
                );
            }

            if (
                dimensions.decisionHorizon !==
                'unknown'
            ) {
                needs.push(
                    'tenir compte de l’horizon ' +
                        dimensions.decisionHorizon
                );
            }

            if (
                dimensions.uncertainties.length
            ) {
                needs.push(
                    'distinguer les faits, hypothèses et informations manquantes'
                );
            }

            if (
                dimensions.peopleGuardrail
            ) {
                needs.push(
                    'fonder la décision sur des critères professionnels vérifiables'
                );
            }

            return needs;
        }

        function cautions(
            dimensions,
            status
        ) {
            const items = [];

            if (
                dimensions.sensitiveIntent
            ) {
                items.push(
                    'Ne transmettez pas de données confidentielles dans cette démonstration locale.'
                );
            }

            if (
                dimensions.peopleGuardrail
            ) {
                items.push(
                    'Le MBTI ne doit jamais être utilisé seul pour recruter, promouvoir ou affecter une personne.'
                );
            }

            if (
                dimensions.safetyCritical
            ) {
                items.push(
                    'La maîtrise immédiate du danger prime sur toute analyse différée.'
                );
            }

            if (
                dimensions.contractInterpretation
            ) {
                items.push(
                    'Une interprétation contractuelle engageante requiert le contrat, les preuves et un expert habilité.'
                );
            }

            if (
                status ===
                'EXTERNAL_VERIFICATION_REQUIRED'
            ) {
                items.push(
                    'La version actuelle du référentiel ou de la réglementation doit être vérifiée auprès d’une source officielle.'
                );
            }

            if (
                dimensions.suspectedCauses.length
            ) {
                items.push(
                    'Une cause suspectée ne doit pas être présentée comme un fait confirmé.'
                );
            }

            return items;
        }

        function missingData(
            dimensions,
            evidencePack,
            status
        ) {
            const missing = [];

            if (
                status ===
                'NEED_DOCUMENT'
            ) {
                missing.push(
                    'document source, version et critères de revue'
                );
            }

            if (
                status ===
                'NEED_DATA'
            ) {
                missing.push.apply(
                    missing,
                    evidencePack
                        ? evidencePack.required
                        : [
                            'valeurs, unités, date de statut et hypothèses'
                        ]
                );
            }

            dimensions.uncertainties.forEach(
                function (item) {
                    missing.push(
                        item.id
                    );
                }
            );

            if (
                dimensions.interactionMode ===
                    'calculate' &&
                !dimensions.hasNumbers
            ) {
                missing.push(
                    'données chiffrées comparables'
                );
            }

            return unique(
                missing
            );
        }

        function responseBlueprint(
            dimensions,
            status,
            nextQuestion,
            evidencePack,
            artifactReadiness
        ) {
            const canAnswerNow =
                status ===
                'CAN_ANSWER';

            const sections = [
                'Contexte compris',
                'Faits et preuves',
                'Incertitudes'
            ];

            if (canAnswerNow) {
                sections.push(
                    'Lecture métier',
                    'Options et arbitrages',
                    'Prochaine action'
                );
            } else {
                sections.push(
                    'Information nécessaire avant conclusion'
                );
            }

            /*
             * For artifact creation,
             * the artifact-specific requirement
             * should become the preferred question.
             *
             * Generic V2 questioning remains
             * available as fallback.
             */
            const artifactQuestion =
                artifactReadiness &&
                artifactReadiness
                    .nextRequirement
                    ? artifactReadiness
                        .nextRequirement
                        .question
                    : null;

            return {
                canAnswerNow,

                answerabilityStatus:
                    status,

                interactionMode:
                    dimensions.interactionMode,

                objective:
                    canAnswerNow
                        ? 'Préparer une décision explicable sans dépasser les preuves disponibles.'
                        : 'Réduire l’incertitude avant de produire une conclusion.',

                mustAsk:
                    artifactQuestion ||
                    (
                        nextQuestion
                            ? nextQuestion.text
                            : null
                    ),

                missingData:
                    missingData(
                        dimensions,
                        evidencePack,
                        status
                    ),

                answerSections:
                    sections,

                cautions:
                    cautions(
                        dimensions,
                        status
                    ),

                suggestedArtifact:
                    suggestedArtifact(
                        dimensions
                    ),

                expertLens:
                    expertLens(
                        dimensions
                    )
            };
        }

        function evaluate(input) {
            const safeInput =
                typeof input ===
                'string'
                    ? {
                        freeText:
                            input
                    }
                    : Object.assign(
                        {
                            freeText:
                                '',
                            answers:
                                {}
                        },
                        input || {}
                    );

            /*
             * STEP 1
             * Understand explicit user action.
             */
            const actionRecognition =
                actionRecognizer.detect(
                    safeInput.freeText
                );

            /*
             * STEP 2
             * Understand requested deliverable.
             */
            const artifactRecognition =
                artifactRecognizer.detect(
                    safeInput.freeText
                );

            /*
             * STEP 3
             * Understand business context.
             */
            const recognition =
                recognizer.recognize(
                    safeInput.freeText
                );

            /*
             * An explicit action command
             * overrides generic lexical mode.
             */
            if (
                actionRecognition.primaryMode
            ) {
                recognition
                    .byCategory
                    .interactionMode = [
                        actionRecognition.primaryMode
                    ];
            }

            /*
             * STEP 4
             * Build business reasoning dimensions.
             */
            const dimensions =
                factEngine.buildDimensions(
                    recognition,
                    safeInput
                );

            /*
             * STEP 5
             * Assess artifact production readiness.
             */
            const artifactReadiness =
                artifactRequirements.evaluate(
                    artifactRecognition.primary,
                    safeInput
                );

            const evidencePack =
                factEngine.evidencePackFor(
                    dimensions
                );

            const status =
                answerability(
                    dimensions,
                    evidencePack
                );

            const nextQuestion =
                questionEngine.select(
                    dimensions,
                    safeInput.answers ||
                        {}
                );

            const recommendations =
                rankRecommendations(
                    dimensions
                );

            let legacy =
                null;

            try {
                legacy =
                    legacyReasoning &&
                    legacyReasoning.evaluate
                        ? legacyReasoning.evaluate(
                            safeInput
                        )
                        : null;
            } catch (error) {
                legacy =
                    null;
            }

            return {
                reasoningCoreVersion:
                    REASONING_CORE_VERSION,

                interactionMode:
                    dimensions.interactionMode,

                requestedArtifact:
                    artifactRecognition.primary,

                /*
                 * NEW SPRINT 2.3 CONTRACT
                 *
                 * Tells the orchestration layer:
                 * - whether Civilina has enough information,
                 * - what is already known,
                 * - what is missing,
                 * - what should be asked next.
                 */
                artifactReadiness,

                persona:
                    dimensions.persona,

                scope:
                    dimensions.scope,

                primaryDomain:
                    dimensions.primaryDomain,

                relatedDomains:
                    dimensions.relatedDomains,

                capabilities:
                    dimensions.capabilities,

                controlDimensions:
                    dimensions.controlDimensions,

                entities:
                    dimensions.scope.entities,

                symptoms:
                    dimensions.symptoms,

                suspectedCauses:
                    dimensions.suspectedCauses,

                confirmedCauses:
                    dimensions.confirmedCauses,

                evidence:
                    dimensions.evidence,

                uncertainties:
                    dimensions.uncertainties,

                decisionNeeds:
                    decisionNeeds(
                        dimensions
                    ),

                decisionHorizon:
                    dimensions.decisionHorizon,

                answerabilityStatus:
                    status,

                recommendations,

                immediateRecommendations:
                    recommendations.filter(
                        function (item) {
                            return (
                                item.priority ===
                                'immediate'
                            );
                        }
                    ),

                exploreRecommendations:
                    recommendations.filter(
                        function (item) {
                            return (
                                item.priority ===
                                'explore'
                            );
                        }
                    ),

                nextBestQuestion:
                    nextQuestion,

                responseBlueprint:
                    responseBlueprint(
                        dimensions,
                        status,
                        nextQuestion,
                        evidencePack,
                        artifactReadiness
                    ),

                sensitiveIntent:
                    dimensions.sensitiveIntent,

                peopleGuardrail:
                    dimensions.peopleGuardrail,

                safetyCritical:
                    dimensions.safetyCritical,

                externalVerificationRequired:
                    dimensions.externalVerificationRequired,

                humanExpertRecommended:
                    [
                        'HUMAN_EXPERT_RECOMMENDED',
                        'EXTERNAL_VERIFICATION_REQUIRED'
                    ].includes(
                        status
                    ),

                recognizedConcepts:
                    dimensions.concepts,

                legacyContract:
                    legacy
            };
        }

        return {
            REASONING_CORE_VERSION,
            evaluate,
            rankRecommendations,
            answerability,
            responseBlueprint
        };
    }
);