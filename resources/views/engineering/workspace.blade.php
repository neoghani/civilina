<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta
        name="viewport"
        content="width=device-width, initial-scale=1"
    >
    <meta
        name="csrf-token"
        content="{{ csrf_token() }}"
    >

    <title>
        {{ $project->code }} · Civilina Engineering
    </title>

    <link
        rel="stylesheet"
        href="{{ asset('assets/engineering-workspace.css') }}"
    >
@include('components.civilina-command-theme')
</head>

<body class="cw-body">
@include('engineering._engineering-nav')

    <div
        class="cw-shell"
        data-engineering-workspace
        data-api-base="/api/organizations/{{ $organization->public_id }}/engineering/projects/{{ $project->public_id }}"
        data-engineering-api-base="/api/organizations/{{ $organization->public_id }}/engineering"
        data-can-edit="{{ $canEdit ? '1' : '0' }}"
        data-can-approve="{{ $canApprove ? '1' : '0' }}"
    >
        <aside class="cw-sidebar">
            <a
                class="cw-brand"
                href="{{ route('engineering.normative-v22.index', ['organization' => $organization, 'project' => $project]) }}"
                aria-label="Retour à l'accueil Civilina"
            >
                <div class="cw-brand-mark">C</div>

                <div>
                    <strong>Civilina</strong>
                    <small>Engineering</small>
                </div>
            </a>

            <div class="cw-project-meta">
                <span>Projet</span>
                <strong>
                    {{ $project->code }}
                    ·
                    {{ $project->name }}
                </strong>
            </div>

            <nav
                class="cw-nav"
                aria-label="Navigation Engineering"
            >
                <button
                    class="is-active"
                    type="button"
                    data-workspace-tab="overview"
                >
                    Vue projet
                </button>

                <button
                    type="button"
                    data-workspace-tab="structure"
                >
                    Structure
                </button>

                <button
                    type="button"
                    data-workspace-tab="design-basis"
                >
                    Design Basis
                </button>

                <button
                    type="button"
                    data-workspace-tab="technical"
                >
                    Modèle technique
                </button>

                <button
                    type="button"
                    data-workspace-tab="standards"
                >
                    Normes
                </button>

                <button
                    type="button"
                    data-workspace-tab="analysis"
                >
                    Calcul
                </button>

                <button
                    type="button"
                    data-workspace-tab="checks"
                >
                    Vérifications
                </button>
                            {{-- CIVILINA_QAQC_MENU_START --}}
<details class="civilina-qaqc-menu" data-civilina-qaqc-menu>
    <summary class="civilina-qaqc-menu__summary">
        <span>QA/QC</span>
        <span class="civilina-qaqc-menu__chevron" aria-hidden="true">&#9662;</span>
    </summary>

    <div class="civilina-qaqc-menu__children">
        <a
                    href="{{ route('engineering.itp.index', [$organization->getKey(), $project->getKey()]) }}"
                    data-civilina-block="2k-itp"
                    style="display:block;padding:11px 12px;margin:4px 0;border-radius:7px;color:#fff;text-decoration:none"
                >
                    · ITP
                </a>
        <a
                    href="{{ route('engineering.qa-control.index', [$organization->getKey(), $project->getKey()]) }}"
                    data-civilina-block="2k1-qa-control"
                    style="display:block;padding:11px 12px;margin:4px 0;border-radius:7px;color:#fff;text-decoration:none"
                >
                    · Controles & PV
                </a>
        <a
                    href="{{ route('engineering.normative-library.index', [$organization->getKey(), $project->getKey()]) }}"
                    data-civilina-block="2k2-qa-normative"
                    style="display:block;padding:11px 12px;margin:4px 0;border-radius:7px;color:#fff;text-decoration:none"
                >
                    · Agent normatif
                </a>
    </div>
</details>

