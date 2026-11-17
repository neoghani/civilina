<?php

namespace App\Http\Controllers;

use App\Models\EngineeringItp;
use App\Models\EngineeringItpRow;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringItpReadinessService;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EngineeringItpController extends Controller
{
    public function index(
        Request $request,
        string $organization,
        string $project,
        EngineeringItpReadinessService $readiness
    ): View {
        [$organizationModel, $projectModel] = $this->resolveScope('view', $organization, $project);

        $itps = EngineeringItp::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->with('rows')
            ->orderByDesc('id')
            ->get()
            ->each(function (EngineeringItp $itp) use ($readiness): void {
                $itp->setAttribute('readiness', $readiness->evaluate($itp));
            });

        return view('engineering.itp.index', [
            'organization' => $organizationModel,
            'project' => $projectModel,
            'itps' => $itps,
        ]);
    }

    public function store(Request $request, string $organization, string $project): RedirectResponse
    {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);

        $validated = $request->validate([
            'code' => ['required', 'string', 'max:80'],
            'title' => ['required', 'string', 'max:255'],
            'revision' => ['nullable', 'string', 'max:40'],
            'scope_type' => ['required', Rule::in(['project', 'work_package', 'activity', 'zone', 'ouvrage'])],
            'scope_ref' => ['nullable', 'string', 'max:120'],
            'scope_label' => ['required', 'string', 'max:255'],
            'reference_documents_text' => ['required', 'string', 'max:8000'],
            'notes' => ['nullable', 'string', 'max:8000'],
        ]);

        $references = collect(preg_split('/\r\n|\r|\n/', $validated['reference_documents_text']))
            ->map(fn ($line) => trim((string) $line))
            ->filter()
            ->values()
            ->all();

        if ($references === []) {
            return back()->withErrors([
                'reference_documents_text' => 'Renseignez au moins une référence applicable.',
            ])->withInput();
        }

        $revision = $validated['revision'] ?: 'DRAFT-01';

        $duplicate = EngineeringItp::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->where('code', $validated['code'])
            ->where('revision', $revision)
            ->exists();

        if ($duplicate) {
            return back()->withErrors([
                'code' => 'Cette combinaison Code + Révision existe déjà pour ce projet.',
            ])->withInput();
        }

        EngineeringItp::create([
            'organization_id' => (string) $organizationModel->getKey(),
            'project_id' => (string) $projectModel->getKey(),
            'code' => $validated['code'],
            'title' => $validated['title'],
            'revision' => $revision,
            'status' => EngineeringItp::STATUS_DRAFT,
            'scope_type' => $validated['scope_type'],
            'scope_ref' => $validated['scope_ref'] ?? null,
            'scope_label' => $validated['scope_label'],
            'reference_documents' => $references,
            'notes' => $validated['notes'] ?? null,
            'created_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
        ]);

        return back()->with('success', 'ITP créé en brouillon.');
    }

    public function addRow(
        Request $request,
        string $organization,
        string $project,
        EngineeringItp $itp
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertItpScope($itp, $organizationModel, $projectModel);

        abort_unless($itp->isDraft(), 422, 'Seul un ITP en brouillon peut être modifié.');

        $validated = $request->validate([
            'sequence' => ['nullable', 'integer', 'min:1', 'max:9999'],
            'code' => ['required', 'string', 'max:80'],
            'inspection_test' => ['required', 'string', 'max:2000'],
            'method' => ['nullable', 'string', 'max:2000'],
            'frequency' => ['nullable', 'string', 'max:500'],
            'acceptance_criteria' => ['required', 'string', 'max:3000'],
            'checkpoint_type' => ['required', Rule::in([
                EngineeringItpRow::CHECKPOINT_HOLD,
                EngineeringItpRow::CHECKPOINT_WITNESS,
                EngineeringItpRow::CHECKPOINT_SURVEILLANCE,
            ])],
            'responsibility_execute' => ['required', 'string', 'max:255'],
            'responsibility_inspect' => ['required', 'string', 'max:255'],
            'responsibility_approve' => ['nullable', 'string', 'max:255'],
            'responsibility_accept' => ['nullable', 'string', 'max:255'],
            'record_required' => ['required', 'string', 'max:1000'],
            'source_reference' => ['nullable', 'string', 'max:1000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $duplicate = $itp->rows()->where('code', $validated['code'])->exists();
        if ($duplicate) {
            return back()->withErrors(['row_code' => 'Ce code de ligne existe déjà dans cet ITP.'])->withInput();
        }

        $sequence = $validated['sequence'] ?? ((int) $itp->rows()->max('sequence') + 10);

        $itp->rows()->create(array_merge($validated, ['sequence' => $sequence]));

        return back()->with('success', 'Ligne ITP ajoutée.');
    }

    public function submitReview(
        Request $request,
        string $organization,
        string $project,
        EngineeringItp $itp,
        EngineeringItpReadinessService $readiness
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertItpScope($itp, $organizationModel, $projectModel);
        abort_unless($itp->isDraft(), 422, 'L’ITP doit être en brouillon.');

        $itp->load('rows');
        $evaluation = $readiness->evaluate($itp);

        if (! $evaluation['ready']) {
            return back()->withErrors(['itp' => 'ITP incomplet : '.implode(' | ', $evaluation['blockers'])]);
        }

        $itp->forceFill([
            'status' => EngineeringItp::STATUS_IN_REVIEW,
            'reviewed_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
            'reviewed_at' => now(),
        ])->save();

        return back()->with('success', 'ITP soumis en revue.');
    }

    public function approve(
        Request $request,
        string $organization,
        string $project,
        EngineeringItp $itp,
        EngineeringItpReadinessService $readiness
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('approve', $organization, $project);
        $this->assertItpScope($itp, $organizationModel, $projectModel);
        abort_unless($itp->isInReview(), 422, 'L’ITP doit être en revue avant approbation.');

        $itp->load('rows');
        $evaluation = $readiness->evaluate($itp);

        if (! $evaluation['ready']) {
            return back()->withErrors(['itp' => 'L’ITP n’est plus complet : '.implode(' | ', $evaluation['blockers'])]);
        }

        $itp->forceFill([
            'status' => EngineeringItp::STATUS_APPROVED,
            'approved_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
            'approved_at' => now(),
        ])->save();

        return back()->with('success', 'ITP approuvé.');
    }

    private function resolveScope(string $ability, string $organization, string $project): array
    {
        $organizationModel = Organization::query()->whereKey($organization)->firstOrFail();
        $projectModel = Project::query()->whereKey($project)->firstOrFail();

        app(EngineeringTenantAccessService::class)->authorizeProject(
            $ability,
            request()->user(),
            $organizationModel,
            $projectModel
        );

        return [$organizationModel, $projectModel];
    }

    private function assertItpScope(EngineeringItp $itp, Organization $organization, Project $project): void
    {
        abort_unless(
            (string) $itp->organization_id === (string) $organization->getKey()
            && (string) $itp->project_id === (string) $project->getKey(),
            404
        );
    }
}
