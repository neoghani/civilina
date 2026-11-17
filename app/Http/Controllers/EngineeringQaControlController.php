<?php

namespace App\Http\Controllers;

use App\Models\EngineeringItp;
use App\Models\EngineeringQaEvidence;
use App\Models\EngineeringQaHandover;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaRegisterEntry;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringQaInspectionService;
use App\Services\Engineering\EngineeringQaWorkTypeCatalog;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EngineeringQaControlController extends Controller
{
    public function index(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaWorkTypeCatalog $catalog
    ): View {
        [$organizationModel, $projectModel] = $this->resolveScope('view', $organization, $project);

        $inspections = EngineeringQaInspection::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->with(['evidences', 'registerEntries', 'itp'])
            ->orderByDesc('id')
            ->get();

        $handovers = EngineeringQaHandover::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->orderByDesc('id')
            ->get();

        $itps = EngineeringItp::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->orderBy('code')
            ->get();

        return view('engineering.qa_control.index', [
            'organization' => $organizationModel,
            'project' => $projectModel,
            'inspections' => $inspections,
            'handovers' => $handovers,
            'itps' => $itps,
            'workTypes' => $catalog->workTypes(),
            'registerSchemas' => $catalog->registerSchemas(),
        ]);
    }

    public function generate(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspectionService $service,
        EngineeringQaWorkTypeCatalog $catalog
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);

        $validated = $request->validate([
            'work_type' => ['required', Rule::in(array_keys($catalog->workTypes()))],
            'scope_type' => ['required', Rule::in(['project', 'work_package', 'activity', 'zone', 'ouvrage'])],
            'scope_ref' => ['nullable', 'string', 'max:120'],
            'scope_label' => ['required', 'string', 'max:255'],
            'engineering_itp_id' => ['nullable', 'integer'],
        ]);

        $itp = null;
        if (! empty($validated['engineering_itp_id'])) {
            $itp = EngineeringItp::query()
                ->where('organization_id', (string) $organizationModel->getKey())
                ->where('project_id', (string) $projectModel->getKey())
                ->whereKey($validated['engineering_itp_id'])
                ->firstOrFail();
        }

        $result = $service->generate(
            (string) $organizationModel->getKey(),
            (string) $projectModel->getKey(),
            $validated['work_type'],
            $validated['scope_type'],
            $validated['scope_ref'] ?? null,
            $validated['scope_label'],
            $itp,
            $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
        );

        return back()->with(
            'success',
            count($result['inspections']).' contrôles/essais générés'.
            (count($result['handovers']) ? ' + '.count($result['handovers']).' handover.' : '.')
        );
    }

    public function updateInspection(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspection $inspection
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertInspectionScope($inspection, $organizationModel, $projectModel);

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EngineeringQaInspection::STATUS_PLANNED,
                EngineeringQaInspection::STATUS_REQUESTED,
                EngineeringQaInspection::STATUS_CONFORMING,
                EngineeringQaInspection::STATUS_NONCONFORMING,
                EngineeringQaInspection::STATUS_CONDITIONAL,
                EngineeringQaInspection::STATUS_CANCELLED,
            ])],
            'authorization_status' => ['required', Rule::in([
                EngineeringQaInspection::AUTH_NONE,
                EngineeringQaInspection::AUTH_REQUESTED,
                EngineeringQaInspection::AUTH_GRANTED,
                EngineeringQaInspection::AUTH_REJECTED,
            ])],
            'requested_at' => ['nullable', 'date'],
            'planned_at' => ['nullable', 'date'],
            'inspected_at' => ['nullable', 'date'],
            'received_at' => ['nullable', 'date'],
            'client_authorized_at' => ['nullable', 'date'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'inspector' => ['nullable', 'string', 'max:255'],
            'client_representative' => ['nullable', 'string', 'max:255'],
            'acceptance_criteria' => ['nullable', 'string', 'max:4000'],
            'requirement_source' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        if (
            $validated['authorization_status'] === EngineeringQaInspection::AUTH_GRANTED
            && empty($validated['client_authorized_at'])
        ) {
            return back()->withErrors([
                'client_authorized_at' => 'La date/heure d’autorisation client est obligatoire lorsque l’autorisation est accordée.',
            ])->withInput();
        }

        // A verdict (conforming / non-conforming / conditional) or a client
        // authorization decision is an approval: editors may prepare the
        // record, only approvers may set or withdraw a decision.
        if (
            $this->changesDecision($inspection->status, $validated['status'], self::INSPECTION_DECISIONS)
            || $this->changesDecision($inspection->authorization_status, $validated['authorization_status'], self::AUTHORIZATION_DECISIONS)
        ) {
            $this->resolveScope('approve', $organization, $project);
        }

        $inspection->update($validated);

        return back()->with('success', 'Contrôle / essai mis à jour.');
    }

    public function uploadEvidence(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspection $inspection
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertInspectionScope($inspection, $organizationModel, $projectModel);

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'document_type' => ['required', 'string', 'max:120'],
            'issued_at' => ['required', 'date'],
            'revision' => ['nullable', 'string', 'max:40'],
            'status' => ['required', Rule::in(['draft', 'valid', 'rejected'])],
            'notes' => ['nullable', 'string', 'max:2000'],
            'evidence' => ['required', 'file', 'max:20480', 'mimes:pdf,jpg,jpeg,png,webp'],
        ]);

        $file = $request->file('evidence');
        $disk = 'local';
        $directory = 'engineering/qa/'.(string) $organizationModel->getKey().'/'.(string) $projectModel->getKey().'/'.$inspection->uuid;
        $storedName = now()->format('Ymd_His').'_'.bin2hex(random_bytes(4)).'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs($directory, $storedName, $disk);

        $sha256 = hash_file('sha256', $file->getRealPath());

        EngineeringQaEvidence::create([
            'engineering_qa_inspection_id' => $inspection->getKey(),
            'organization_id' => (string) $organizationModel->getKey(),
            'project_id' => (string) $projectModel->getKey(),
            'reference' => $validated['reference'],
            'document_type' => $validated['document_type'],
            'issued_at' => $validated['issued_at'],
            'revision' => $validated['revision'] ?? null,
            'original_name' => $file->getClientOriginalName(),
            'disk' => $disk,
            'path' => $path,
            'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => $sha256,
            'status' => $validated['status'],
            'uploaded_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'PV / preuve enregistré avec empreinte SHA-256.');
    }

    public function downloadEvidence(
        string $organization,
        string $project,
        EngineeringQaEvidence $evidence
    ): StreamedResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('view', $organization, $project);

        abort_unless(
            (string) $evidence->organization_id === (string) $organizationModel->getKey()
            && (string) $evidence->project_id === (string) $projectModel->getKey(),
            404
        );

        abort_unless(Storage::disk($evidence->disk)->exists($evidence->path), 404);

        return Storage::disk($evidence->disk)->download($evidence->path, $evidence->original_name);
    }

    public function addRegisterEntry(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspection $inspection,
        EngineeringQaWorkTypeCatalog $catalog
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertInspectionScope($inspection, $organizationModel, $projectModel);

        $schema = $catalog->registerSchema($inspection->register_type);
        abort_unless($schema !== null, 422, 'Aucun registre structuré n’est associé à ce contrôle.');

        $validated = $request->validate([
            'reference' => ['required', 'string', 'max:255'],
            'entry_date' => ['required', 'date'],
            'quality_status' => ['required', Rule::in(['pending', 'conforming', 'nonconforming', 'conditional'])],
            'source_reference' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'payload' => ['required', 'array'],
        ]);

        $payload = [];
        foreach (array_keys($schema['fields']) as $field) {
            $value = trim((string) ($validated['payload'][$field] ?? ''));
            if ($value !== '') {
                $payload[$field] = $value;
            }
        }

        if ($payload === []) {
            return back()->withErrors(['payload' => 'Renseignez au moins une donnée de suivi.'])->withInput();
        }

        EngineeringQaRegisterEntry::create([
            'engineering_qa_inspection_id' => $inspection->getKey(),
            'organization_id' => (string) $organizationModel->getKey(),
            'project_id' => (string) $projectModel->getKey(),
            'work_type' => $inspection->work_type,
            'register_type' => $inspection->register_type,
            'reference' => $validated['reference'],
            'entry_date' => $validated['entry_date'],
            'scope_label' => $inspection->scope_label,
            'payload' => $payload,
            'quality_status' => $validated['quality_status'],
            'source_reference' => $validated['source_reference'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
        ]);

        return back()->with('success', 'Ligne ajoutée au tableau de suivi.');
    }

    public function updateHandover(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaHandover $handover
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);

        abort_unless(
            (string) $handover->organization_id === (string) $organizationModel->getKey()
            && (string) $handover->project_id === (string) $projectModel->getKey(),
            404
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in(['draft', 'requested', 'accepted', 'rejected'])],
            'requested_at' => ['nullable', 'date'],
            'inspected_at' => ['nullable', 'date'],
            'client_authorized_at' => ['nullable', 'date'],
            'accepted_at' => ['nullable', 'date'],
            'client_reference' => ['nullable', 'string', 'max:255'],
            'from_responsible' => ['nullable', 'string', 'max:255'],
            'to_responsible' => ['nullable', 'string', 'max:255'],
            'client_representative' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:4000'],
        ]);

        if ($validated['status'] === 'accepted' && empty($validated['accepted_at'])) {
            return back()->withErrors(['accepted_at' => 'La date d’acceptation est obligatoire pour clôturer le handover.'])->withInput();
        }

        if ($this->changesDecision($handover->status, $validated['status'], self::HANDOVER_DECISIONS)) {
            $this->resolveScope('approve', $organization, $project);
        }

        $handover->update($validated);

        return back()->with('success', 'Handover mis à jour.');
    }

    private const INSPECTION_DECISIONS = [
        EngineeringQaInspection::STATUS_CONFORMING,
        EngineeringQaInspection::STATUS_NONCONFORMING,
        EngineeringQaInspection::STATUS_CONDITIONAL,
    ];

    private const AUTHORIZATION_DECISIONS = [
        EngineeringQaInspection::AUTH_GRANTED,
        EngineeringQaInspection::AUTH_REJECTED,
    ];

    private const HANDOVER_DECISIONS = ['accepted', 'rejected'];

    /**
     * True when the update sets, changes or withdraws a decision value.
     */
    private function changesDecision(?string $current, string $next, array $decisions): bool
    {
        if ((string) $current === $next) {
            return false;
        }

        return in_array($next, $decisions, true) || in_array((string) $current, $decisions, true);
    }

    private function resolveScope(string $ability, string $organization, string $project): array
    {
        $organizationModel = Organization::query()->where('public_id', $organization)->firstOrFail();
        $projectModel = Project::query()->where('public_id', $project)->firstOrFail();

        app(EngineeringTenantAccessService::class)->authorizeProject(
            $ability,
            request()->user(),
            $organizationModel,
            $projectModel
        );

        return [$organizationModel, $projectModel];
    }

    private function assertInspectionScope(
        EngineeringQaInspection $inspection,
        Organization $organization,
        Project $project
    ): void {
        abort_unless(
            (string) $inspection->organization_id === (string) $organization->getKey()
            && (string) $inspection->project_id === (string) $project->getKey(),
            404
        );
    }
}