<style>
    .civilina-qaqc-menu {
        margin: 2px 0;
        font: inherit;
    }

    .civilina-qaqc-menu__summary {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        list-style: none;
        cursor: pointer;
        user-select: none;
        padding: 10px 12px;
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
        font-weight: 500;
        line-height: 1.35;
        color: inherit;
        transition: background-color .15s ease;
    }

    .civilina-qaqc-menu__summary::-webkit-details-marker {
        display: none;
    }

    .civilina-qaqc-menu__summary:hover,
    .civilina-qaqc-menu[open] > .civilina-qaqc-menu__summary {
        background: rgba(255, 255, 255, .08);
    }

    .civilina-qaqc-menu__chevron {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        font-size: 12px;
        opacity: .72;
        transform: rotate(-90deg);
        transition: transform .15s ease;
    }

    .civilina-qaqc-menu[open] .civilina-qaqc-menu__chevron {
        transform: rotate(0deg);
    }

    .civilina-qaqc-menu__children {
        margin: 4px 0 6px 12px;
        padding-left: 10px;
        border-left: 1px solid rgba(255, 255, 255, .12);
    }

    .civilina-qaqc-menu__children a {
        display: block;
        width: 100%;
        box-sizing: border-box;
        margin: 1px 0;
        padding: 8px 10px !important;
        border-radius: 7px;
        font-family: inherit !important;
        font-size: 14px !important;
        font-weight: 400 !important;
        line-height: 1.35 !important;
        text-decoration: none;
    }

    .civilina-qaqc-menu__children a:hover {
        background: rgba(255, 255, 255, .06);
    }
</style>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        const menu = document.querySelector('[data-civilina-qaqc-menu]');
        if (!menu) return;

        const currentPath = window.location.pathname.replace(/\/+$/, '');

        const hasActiveChild = Array.from(menu.querySelectorAll('a')).some(function (link) {
            try {
                const linkPath = new URL(link.href, window.location.origin).pathname.replace(/\/+$/, '');
                return linkPath === currentPath;
            } catch (e) {
                return false;
            }
        });

        if (hasActiveChild) {
            menu.open = true;
        }
    });
