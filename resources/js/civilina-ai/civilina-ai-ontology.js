(function (root, factory) {
    'use strict';
    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CivilinaAIOntology = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const VERSION = '2.0.0';

    const INTERACTION_MODES = Object.freeze(['diagnose', 'explain', 'review', 'critique', 'create', 'design', 'prioritize', 'compare', 'plan', 'audit', 'troubleshoot', 'calculate', 'decide']);
    const PERSONAS = Object.freeze(['executive', 'projectManager', 'projectControls', 'pmo', 'engineeringManager', 'siteManager', 'qualityManager', 'hseManager', 'procurementManager', 'contractManager', 'isoManager', 'digitalManager', 'peopleManager', 'unknown']);
    const BUSINESS_SCOPES = Object.freeze(['enterprise', 'portfolio', 'program', 'project', 'wbs', 'workPackage', 'activity', 'ouvrage', 'zone', 'process', 'department', 'team', 'person']);
    const BUSINESS_DOMAINS = Object.freeze({
        executiveControl: { label: 'Executive Control' },
        projects: { label: 'Projects' },
        organisation: { label: 'Organisation' },
        managementSystems: { label: 'Management Systems / ISO' },
        digitalisation: { label: 'Digitalisation' },
        people: { label: 'People / décisions humaines' }
    });
    const CAPABILITIES = Object.freeze({
        projectControls: { label: 'Project Controls' },
        planning: { label: 'Planning' },
        costControl: { label: 'Cost Control' },
        riskManagement: { label: 'Risk Management' },
        changeControl: { label: 'Change Control' },
        contractsClaims: { label: 'Contracts & Claims' },
        engineering: { label: 'Engineering' },
        technicalDocumentManagement: { label: 'Technical Document Management' },
        procurement: { label: 'Procurement' },
        quality: { label: 'Quality' },
        hse: { label: 'HSE' },
        construction: { label: 'Construction' },
        resourcesProductivity: { label: 'Resources & Productivity' },
        documentControl: { label: 'Document Control' },
        processManagement: { label: 'Process Management' },
        dataReporting: { label: 'Data & Reporting' },
        integrationAutomation: { label: 'Integration & Automation' }
    });
    const BUSINESS_ENTITIES = Object.freeze(['portfolio', 'program', 'project', 'wbs', 'workPackage', 'activity', 'ouvrage', 'zone', 'baseline', 'milestone', 'document', 'drawing', 'rfi', 'submittal', 'transmittal', 'contract', 'claim', 'change', 'risk', 'purchaseOrder', 'material', 'supplier', 'subcontractor', 'inspection', 'itp', 'ncr', 'correctiveAction', 'resource', 'equipment', 'cost', 'commitment', 'progress', 'kpi', 'decision']);
    const CONTROL_DIMENSIONS = Object.freeze(['scope', 'time', 'cost', 'progress', 'quality', 'risk', 'change', 'contract', 'procurement', 'resource', 'engineering', 'document', 'safety', 'data', 'decision']);
    const ANSWERABILITY_STATUSES = Object.freeze(['CAN_ANSWER', 'NEED_CLARIFICATION', 'NEED_DATA', 'NEED_DOCUMENT', 'EXTERNAL_VERIFICATION_REQUIRED', 'HUMAN_EXPERT_RECOMMENDED', 'OUT_OF_SCOPE']);
    const DECISION_HORIZONS = Object.freeze(['today', 'thisWeek', 'thisMonth', 'nextMilestone', 'projectCompletion', 'strategic', 'unknown']);
    const EXPERT_LENSES = Object.freeze(['Executive', 'Project Controls', 'Engineering', 'Construction', 'Quality', 'HSE', 'Procurement', 'Contracts', 'ISO', 'Organisation', 'Digital', 'Data', 'People']);

    const CONCEPT_MAP = Object.freeze({
        projectControls: { domains: ['projects'], capabilities: ['projectControls'], dimensions: ['time', 'cost', 'progress', 'data'] },
        evm: { domains: ['projects', 'executiveControl'], capabilities: ['projectControls', 'costControl', 'dataReporting'], dimensions: ['time', 'cost', 'progress', 'decision'], entities: ['kpi', 'cost', 'progress'] },
        wbs: { domains: ['projects'], capabilities: ['projectControls', 'planning'], dimensions: ['scope', 'time', 'cost', 'progress'], entities: ['wbs'] },
        planning: { domains: ['projects'], capabilities: ['planning', 'projectControls'], dimensions: ['time'], entities: ['baseline', 'milestone'] },
        costControl: { domains: ['projects', 'executiveControl'], capabilities: ['costControl', 'projectControls'], dimensions: ['cost'], entities: ['cost', 'commitment'] },
        progressControl: { domains: ['projects'], capabilities: ['projectControls', 'quality'], dimensions: ['progress', 'quality'], entities: ['progress'] },
        riskManagement: { domains: ['projects', 'executiveControl'], capabilities: ['riskManagement'], dimensions: ['risk'], entities: ['risk'] },
        changeControl: { domains: ['projects'], capabilities: ['changeControl'], dimensions: ['change', 'cost', 'time'], entities: ['change'] },
        contractsClaims: { domains: ['projects'], capabilities: ['contractsClaims', 'changeControl'], dimensions: ['contract', 'change', 'time', 'cost'], entities: ['contract', 'claim', 'change'] },
        engineering: { domains: ['projects'], capabilities: ['engineering'], dimensions: ['engineering'], entities: ['drawing'] },
        technicalDocuments: { domains: ['projects'], capabilities: ['engineering', 'technicalDocumentManagement'], dimensions: ['engineering', 'document'], entities: ['document', 'drawing'] },
        documentControl: { domains: ['managementSystems', 'digitalisation'], capabilities: ['documentControl'], dimensions: ['document', 'data'], entities: ['document'] },
        quality: { domains: ['managementSystems', 'projects'], capabilities: ['quality'], dimensions: ['quality'], entities: ['inspection', 'itp', 'ncr', 'correctiveAction'] },
        hse: { domains: ['managementSystems', 'projects'], capabilities: ['hse'], dimensions: ['safety', 'risk'], entities: ['correctiveAction'] },
        construction: { domains: ['projects'], capabilities: ['construction'], dimensions: ['scope', 'progress', 'resource'], entities: ['activity', 'ouvrage', 'zone'] },
        productivity: { domains: ['projects'], capabilities: ['construction', 'resourcesProductivity', 'projectControls'], dimensions: ['resource', 'progress'], entities: ['resource', 'equipment', 'progress'] },
        procurement: { domains: ['projects'], capabilities: ['procurement'], dimensions: ['procurement', 'time', 'cost'], entities: ['purchaseOrder', 'material', 'supplier'] },
        organisation: { domains: ['organisation'], capabilities: ['processManagement'], dimensions: ['decision'], entities: ['decision'] },
        managementSystem: { domains: ['managementSystems'], capabilities: ['quality', 'processManagement'], dimensions: ['quality', 'risk', 'document'] },
        digitalisation: { domains: ['digitalisation'], capabilities: ['integrationAutomation'], dimensions: ['data'] },
        dataGovernance: { domains: ['digitalisation', 'organisation'], capabilities: ['dataReporting', 'integrationAutomation'], dimensions: ['data', 'decision'], entities: ['kpi'] },
        executiveControl: { domains: ['executiveControl'], capabilities: ['dataReporting', 'projectControls'], dimensions: ['decision', 'cost', 'time', 'risk'], entities: ['portfolio', 'decision'] },
        people: { domains: ['people', 'organisation'], capabilities: ['processManagement'], dimensions: ['decision'], entities: ['decision'] }
    });

    const RECOMMENDATIONS = Object.freeze({
        'executive-control': { label: 'Executive Control', capability: 'dataReporting', concepts: ['executiveControl', 'evm'], minEvidence: 1 },
        'wbs-integration': { label: 'WBS Integration', capability: 'projectControls', concepts: ['wbs'], minEvidence: 1 },
        'evm-decision-support': { label: 'EVM Decision Support', capability: 'projectControls', concepts: ['evm'], minEvidence: 1 },
        'schedule-assurance': { label: 'Schedule Assurance', capability: 'planning', concepts: ['planning'], minEvidence: 1, blockedBy: ['CAUSE_UNKNOWN'] },
        'cost-forecast-control': { label: 'Cost & Forecast Control', capability: 'costControl', concepts: ['costControl'], minEvidence: 1 },
        'progress-governance': { label: 'Progress Governance', capability: 'projectControls', concepts: ['progressControl'], minEvidence: 1 },
        'risk-control': { label: 'Risk Control', capability: 'riskManagement', concepts: ['riskManagement'], minEvidence: 1 },
        'change-claims-control': { label: 'Change, Claims & Contracts Control', capability: 'contractsClaims', concepts: ['changeControl', 'contractsClaims'], minEvidence: 1 },
        'engineering-flow': { label: 'Engineering Deliverables Flow', capability: 'technicalDocumentManagement', concepts: ['engineering', 'technicalDocuments'], minEvidence: 1 },
        'document-governance': { label: 'Document Control Governance', capability: 'documentControl', concepts: ['documentControl', 'technicalDocuments'], minEvidence: 1 },
        'quality-control-integration': { label: 'Quality Control Integration', capability: 'quality', concepts: ['quality'], minEvidence: 1 },
        'hse-action-control': { label: 'HSE Action Control', capability: 'hse', concepts: ['hse'], minEvidence: 1 },
        'construction-productivity': { label: 'Construction Productivity', capability: 'resourcesProductivity', concepts: ['construction', 'productivity'], minEvidence: 1 },
        'procurement-schedule': { label: 'Procurement Schedule Integration', capability: 'procurement', concepts: ['procurement'], minEvidence: 1 },
        'organisation-governance': { label: 'Organisation & Decision Governance', capability: 'processManagement', concepts: ['organisation'], minEvidence: 1 },
        'management-system': { label: 'Management System', capability: 'quality', concepts: ['managementSystem'], minEvidence: 1 },
        'data-governance': { label: 'Data Governance', capability: 'dataReporting', concepts: ['dataGovernance'], minEvidence: 1 },
        'integration-architecture': { label: 'Integration Architecture', capability: 'integrationAutomation', concepts: ['digitalisation'], minEvidence: 1 },
        'people-decision-framework': { label: 'People Decision Framework', capability: 'processManagement', concepts: ['people'], minEvidence: 1 }
    });

    function label(type, id) {
        const registry = type === 'domain' ? BUSINESS_DOMAINS : CAPABILITIES;
        return registry[id] ? registry[id].label : id;
    }

    return {
        VERSION,
        INTERACTION_MODES,
        PERSONAS,
        BUSINESS_SCOPES,
        BUSINESS_DOMAINS,
        CAPABILITIES,
        BUSINESS_ENTITIES,
        CONTROL_DIMENSIONS,
        ANSWERABILITY_STATUSES,
        DECISION_HORIZONS,
        EXPERT_LENSES,
        CONCEPT_MAP,
        RECOMMENDATIONS,
        label
    };
});
