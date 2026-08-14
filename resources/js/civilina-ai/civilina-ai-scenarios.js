(function (root, factory) {
    'use strict';

    const api = factory();
    if (typeof module === 'object' && module.exports) module.exports = api;
    else root.CivilinaAIScenarios = api;
})(typeof globalThis !== 'undefined' ? globalThis : this, function () {
    'use strict';

    const scenarios = {
        projects: {
            label: 'Pilotage de projets',
            intro: 'Commençons par votre environnement projet.',
            questions: [
                {
                    id: 'activity',
                    prompt: 'Dans quel environnement vos projets sont-ils principalement réalisés ?',
                    suggestions: [['Construction / EPC', 'epc'], ['Industrie', 'industry'], ['Services', 'services'], ['Plusieurs activités', 'mixed']]
                },
                {
                    id: 'portfolio',
                    prompt: 'Combien de projets gérez-vous actuellement ?',
                    suggestions: [['1 à 3 projets', 'small'], ['4 à 10 projets', 'medium'], ['Plus de 10 projets', 'large'], ['Un portefeuille mixte', 'mixed']]
                },
                {
                    id: 'tracking',
                    prompt: 'Comment suivez-vous principalement les coûts et les délais ?',
                    suggestions: [['Excel', 'excel'], ['ERP', 'erp'], ['Logiciel projet', 'project-software'], ['Plusieurs outils', 'multiple-tools'], ['Suivi peu formalisé', 'informal']]
                },
                {
                    id: 'difficulty',
                    prompt: 'Quelle difficulté demande le plus d’attention aujourd’hui ?',
                    suggestions: [['Consolider coûts et délais', 'cost-schedule'], ['Fiabiliser le reporting direction', 'executive-reporting'], ['Arbitrer ressources et priorités', 'resources'], ['Maîtriser risques et documents', 'risks-documents']]
                }
            ]
        },
        organisation: {
            label: 'Organisation',
            intro: 'Regardons comment les responsabilités et les décisions circulent.',
            questions: [
                {
                    id: 'size',
                    prompt: 'Quelle est approximativement la taille de votre organisation ?',
                    suggestions: [['Moins de 20 personnes', 'micro'], ['20 à 99 personnes', 'small'], ['100 à 499 personnes', 'medium'], ['500 personnes ou plus', 'large']]
                },
                {
                    id: 'issue',
                    prompt: 'Quel point freine le plus le fonctionnement quotidien ?',
                    suggestions: [['Responsabilités peu claires', 'roles'], ['Validations trop lentes', 'approvals'], ['Processus variables', 'processes'], ['Reporting dispersé', 'reporting']]
                },
                {
                    id: 'decisions',
                    prompt: 'Comment les validations et décisions sont-elles principalement suivies ?',
                    suggestions: [['E-mails', 'email'], ['Échanges verbaux', 'verbal'], ['Fichiers Excel', 'excel'], ['Workflow formalisé', 'workflow']]
                },
                {
                    id: 'objective',
                    prompt: 'Quel résultat souhaitez-vous obtenir en priorité ?',
                    suggestions: [['Clarifier les rôles', 'clarify-roles'], ['Standardiser les processus', 'standardize'], ['Accélérer les décisions', 'faster-decisions'], ['Créer un tableau de bord direction', 'management-dashboard']]
                }
            ]
        },
        managementSystems: {
            label: 'Management Systems / ISO',
            intro: 'Précisons le référentiel et la maturité du système de management.',
            questions: [
                {
                    id: 'reference',
                    prompt: 'Quel référentiel ou objectif souhaitez-vous travailler ?',
                    suggestions: [['ISO 9001', 'iso-9001'], ['ISO 14001 / ISO 45001', 'hse'], ['Système intégré', 'integrated'], ['Structurer la qualité sans certification', 'quality-structure']]
                },
                {
                    id: 'maturity',
                    prompt: 'Où en est actuellement votre système de management ?',
                    suggestions: [['Démarrage', 'starting'], ['Documenté mais peu utilisé', 'documented'], ['Déployé mais fragmenté', 'fragmented'], ['En amélioration continue', 'improving']]
                },
                {
                    id: 'difficulty',
                    prompt: 'Quel point demande le plus de structuration ?',
                    suggestions: [['Documents et versions', 'documents'], ['Audits et actions', 'audits-actions'], ['Indicateurs', 'indicators'], ['Rôles et responsabilités', 'roles']]
                },
                {
                    id: 'tools',
                    prompt: 'Comment suivez-vous aujourd’hui les documents, actions et indicateurs ?',
                    suggestions: [['Papier et dossiers partagés', 'paper-files'], ['Excel', 'excel'], ['Logiciel QMS', 'qms'], ['Plusieurs outils', 'multiple-tools']]
                }
            ]
        },
        digitalisation: {
            label: 'Digitalisation',
            intro: 'Partons du processus réel avant de parler d’outil.',
            questions: [
                {
                    id: 'process',
                    prompt: 'Quel processus souhaitez-vous digitaliser en priorité ?',
                    suggestions: [['Validations internes', 'approvals'], ['Documents et demandes', 'documents'], ['Reporting', 'reporting'], ['Achats / Procurement', 'procurement'], ['Opérations terrain', 'field']]
                },
                {
                    id: 'tools',
                    prompt: 'Quels outils utilisez-vous principalement pour ce processus ?',
                    suggestions: [['Papier et e-mail', 'paper-email'], ['Excel', 'excel'], ['ERP', 'erp'], ['Plusieurs outils', 'multiple-tools'], ['Aucun outil structuré', 'none']]
                },
                {
                    id: 'difficulty',
                    prompt: 'Quelle difficulté voulez-vous réduire en premier ?',
                    suggestions: [['Double saisie', 'reentry'], ['Délais de validation', 'delays'], ['Manque de traçabilité', 'traceability'], ['Accès et consolidation', 'access-consolidation']]
                },
                {
                    id: 'objective',
                    prompt: 'Quel résultat concret attendez-vous ?',
                    suggestions: [['Créer un workflow', 'workflow'], ['Automatiser les tâches répétitives', 'automation'], ['Centraliser les documents', 'centralize'], ['Construire un dashboard', 'dashboard']]
                }
            ]
        },
        people: {
            label: 'People / décisions humaines',
            intro: 'Précisons le contexte humain sans réduire une décision à un profil.',
            questions: [
                {
                    id: 'peopleObjective',
                    prompt: 'Quel est l’objectif principal de votre réflexion ?',
                    suggestions: [['Développer la communication', 'communication'], ['Accompagner une évolution', 'development'], ['Améliorer la collaboration', 'collaboration'], ['Éclairer une décision de recrutement ou d’affectation', 'staffing-decision']]
                },
                {
                    id: 'peopleScope',
                    prompt: 'Quel est le périmètre concerné ?',
                    suggestions: [['Une personne', 'individual'], ['Une équipe', 'team'], ['Un poste', 'position'], ['Des candidats', 'candidates']]
                },
                {
                    id: 'peopleCriteria',
                    prompt: 'Quels éléments objectifs sont déjà disponibles ?',
                    suggestions: [['Compétences et expérience', 'skills-experience'], ['Performance et responsabilités', 'performance-responsibilities'], ['Exigences du poste', 'role-requirements'], ['Éléments encore incomplets', 'incomplete']]
                }
            ]
        }
    };

    const initialOptions = [
        { label: 'Piloter mes projets', value: 'Je veux mieux piloter mes projets', scenario: 'projects' },
        { label: 'Structurer mon organisation', value: 'Je veux structurer mon organisation', scenario: 'organisation' },
        { label: 'Digitaliser mes processus', value: 'Je veux digitaliser mes processus', scenario: 'digitalisation' },
        { label: 'Améliorer mon système ISO', value: 'Je veux améliorer mon système ISO', scenario: 'managementSystems' }
    ];

    return { scenarios: scenarios, initialOptions: initialOptions };
});