</script>
{{-- CIVILINA_QAQC_MENU_END --}}
                
                
</nav>
        </aside>

        <main class="cw-main">
            <header class="cw-topbar">
                <div>
                    <h1>
                        {{ $project->name }}
                    </h1>

                    <p>
                        Source de vérité technique Civilina
                        ·
                        {{ $organization->name }}
                    </p>
                </div>

                <span class="cw-role">
                    {{ $role }}
                </span>
            </header>

            <div class="cw-content">
                <div
                    class="cw-alert"
                    data-workspace-alert
                    role="status"
                ></div>

                <section
                    class="cw-panel is-active"
                    data-workspace-panel="overview"
                >
                    <div class="cw-section-head">
                        <div>
                            <h2>
                                Engineering Core
                            </h2>
                            <div class="cw-muted">
                                WBS et dimensions physiques séparés,
                                Design Basis révisée,
                                modèle technique versionné,
                                calcul et vérification traçables.
                            </div>
                        </div>

                        <span
                            class="cw-status"
                            data-status="{{ $project->status->value ?? $project->status }}"
                        >
                            {{ $project->status->value ?? $project->status }}
                        </span>
                    </div>

                    <div class="cw-grid cw-grid-kpi">
                        <div class="cw-card cw-kpi">
                            <span>WBS</span>
                            <strong data-kpi="wbs">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Work Packages</span>
                            <strong data-kpi="work-packages">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Activities</span>
                            <strong data-kpi="activities">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Zones</span>
                            <strong data-kpi="zones">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Ouvrages</span>
                            <strong data-kpi="ouvrages">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Matériaux</span>
                            <strong data-kpi="materials">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Design Basis</span>
                            <strong data-kpi="design-basis">—</strong>
                        </div>

                        <div class="cw-card cw-kpi">
                            <span>Objets techniques</span>
                            <strong data-kpi="technical">—</strong>
                        </div>
                    </div>

                    <div class="cw-section-head">
                        <h3>Fil numérique Civilina</h3>
                    </div>

                    <div class="cw-card cw-card-pad">
                        <strong>
                            Besoin → Design Basis → Modèle technique
                            → Robot → Vérification → Plans → Quantités
                            → QA/QC → Validation ingénieur
                        </strong>

                        <p class="cw-muted">
                            Robot, AutoCAD et Excel restent des moteurs
                            ou outils d'exécution. Le modèle technique
                            Civilina demeure la référence.
                        </p>
                    </div>
                </section>

                <section
                    class="cw-panel"
                    data-workspace-panel="structure"
                >
                    <div class="cw-section-head">
                        <h2>
                            Structure projet
                        </h2>
                    </div>

                    @if($canEdit)
                        <div class="cw-two-col">
                            <div>
                    @endif

                    <div class="cw-card">
                        <div class="cw-section-head cw-card-pad">
                            <h3>WBS</h3>
                        </div>
                        <div data-table="wbs">
                            <div
                                class="cw-loading"
                                data-workspace-loading
                            >
                                Chargement…
                            </div>
                        </div>
                    </div>

                    <div class="cw-two-col" style="margin-top:16px">
                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Work Packages</h3>
                            </div>
                            <div data-table="work-packages"></div>
                        </div>

                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Activities</h3>
                            </div>
                            <div data-table="activities"></div>
                        </div>
                    </div>

                    <div class="cw-two-col" style="margin-top:16px">
                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Zones</h3>
                            </div>
                            <div data-table="zones"></div>
                        </div>

                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Ouvrages</h3>
                            </div>
                            <div data-table="ouvrages"></div>
                        </div>
                    </div>

                    <div class="cw-card" style="margin-top:16px">
                        <div class="cw-section-head cw-card-pad">
                            <h3>Matériaux</h3>
                        </div>
                        <div data-table="materials"></div>
                    </div>

                    @if($canEdit)
                            </div>

                            <aside class="cw-card cw-card-pad">
                                <div class="cw-section-head">
                                    <h3>Ajout rapide</h3>
                                </div>

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/wbs-nodes"
                                >
                                    <strong>WBS</strong>

                                    <div class="cw-field">
                                        <label>Code</label>
                                        <input
                                            name="code"
                                            required
                                            placeholder="P-001-STR"
                                        >
                                    </div>

                                    <div class="cw-field">
                                        <label>Nom</label>
                                        <input
                                            name="name"
                                            required
                                            placeholder="Structure"
                                        >
                                    </div>

                                    <div class="cw-field">
                                        <label>Parent ID</label>
                                        <input
                                            name="parent_id"
                                            type="number"
                                            min="1"
                                            placeholder="Optionnel"
                                        >
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter WBS
                                    </button>
                                </form>

                                <hr
                                    style="border:0;border-top:1px solid var(--cw-border);margin:18px 0"
                                >

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/work-packages"
                                >
                                    <strong>Work Package</strong>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Code</label>
                                            <input
                                                name="code"
                                                required
                                                placeholder="WP-STR-001"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Nom</label>
                                            <input
                                                name="name"
                                                required
                                                placeholder="Structure béton"
                                            >
                                        </div>
                                    </div>

                                    <div class="cw-field">
                                        <label>WBS</label>
                                        <select
                                            name="wbs_node_id"
                                            data-select="wbs"
                                            required
                                        ></select>
                                    </div>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Zone</label>
                                            <select
                                                name="zone_id"
                                                data-select="zone"
                                                data-optional="1"
                                            ></select>
                                        </div>

                                        <div class="cw-field">
                                            <label>Ouvrage</label>
                                            <select
                                                name="ouvrage_id"
                                                data-select="ouvrage"
                                                data-optional="1"
                                            ></select>
                                        </div>
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter Work Package
                                    </button>
                                </form>

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/activities"
                                    style="margin-top:18px"
                                >
                                    <strong>Activity</strong>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Code</label>
                                            <input
                                                name="code"
                                                required
                                                placeholder="ACT-STR-001"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Nom</label>
                                            <input
                                                name="name"
                                                required
                                                placeholder="Ferraillage poteaux"
                                            >
                                        </div>
                                    </div>

                                    <div class="cw-field">
                                        <label>Work Package</label>
                                        <select
                                            name="work_package_id"
                                            data-select="work-package"
                                            required
                                        ></select>
                                    </div>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Type</label>
                                            <input
                                                name="activity_type"
                                                placeholder="engineering, construction…"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Statut</label>
                                            <select name="status">
                                                <option value="planned">planned</option>
                                                <option value="ready">ready</option>
                                                <option value="in_progress">in_progress</option>
                                                <option value="on_hold">on_hold</option>
                                                <option value="completed">completed</option>
                                            </select>
                                        </div>
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter Activity
                                    </button>
                                </form>

                                <hr
                                    style="border:0;border-top:1px solid var(--cw-border);margin:18px 0"
                                >

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/zones"
                                >
                                    <strong>Zone</strong>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Code</label>
                                            <input
                                                name="code"
                                                required
                                                placeholder="Z-01"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Nom</label>
                                            <input
                                                name="name"
                                                required
                                                placeholder="Process"
                                            >
                                        </div>
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter zone
                                    </button>
                                </form>

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/ouvrages"
                                    style="margin-top:18px"
                                >
                                    <strong>Ouvrage</strong>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Code</label>
                                            <input
                                                name="code"
                                                required
                                                placeholder="OUV-01"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Nom</label>
                                            <input
                                                name="name"
                                                required
                                                placeholder="Bâtiment pompes"
                                            >
                                        </div>
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter ouvrage
                                    </button>
                                </form>

                                <form
                                    class="cw-form"
                                    data-workspace-form
                                    data-endpoint="/materials"
                                    data-kind="material"
                                    style="margin-top:18px"
                                >
                                    <strong>Matériau</strong>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>Code</label>
                                            <input
                                                name="code"
                                                required
                                                placeholder="C30-37"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Type</label>
                                            <input
                                                name="material_type"
                                                required
                                                placeholder="concrete"
                                            >
                                        </div>
                                    </div>

                                    <div class="cw-field">
                                        <label>Nom</label>
                                        <input
                                            name="name"
                                            required
                                            placeholder="Concrete C30/37"
                                        >
                                    </div>

                                    <div class="cw-field">
                                        <label>Référence</label>
                                        <input
                                            name="standard_reference"
                                            placeholder="EN 206"
                                        >
                                    </div>

                                    <div class="cw-form-row">
                                        <div class="cw-field">
                                            <label>fck MPa</label>
                                            <input
                                                name="fck_mpa"
                                                type="number"
                                                step="0.01"
                                            >
                                        </div>

                                        <div class="cw-field">
                                            <label>Densité kg/m³</label>
                                            <input
                                                name="density_kg_m3"
                                                type="number"
                                                step="0.01"
                                            >
                                        </div>
                                    </div>

                                    <button
                                        class="cw-button"
                                        type="submit"
                                    >
                                        Ajouter matériau
                                    </button>
                                </form>
                            </aside>
                        </div>
                    @endif
                </section>

