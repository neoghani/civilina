(function (root, factory) {
    'use strict';
    const api = factory(root.CivilinaAIReasoning, root.CivilinaAIReasoningV2);
    root.CivilinaAIReasoningRuntime = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function (legacyCore, coreV2) {
    'use strict';

    function evaluate(input) {
        const resultV2 = coreV2.evaluate(input);
        const legacy = resultV2.legacyContract || legacyCore.evaluate(input);
        const peopleGuardrail = Boolean(resultV2.peopleGuardrail || legacy.peopleGuardrail);
        const relatedDomains = Array.from(new Set([
            ...(resultV2.relatedDomains || []),
            ...(legacy.relatedDomains || []),
            ...(peopleGuardrail && resultV2.primaryDomain !== 'people' ? [resultV2.primaryDomain] : [])
        ])).filter(function (domain) { return domain && domain !== (peopleGuardrail ? 'people' : resultV2.primaryDomain); });

        return Object.assign({}, legacy, {
            reasoningCoreVersion: resultV2.reasoningCoreVersion,
            primaryDomain: peopleGuardrail ? 'people' : (resultV2.primaryDomain || legacy.primaryDomain),
            relatedDomains,
            capabilities: resultV2.capabilities.length ? resultV2.capabilities : legacy.capabilities,
            sensitiveIntent: Boolean(resultV2.sensitiveIntent || legacy.sensitiveIntent),
            peopleGuardrail,
            expertSuggested: resultV2.humanExpertRecommended || legacy.expertSuggested,
            v2: resultV2
        });
    }

    return Object.assign({}, legacyCore, {
        REASONING_CORE_VERSION: coreV2.REASONING_CORE_VERSION,
        evaluate
    });
});
