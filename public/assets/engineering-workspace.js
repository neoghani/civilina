(function () {
    'use strict';

    const root = document.querySelector('[data-engineering-workspace]');
    if (!root) return;

    const projectBase = root.dataset.apiBase;
    const engineeringBase = root.dataset.engineeringApiBase;
    const canEdit = root.dataset.canEdit === '1';
    const canApprove = root.dataset.canApprove === '1';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const state = {
        structure: null,
        designBasis: [],
        technicalObjects: [],
        standardProfiles: [],
        standards: [],
        analysisModels: [],
        checks: [],
    };

    function escapeHtml(value) {
        return String(value ?? '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function enumValue(value) {
        if (value && typeof value === 'object' && 'value' in value) return value.value;
        return value;
    }

    function status(value) {
        const safe = escapeHtml(enumValue(value) || 'unknown');
        return '<span class="cw-status" data-status="' + safe + '">' + safe + '</span>';
    }

    function empty(message) {
        return '<div class="cw-empty">' + escapeHtml(message) + '</div>';
    }

    function table(headers, rows) {
        if (!rows.length) return empty('Aucune donnée enregistrée.');
        return '<div class="cw-table-wrap"><table class="cw-table"><thead><tr>' +
            headers.map((header) => '<th>' + escapeHtml(header) + '</th>').join('') +
            '</tr></thead><tbody>' + rows.join('') + '</tbody></table></div>';
    }

    async function apiRequest(base, path, options = {}) {
        const method = options.method || 'GET';
        const headers = {
            Accept: 'application/json',
            ...(options.headers || {}),
        };

        if (method !== 'GET') {
            headers['Content-Type'] = 'application/json';
            headers['X-CSRF-TOKEN'] = csrf;
        }

        const response = await fetch(base + path, {
            credentials: 'same-origin',
            ...options,
            method,
            headers,
        });

        const payload = await response.json().catch(() => ({}));
        if (!response.ok) {
            const errors = payload.errors
                ? Object.values(payload.errors).flat().join(' ')
                : null;
            const error = new Error(errors || payload.message || 'Erreur API Civilina.');
            error.status = response.status;
            error.payload = payload;
            throw error;
        }

        return payload.data;
    }

    function requestProject(path, options = {}) {
        return apiRequest(projectBase, path, options);
    }

    function requestEngineering(path, options = {}) {
        return apiRequest(engineeringBase, path, options);
    }

    function showAlert(message, kind = 'success') {
        const alert = document.querySelector('[data-workspace-alert]');
        if (!alert) return;
        alert.className = 'cw-alert is-visible ' + (kind === 'error' ? 'is-error' : 'is-success');
        alert.textContent = message;
        window.setTimeout(() => { alert.className = 'cw-alert'; }, 4500);
    }

    function itemLabel(items, id) {
        if (!id) return '—';
        const item = items.find((candidate) => String(candidate.id) === String(id));
        return item ? item.code + ' · ' + item.name : String(id);
    }

    function renderStructure() {
        const data = state.structure;
        if (!data) return;

        const workPackages = data.work_packages || [];
        const activities = data.activities || [];

        document.querySelector('[data-kpi="wbs"]').textContent = data.wbs_nodes.length;
        document.querySelector('[data-kpi="work-packages"]').textContent = workPackages.length;
        document.querySelector('[data-kpi="activities"]').textContent = activities.length;
        document.querySelector('[data-kpi="zones"]').textContent = data.zones.length;
        document.querySelector('[data-kpi="ouvrages"]').textContent = data.ouvrages.length;
        document.querySelector('[data-kpi="materials"]').textContent = data.materials.length;

        document.querySelector('[data-table="wbs"]').innerHTML = table(
            ['Code', 'Nom', 'Parent'],
            data.wbs_nodes.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td><td>' +
                escapeHtml(itemLabel(data.wbs_nodes, item.parent_id)) + '</td></tr>')
        );

        document.querySelector('[data-table="work-packages"]').innerHTML = table(
            ['Code', 'Work Package', 'WBS', 'Zone', 'Ouvrage', 'Statut'],
            workPackages.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td><td>' +
                escapeHtml(itemLabel(data.wbs_nodes, item.wbs_node_id)) + '</td><td>' +
                escapeHtml(itemLabel(data.zones, item.zone_id)) + '</td><td>' +
                escapeHtml(itemLabel(data.ouvrages, item.ouvrage_id)) + '</td><td>' +
                status(item.status) + '</td></tr>')
        );

        document.querySelector('[data-table="activities"]').innerHTML = table(
            ['Code', 'Activity', 'Work Package', 'Type', 'Zone', 'Statut'],
            activities.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td><td>' +
                escapeHtml(itemLabel(workPackages, item.work_package_id)) + '</td><td>' +
                escapeHtml(item.activity_type || '—') + '</td><td>' +
                escapeHtml(itemLabel(data.zones, item.zone_id)) + '</td><td>' +
                status(item.status) + '</td></tr>')
        );

        document.querySelector('[data-table="zones"]').innerHTML = table(
            ['Code', 'Zone'],
            data.zones.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td></tr>')
        );

        document.querySelector('[data-table="ouvrages"]').innerHTML = table(
            ['Code', 'Ouvrage'],
            data.ouvrages.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td></tr>')
        );

        document.querySelector('[data-table="materials"]').innerHTML = table(
            ['Code', 'Matériau', 'Type', 'Référence'],
            data.materials.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.name) + '</td><td>' + escapeHtml(item.material_type) +
                '</td><td>' + escapeHtml(item.standard_reference || '—') + '</td></tr>')
        );
    }

    function renderDesignBasis() {
        document.querySelector('[data-kpi="design-basis"]').textContent = state.designBasis.length;
        document.querySelector('[data-table="design-basis"]').innerHTML = table(
            ['Révision', 'Titre', 'Statut', 'Items', 'Sources'],
            state.designBasis.map((item) => {
                const sourced = (item.items || []).filter((row) => row.source_reference).length;
                return '<tr><td>R' + escapeHtml(item.revision_no) + '</td><td>' + escapeHtml(item.title) +
                    '</td><td>' + status(item.status) + '</td><td>' + escapeHtml(item.items?.length || 0) +
                    '</td><td>' + escapeHtml(sourced) + '</td></tr>';
            })
        );
    }

    function renderTechnical() {
        document.querySelector('[data-kpi="technical"]').textContent = state.technicalObjects.length;
        const structure = state.structure || {
            wbs_nodes: [],
            work_packages: [],
            activities: [],
            zones: [],
            ouvrages: [],
        };

        document.querySelector('[data-table="technical"]').innerHTML = table(
            ['Code', 'Objet', 'Type', 'WBS', 'Work Package', 'Activity', 'Zone', 'Ouvrage', 'Révision', 'Statut'],
            state.technicalObjects.map((item) => {
                const latest = item.revisions?.[0];
                return '<tr><td class="cw-mono">' + escapeHtml(item.code) + '</td><td>' + escapeHtml(item.name) +
                    '</td><td>' + escapeHtml(item.object_type) + '</td><td>' +
                    escapeHtml(itemLabel(structure.wbs_nodes || [], item.wbs_node_id)) + '</td><td>' +
                    escapeHtml(itemLabel(structure.work_packages || [], item.work_package_id)) + '</td><td>' +
                    escapeHtml(itemLabel(structure.activities || [], item.activity_id)) + '</td><td>' +
                    escapeHtml(itemLabel(structure.zones || [], item.zone_id)) + '</td><td>' +
                    escapeHtml(itemLabel(structure.ouvrages || [], item.ouvrage_id)) + '</td><td>' +
                    (latest ? 'R' + escapeHtml(latest.revision_no) : '—') + '</td><td>' +
                    (latest ? status(latest.status) : '—') + '</td></tr>';
            })
        );
    }

    function renderStandards() {
        document.querySelector('[data-table="standards"]').innerHTML = table(
            ['Code', 'Document', 'Éditions'],
            state.standards.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.code) +
                '</td><td>' + escapeHtml(item.title) + '</td><td>' + escapeHtml(item.editions?.length || 0) + '</td></tr>')
        );

        document.querySelector('[data-table="profiles"]').innerHTML = table(
            ['Révision', 'Profil', 'Statut', 'Standards'],
            state.standardProfiles.map((item) => '<tr><td>R' + escapeHtml(item.revision_no) + '</td><td>' +
                escapeHtml(item.name) + '</td><td>' + status(item.status) + '</td><td>' +
                escapeHtml(item.standards?.length || 0) + '</td></tr>')
        );
    }

    function renderAnalysis() {
        document.querySelector('[data-table="analysis"]').innerHTML = table(
            ['Code', 'Modèle', 'Solveur', 'Statut', 'Objets', 'Runs', 'Dernier run'],
            state.analysisModels.map((item) => {
                const latestRun = item.runs?.[item.runs.length - 1];
                return '<tr><td class="cw-mono">' + escapeHtml(item.code) + '</td><td>' + escapeHtml(item.name) +
                    '</td><td>' + escapeHtml(item.solver_key) + '</td><td>' + status(item.status) + '</td><td>' +
                    escapeHtml(item.items?.length || 0) + '</td><td>' + escapeHtml(item.runs?.length || 0) +
                    '</td><td>' + (latestRun ? status(latestRun.status) : '—') + '</td></tr>';
            })
        );
    }

    function renderChecks() {
        document.querySelector('[data-table="checks"]').innerHTML = table(
            ['Code', 'Vérification', 'Outcome', 'Statut', 'Utilisation', 'Findings'],
            state.checks.map((item) => '<tr><td class="cw-mono">' + escapeHtml(item.check_code) + '</td><td>' +
                escapeHtml(item.title) + '</td><td>' + status(item.outcome) + '</td><td>' + status(item.status) +
                '</td><td>' + (item.utilization_ratio !== null ? escapeHtml(Number(item.utilization_ratio).toFixed(3)) : '—') +
                '</td><td>' + escapeHtml(item.findings?.length || 0) + '</td></tr>')
        );
    }

    function option(value, label, selectedValue) {
        const selected = String(value) === String(selectedValue) ? ' selected' : '';
        return '<option value="' + escapeHtml(value) + '"' + selected + '>' + escapeHtml(label) + '</option>';
    }

    function setOptions(selector, items, labelFn, { optional = false } = {}) {
        document.querySelectorAll('[data-select="' + selector + '"]').forEach((select) => {
            const previous = select.value;
            const includeEmpty = optional || select.dataset.optional === '1';
            let html = includeEmpty ? '<option value="">— Aucun —</option>' : '<option value="">— Sélectionner —</option>';
            html += items.map((item) => option(item.value, item.label, previous)).join('');
            select.innerHTML = html;
            select.disabled = items.length === 0 && !includeEmpty;
        });
    }

    function technicalRevisions(filterStatus = null) {
        const rows = [];
        state.technicalObjects.forEach((object) => {
            (object.revisions || []).forEach((revision) => {
                const value = enumValue(revision.status);
                if (filterStatus && value !== filterStatus) return;
                rows.push({
                    value: object.id + ':' + revision.id,
                    revisionId: revision.id,
                    label: object.code + ' · R' + revision.revision_no + ' · ' + value,
                });
            });
        });
        return rows;
    }

    function refreshSelectors() {
        const structure = state.structure || { wbs_nodes: [], work_packages: [], activities: [], zones: [], ouvrages: [], materials: [] };

        setOptions('wbs', structure.wbs_nodes.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('work-package', (structure.work_packages || []).map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('activity', (structure.activities || []).map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('zone', structure.zones.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('ouvrage', structure.ouvrages.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('material', structure.materials.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });

        setOptions('design-basis-revision', state.designBasis.map((x) => ({ value: x.id, label: 'R' + x.revision_no + ' · ' + x.title + ' · ' + enumValue(x.status) })));
        setOptions('design-basis-review', state.designBasis.filter((x) => enumValue(x.status) === 'review').map((x) => ({ value: x.id, label: 'R' + x.revision_no + ' · ' + x.title })));
        setOptions('design-basis-approved', state.designBasis.filter((x) => enumValue(x.status) === 'approved').map((x) => ({ value: x.id, label: 'R' + x.revision_no + ' · ' + x.title })));

        setOptions('technical-object', state.technicalObjects.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })), null, { optional: true });
        setOptions('technical-revision', technicalRevisions());
        setOptions('technical-revision-review', technicalRevisions('review'));
        setOptions('technical-revision-approved', technicalRevisions('approved').map((x) => ({ value: x.revisionId, label: x.label })));

        setOptions('standard-profile-approved', state.standardProfiles.filter((x) => enumValue(x.status) === 'approved').map((x) => ({ value: x.id, label: 'R' + x.revision_no + ' · ' + x.name })), null, { optional: true });
        setOptions('analysis-model', state.analysisModels.map((x) => ({ value: x.id, label: x.code + ' · ' + x.name + ' · ' + enumValue(x.status) })));
        setOptions('analysis-model-review', state.analysisModels.filter((x) => enumValue(x.status) === 'review').map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })));
        setOptions('analysis-model-approved', state.analysisModels.filter((x) => enumValue(x.status) === 'approved').map((x) => ({ value: x.id, label: x.code + ' · ' + x.name })));
    }

    async function loadAll() {
        const [structure, designBasis, technicalObjects, standardProfiles, standards, analysisModels, checks] = await Promise.all([
            requestProject('/structure'),
            requestProject('/design-basis'),
            requestProject('/technical-objects'),
            requestProject('/standard-profiles'),
            requestEngineering('/knowledge/standards'),
            requestProject('/analysis-models'),
            requestProject('/checks'),
        ]);

        Object.assign(state, { structure, designBasis, technicalObjects, standardProfiles, standards, analysisModels, checks });
        renderAll();
        document.querySelectorAll('[data-workspace-loading]').forEach((node) => node.remove());
    }

    function renderAll() {
        renderStructure();
        renderDesignBasis();
        renderTechnical();
        renderStandards();
        renderAnalysis();
        renderChecks();
        refreshSelectors();
    }

    async function reloadProjectState() {
        const [structure, designBasis, technicalObjects, standardProfiles, analysisModels, checks] = await Promise.all([
            requestProject('/structure'),
            requestProject('/design-basis'),
            requestProject('/technical-objects'),
            requestProject('/standard-profiles'),
            requestProject('/analysis-models'),
            requestProject('/checks'),
        ]);
        Object.assign(state, { structure, designBasis, technicalObjects, standardProfiles, analysisModels, checks });
        renderAll();
    }

    function formPayload(form) {
        const data = new FormData(form);
        const payload = {};

        for (const [key, raw] of data.entries()) {
            if (String(raw).trim() === '') continue;
            const field = form.elements.namedItem(key);
            if (field?.dataset?.json !== undefined) {
                try {
                    payload[key] = JSON.parse(raw);
                } catch {
                    throw new Error('JSON invalide dans le champ « ' + key + ' ».');
                }
            } else {
                payload[key] = raw;
            }
        }

        form.querySelectorAll('input[type="checkbox"]').forEach((checkbox) => {
            payload[checkbox.name] = checkbox.checked;
        });

        return payload;
    }

    function numericIds(payload, keys) {
        keys.forEach((key) => {
            if (payload[key] !== undefined && payload[key] !== '') payload[key] = Number(payload[key]);
        });
        return payload;
    }

    async function submitLegacyForm(event) {
        event.preventDefault();
        if (!canEdit) return;

        const form = event.currentTarget;
        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        try {
            const payload = formPayload(form);
            if (form.dataset.kind === 'material') {
                payload.properties = {};
                if (payload.fck_mpa) {
                    payload.properties.fck_mpa = Number(payload.fck_mpa);
                    delete payload.fck_mpa;
                }
                if (payload.density_kg_m3) {
                    payload.properties.density_kg_m3 = Number(payload.density_kg_m3);
                    delete payload.density_kg_m3;
                }
            }
            if (payload.sort_order) payload.sort_order = Number(payload.sort_order);
            numericIds(payload, ['parent_id', 'wbs_node_id', 'work_package_id', 'activity_id', 'zone_id', 'ouvrage_id']);
            await requestProject(form.dataset.endpoint, { method: 'POST', body: JSON.stringify(payload) });
            form.reset();
            await reloadProjectState();
            showAlert('Donnée Engineering enregistrée.');
        } catch (error) {
            showAlert(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    function splitRevisionPair(value) {
        const [objectId, revisionId] = String(value || '').split(':').map(Number);
        if (!objectId || !revisionId) throw new Error('Révision technique invalide.');
        return { objectId, revisionId };
    }

    async function runOperation(operation, payload) {
        switch (operation) {
            case 'create-design-basis':
                return requestProject('/design-basis', { method: 'POST', body: JSON.stringify(payload) });

            case 'add-design-basis-item': {
                const revisionId = Number(payload.revision_id);
                delete payload.revision_id;
                return requestProject('/design-basis/' + revisionId + '/items', { method: 'POST', body: JSON.stringify(payload) });
            }

            case 'review-design-basis':
            case 'approve-design-basis': {
                const revisionId = Number(payload.revision_id);
                const action = operation === 'review-design-basis' ? 'review' : 'approve';
                return requestProject('/design-basis/' + revisionId + '/' + action, { method: 'POST', body: '{}' });
            }

            case 'create-technical-object':
                numericIds(payload, ['parent_id', 'wbs_node_id', 'work_package_id', 'activity_id', 'zone_id', 'ouvrage_id']);
                return requestProject('/technical-objects', { method: 'POST', body: JSON.stringify(payload) });

            case 'create-technical-revision': {
                const objectId = Number(payload.object_id);
                delete payload.object_id;
                numericIds(payload, ['material_id']);
                return requestProject('/technical-objects/' + objectId + '/revisions', { method: 'POST', body: JSON.stringify(payload) });
            }

            case 'review-technical-revision':
            case 'approve-technical-revision': {
                const pair = splitRevisionPair(payload.revision_pair);
                const action = operation === 'review-technical-revision' ? 'review' : 'approve';
                return requestProject('/technical-objects/' + pair.objectId + '/revisions/' + pair.revisionId + '/' + action, { method: 'POST', body: '{}' });
            }

            case 'create-analysis-model':
                numericIds(payload, ['design_basis_revision_id', 'project_standard_profile_id']);
                return requestProject('/analysis-models', { method: 'POST', body: JSON.stringify(payload) });

            case 'add-analysis-item': {
                const analysisId = Number(payload.analysis_model_id);
                delete payload.analysis_model_id;
                numericIds(payload, ['technical_object_revision_id']);
                return requestProject('/analysis-models/' + analysisId + '/items', { method: 'POST', body: JSON.stringify(payload) });
            }

            case 'review-analysis-model':
            case 'approve-analysis-model':
            case 'prepare-analysis-run': {
                const analysisId = Number(payload.analysis_model_id);
                const action = operation === 'review-analysis-model'
                    ? 'review'
                    : operation === 'approve-analysis-model' ? 'approve' : 'runs';
                return requestProject('/analysis-models/' + analysisId + '/' + action, { method: 'POST', body: '{}' });
            }

            default:
                throw new Error('Opération Engineering inconnue.');
        }
    }

    async function submitOperation(event) {
        event.preventDefault();
        if (!canEdit) return;

        const form = event.currentTarget;
        const operation = form.dataset.engineeringOperation;
        if (operation.startsWith('approve-') && !canApprove) return;

        const button = form.querySelector('button[type="submit"]');
        button.disabled = true;

        try {
            const payload = formPayload(form);
            await runOperation(operation, payload);
            await reloadProjectState();
            showAlert('Opération Engineering enregistrée.');
        } catch (error) {
            showAlert(error.message, 'error');
        } finally {
            button.disabled = false;
        }
    }

    document.querySelectorAll('[data-workspace-tab]').forEach((button) => {
        button.addEventListener('click', () => {
            document.querySelectorAll('[data-workspace-tab]').forEach((node) => node.classList.remove('is-active'));
            document.querySelectorAll('[data-workspace-panel]').forEach((node) => node.classList.remove('is-active'));
            button.classList.add('is-active');
            document.querySelector('[data-workspace-panel="' + button.dataset.workspaceTab + '"]')?.classList.add('is-active');
        });
    });

    document.querySelectorAll('[data-workspace-form]').forEach((form) => form.addEventListener('submit', submitLegacyForm));
    document.querySelectorAll('[data-engineering-operation]').forEach((form) => form.addEventListener('submit', submitOperation));

    loadAll().catch((error) => showAlert(error.message, 'error'));
})();