<section
    class="cw-panel"
    data-workspace-panel="design-basis"
>
    <div class="cw-section-head">
        <div>
            <h2>Design Basis</h2>
            <div class="cw-muted">
                Exigences, contraintes, hypothèses et sources du projet.
            </div>
        </div>
    </div>

    <div class="cw-two-col">
        <div class="cw-card">
            <div data-table="design-basis">
                <div class="cw-loading" data-workspace-loading>
                    Chargement…
                </div>
            </div>
        </div>

        @if($canEdit)
            <aside class="cw-stack">
                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Nouvelle révision</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="create-design-basis">
                        <div class="cw-field">
                            <label>Titre</label>
                            <input name="title" required placeholder="Design Basis — Révision projet">
                        </div>

                        <div class="cw-field">
                            <label>Résumé des changements</label>
                            <textarea name="change_summary" placeholder="Motif et portée de la révision"></textarea>
                        </div>

                        <button class="cw-button" type="submit">Créer la révision</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Ajouter une donnée</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="add-design-basis-item">
                        <div class="cw-field">
                            <label>Révision</label>
                            <select name="revision_id" data-select="design-basis-revision" required></select>
                        </div>

                        <div class="cw-field">
                            <label>Catégorie</label>
                            <select name="category" required>
                                <option value="client_requirement">Exigence client</option>
                                <option value="location">Localisation</option>
                                <option value="climate">Climat</option>
                                <option value="seismic">Sismicité</option>
                                <option value="geotechnical">Géotechnique</option>
                                <option value="code_standard">Norme / code</option>
                                <option value="constraint">Contrainte</option>
                                <option value="assumption">Hypothèse</option>
                            </select>
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Clé</label>
                                <input name="key" required placeholder="design_life_years">
                            </div>
                            <div class="cw-field">
                                <label>Unité</label>
                                <input name="unit" placeholder="ans, MPa, m/s…">
                            </div>
                        </div>

                        <div class="cw-field">
                            <label>Libellé</label>
                            <input name="label" required placeholder="Durée de vie de calcul">
                        </div>

                        <div class="cw-field">
                            <label>Valeur</label>
                            <input name="value" required placeholder="50">
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Type de source</label>
                                <input name="source_type" placeholder="client, standard, survey…">
                            </div>
                            <div class="cw-field">
                                <label>Référence source</label>
                                <input name="source_reference" placeholder="Clause / note / document">
                            </div>
                        </div>

                        <label class="cw-check">
                            <input type="checkbox" name="is_assumption" value="1">
                            <span>Enregistrer explicitement comme hypothèse</span>
                        </label>

                        <button class="cw-button" type="submit">Ajouter la donnée</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Cycle de validation</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="review-design-basis">
                        <div class="cw-field">
                            <label>Révision</label>
                            <select name="revision_id" data-select="design-basis-revision" required></select>
                        </div>
                        <button class="cw-button cw-button-secondary" type="submit">Soumettre en revue</button>
                    </form>

                    @if($canApprove)
                        <form class="cw-form cw-form-spaced" data-engineering-operation="approve-design-basis">
                            <div class="cw-field">
                                <label>Révision</label>
                                <select name="revision_id" data-select="design-basis-review" required></select>
                            </div>
                            <button class="cw-button" type="submit">Approuver</button>
                        </form>
                    @endif
                </div>
            </aside>
        @endif
    </div>
