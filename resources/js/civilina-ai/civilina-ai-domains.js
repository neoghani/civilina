(function (root, factory) {
    'use strict';

    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CivilinaAIDomains = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const BUSINESS_DOMAIN_IDS = Object.freeze({
        PROJECTS: 'projects',
        ORGANISATION: 'organisation',
        MANAGEMENT_SYSTEMS: 'managementSystems',
        DIGITALISATION: 'digitalisation',
        PEOPLE: 'people'
    });

    const CAPABILITY_IDS = Object.freeze({
        PROJECT_CONTROLS: 'projectControls',
        ENGINEERING: 'engineering',
        QUALITY: 'quality',
        PROCUREMENT: 'procurement',
        DATA: 'data'
    });

    const BUSINESS_DOMAINS = Object.freeze({
        projects: { label: 'Projects' },
        organisation: { label: 'Organisation' },
        managementSystems: { label: 'Management Systems / ISO' },
        digitalisation: { label: 'Digitalisation' },
        people: { label: 'People / décisions humaines' }
    });

    const CAPABILITIES = Object.freeze({
        projectControls: { label: 'Project Controls' },
        engineering: { label: 'Engineering' },
        quality: { label: 'Quality' },
        procurement: { label: 'Procurement' },
        data: { label: 'Data' }
    });

    const ENTRY_DOMAINS = Object.freeze(Object.keys(BUSINESS_DOMAINS));

    const RECOMMENDATION_IDS = Object.freeze({
        COST_CONTROL: 'cost-control',
        PLANNING_WBS: 'planning-wbs',
        EXECUTIVE_REPORTING: 'executive-reporting',
        ENGINEERING_DOCUMENTS: 'engineering-documents',
        PROCUREMENT: 'procurement',
        QA_QC: 'qa-qc',
        ROLES: 'roles-responsibilities',
        PROCESS: 'process-management',
        DECISION_WORKFLOWS: 'decision-workflows',
        MANAGEMENT_DASHBOARD: 'management-dashboard',
        DOCUMENT_CONTROL: 'document-control',
        QUALITY_MANAGEMENT: 'quality-management',
        AUDIT_ACTIONS: 'audit-actions',
        DIGITAL_WORKFLOWS: 'digital-workflows',
        AUTOMATION: 'automation',
        DATA_REPORTING: 'data-reporting'
    });

    function getDomainLabel(domainId) {
        return BUSINESS_DOMAINS[domainId] ? BUSINESS_DOMAINS[domainId].label : domainId;
    }

    function getCapabilityLabel(capabilityId) {
        return CAPABILITIES[capabilityId] ? CAPABILITIES[capabilityId].label : capabilityId;
    }

    return {
        BUSINESS_DOMAIN_IDS: BUSINESS_DOMAIN_IDS,
        CAPABILITY_IDS: CAPABILITY_IDS,
        BUSINESS_DOMAINS: BUSINESS_DOMAINS,
        CAPABILITIES: CAPABILITIES,
        ENTRY_DOMAINS: ENTRY_DOMAINS,
        RECOMMENDATION_IDS: RECOMMENDATION_IDS,
        getDomainLabel: getDomainLabel,
        getCapabilityLabel: getCapabilityLabel
    };
});