</section>

<section
    class="cw-panel"
    data-workspace-panel="technical"
>
    <div class="cw-section-head">
        <div>
            <h2>Modèle technique Civilina</h2>
            <div class="cw-muted">
                Identité stable des objets, contexte WBS / Zone / Ouvrage et révisions techniques traçables.
            </div>
        </div>
    </div>

    <div class="cw-two-col">
        <div class="cw-card">
            <div data-table="technical">
                <div class="cw-loading" data-workspace-loading>
                    Chargement…
                </div>
            </div>
        </div>

        @if($canEdit)
            <aside class="cw-stack">
                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Nouvel objet technique</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="create-technical-object">
                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Code</label>
                                <input name="code" required placeholder="COL-C01">
                            </div>
                            <div class="cw-field">
                                <label>Type</label>
                                <input name="object_type" required placeholder="column">
                            </div>
                        </div>

                        <div class="cw-field">
                            <label>Nom</label>
                            <input name="name" required placeholder="Poteau C01">
                        </div>

                        <div class="cw-field">
                            <label>Discipline</label>
                            <input name="discipline" required placeholder="structure">
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>WBS</label>
                                <select name="wbs_node_id" data-select="wbs" data-optional="1"></select>
                            </div>
                            <div class="cw-field">
                                <label>Work Package</label>
                                <select name="work_package_id" data-select="work-package" data-optional="1"></select>
                            </div>
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Activity</label>
                                <select name="activity_id" data-select="activity" data-optional="1"></select>
                            </div>
                            <div class="cw-field">
                                <label>Zone</label>
                                <select name="zone_id" data-select="zone" data-optional="1"></select>
                            </div>
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Ouvrage</label>
                                <select name="ouvrage_id" data-select="ouvrage" data-optional="1"></select>
                            </div>
                            <div class="cw-field">
                                <label>Objet parent</label>
                                <select name="parent_id" data-select="technical-object" data-optional="1"></select>
                            </div>
                        </div>

                        <button class="cw-button" type="submit">Créer l’objet</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Nouvelle révision technique</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="create-technical-revision">
                        <div class="cw-field">
                            <label>Objet</label>
                            <select name="object_id" data-select="technical-object" required></select>
                        </div>

                        <div class="cw-field">
                            <label>Matériau</label>
                            <select name="material_id" data-select="material" data-optional="1"></select>
                        </div>

                        <div class="cw-field">
                            <label>Géométrie JSON</label>
                            <textarea name="geometry" data-json required>{"type":"member","length_m":6}</textarea>
                        </div>

                        <div class="cw-field">
                            <label>Propriétés JSON</label>
                            <textarea name="properties" data-json>{}</textarea>
                        </div>

                        <div class="cw-field">
                            <label>Hypothèses JSON</label>
                            <textarea name="assumptions" data-json>{}</textarea>
                        </div>

                        <div class="cw-field">
                            <label>Provenance JSON</label>
                            <textarea name="provenance" data-json>{"source":"civilina_workspace"}</textarea>
                        </div>

                        <div class="cw-field">
                            <label>Résumé des changements</label>
                            <textarea name="change_summary" placeholder="Création / modification de section / hypothèse…"></textarea>
                        </div>

                        <button class="cw-button" type="submit">Créer la révision</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Cycle de validation</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="review-technical-revision">
                        <div class="cw-field">
                            <label>Révision</label>
                            <select name="revision_pair" data-select="technical-revision" required></select>
                        </div>
                        <button class="cw-button cw-button-secondary" type="submit">Soumettre en revue</button>
                    </form>

                    @if($canApprove)
                        <form class="cw-form cw-form-spaced" data-engineering-operation="approve-technical-revision">
                            <div class="cw-field">
                                <label>Révision en revue</label>
                                <select name="revision_pair" data-select="technical-revision-review" required></select>
                            </div>
                            <button class="cw-button" type="submit">Approuver</button>
                        </form>
                    @endif
                </div>
            </aside>
        @endif
    </div>
</section>

                <section
                    class="cw-panel"
                    data-workspace-panel="standards"
                >
                    <div class="cw-section-head">
                        <h2>Engineering Knowledge</h2>
                    </div>

                    <div class="cw-two-col">
                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Bibliothèque normative</h3>
                            </div>
                            <div data-table="standards"></div>
                        </div>

                        <div class="cw-card">
                            <div class="cw-section-head cw-card-pad">
                                <h3>Profils projet</h3>
                            </div>
                            <div data-table="profiles"></div>
                        </div>
                    </div>
                </section>

<section
    class="cw-panel"
    data-workspace-panel="analysis"
>
    <div class="cw-section-head">
        <div>
            <h2>Calcul structurel</h2>
            <div class="cw-muted">
                Un modèle d’analyse Civilina ne référence que des sources techniques approuvées.
            </div>
        </div>
    </div>

    <div class="cw-two-col">
        <div class="cw-card">
            <div data-table="analysis">
                <div class="cw-loading" data-workspace-loading>
                    Chargement…
                </div>
            </div>
        </div>

        @if($canEdit)
            <aside class="cw-stack">
                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Nouveau modèle d’analyse</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="create-analysis-model">
                        <div class="cw-field">
                            <label>Design Basis approuvée</label>
                            <select name="design_basis_revision_id" data-select="design-basis-approved" required></select>
                        </div>

                        <div class="cw-field">
                            <label>Profil normatif approuvé</label>
                            <select name="project_standard_profile_id" data-select="standard-profile-approved" data-optional="1"></select>
                        </div>

                        <div class="cw-form-row">
                            <div class="cw-field">
                                <label>Code</label>
                                <input name="code" required placeholder="RSA-STR-001">
                            </div>
                            <div class="cw-field">
                                <label>Solveur</label>
                                <input name="solver_key" value="robot_structural_analysis" required>
                            </div>
                        </div>

                        <div class="cw-field">
                            <label>Nom</label>
                            <input name="name" required placeholder="Modèle global structure">
                        </div>

                        <div class="cw-field">
                            <label>Métadonnées JSON</label>
                            <textarea name="metadata" data-json>{}</textarea>
                        </div>

                        <button class="cw-button" type="submit">Créer le modèle</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Ajouter un objet approuvé</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="add-analysis-item">
                        <div class="cw-field">
                            <label>Modèle d’analyse</label>
                            <select name="analysis_model_id" data-select="analysis-model" required></select>
                        </div>

                        <div class="cw-field">
                            <label>Révision technique approuvée</label>
                            <select name="technical_object_revision_id" data-select="technical-revision-approved" required></select>
                        </div>

                        <div class="cw-field">
                            <label>Clé externe Robot</label>
                            <input name="external_key" placeholder="COL-C01-R1">
                        </div>

                        <div class="cw-field">
                            <label>Métadonnées JSON</label>
                            <textarea name="metadata" data-json>{}</textarea>
                        </div>

                        <button class="cw-button" type="submit">Ajouter au modèle</button>
                    </form>
                </div>

                <div class="cw-card cw-card-pad">
                    <div class="cw-section-head cw-section-head-first">
                        <h3>Validation et run Robot</h3>
                    </div>

                    <form class="cw-form" data-engineering-operation="review-analysis-model">
                        <div class="cw-field">
                            <label>Modèle</label>
                            <select name="analysis_model_id" data-select="analysis-model" required></select>
                        </div>
                        <button class="cw-button cw-button-secondary" type="submit">Soumettre en revue</button>
                    </form>

                    @if($canApprove)
                        <form class="cw-form cw-form-spaced" data-engineering-operation="approve-analysis-model">
                            <div class="cw-field">
                                <label>Modèle en revue</label>
                                <select name="analysis_model_id" data-select="analysis-model-review" required></select>
                            </div>
                            <button class="cw-button" type="submit">Approuver le modèle</button>
                        </form>
                    @endif

                    <form class="cw-form cw-form-spaced" data-engineering-operation="prepare-analysis-run">
                        <div class="cw-field">
                            <label>Modèle approuvé</label>
                            <select name="analysis_model_id" data-select="analysis-model-approved" required></select>
                        </div>
                        <button class="cw-button" type="submit">Préparer le run Robot</button>
                    </form>
                </div>
            </aside>
        @endif
    </div>
</section>

                <section
                    class="cw-panel"
                    data-workspace-panel="checks"
                >
                    <div class="cw-section-head">
                        <h2>Vérifications Engineering</h2>
                    </div>

                    <div class="cw-card">
                        <div data-table="checks">
                            <div
                                class="cw-loading"
                                data-workspace-loading
                            >
                                Chargement…
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </main>
    </div>

    <script
        src="{{ asset('assets/engineering-workspace.js') }}"
        defer
    ></script>
</body>
</html>
