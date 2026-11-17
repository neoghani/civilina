<?php

namespace App\Http\Controllers;

use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\EngineeringStandardInboxFile;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringQaNormativeAgentService;
use App\Services\Engineering\EngineeringStandardInboxService;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EngineeringQaNormativeController extends Controller
{
    public function index(
        string $organization,
        string $project,
        EngineeringStandardInboxService $inbox
    ): View {
        [$organizationModel, $projectModel] = $this->resolveScope('view', $organization, $project);

        $inspections = EngineeringQaInspection::query()
            ->where('organization_id', (string) $organizationModel->getKey())
            ->where('project_id', (string) $projectModel->getKey())
            ->with('normativeLinks')
            ->orderByDesc('id')
            ->get();

        $files = EngineeringStandardInboxFile::query()
            ->orderByDesc('last_scanned_at')
            ->orderBy('original_name')
            ->limit(250)
            ->get();

        $sectionCount = 0;
        if (class_exists(\App\Models\StandardSection::class)) {
            $model = new \App\Models\StandardSection();
            if (Schema::hasTable($model->getTable())) {
                $sectionCount = \App\Models\StandardSection::query()->count();
            }
        }

        return view('engineering.qa_normative.index', [
            'organization' => $organizationModel,
            'project' => $projectModel,
            'inspections' => $inspections,
            'inboxFiles' => $files,
            'inboxDirectory' => $inbox->directory(),
            'standardSectionCount' => $sectionCount,
        ]);
    }

    public function scan(
        Request $request,
        string $organization,
        string $project,
        EngineeringStandardInboxService $inbox
    ): RedirectResponse {
        $this->resolveScope('edit', $organization, $project);

        $validated = $request->validate([
            'batch_size' => ['nullable', 'integer', Rule::in([10, 20, 50])],
        ]);

        $result = $inbox->scan((int) ($validated['batch_size'] ?? 20));

        $message = $result['complete']
            ? sprintf(
                'Scan terminé : %d fichier(s) détecté(s), %d traité(s) dans ce lot, %d déjà à jour, %d erreur(s).',
                $result['total_files'],
                $result['processed'],
                $result['already_current'],
                $result['errors']
            )
            : sprintf(
                'Lot traité : %d fichier(s). Il reste %d fichier(s) à scanner. Clique de nouveau sur « Scanner le lot suivant ».',
                $result['processed'],
                $result['remaining']
            );

        return back()->with('success', $message);
    }

    public function suggest(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspection $inspection,
        EngineeringQaNormativeAgentService $agent
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertInspectionScope($inspection, $organizationModel, $projectModel);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:10'],
        ]);

        $result = $agent->suggest(
            $inspection,
            $projectModel,
            $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
            (int) ($validated['limit'] ?? 5)
        );

        return back()->with('success', $result['message']);
    }

    public function decide(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaNormativeLink $link
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('approve', $organization, $project);

        abort_unless(
            (string) $link->organization_id === (string) $organizationModel->getKey()
            && (string) $link->project_id === (string) $projectModel->getKey(),
            404
        );

        $validated = $request->validate([
            'status' => ['required', Rule::in([
                EngineeringQaNormativeLink::STATUS_VALIDATED,
                EngineeringQaNormativeLink::STATUS_REJECTED,
            ])],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        $userId = $request->user() ? (string) $request->user()->getAuthIdentifier() : null;
        $isValidated = $validated['status'] === EngineeringQaNormativeLink::STATUS_VALIDATED;

        $link->update([
            'status' => $validated['status'],
            'notes' => $validated['notes'] ?? null,
            'validated_by' => $isValidated ? $userId : null,
            'validated_at' => $isValidated ? now() : null,
            'rejected_at' => $isValidated ? null : now(),
        ]);

        return back()->with('success', $isValidated ? 'Référence normative validée.' : 'Référence normative rejetée.');
    }

    public function manual(
        Request $request,
        string $organization,
        string $project,
        EngineeringQaInspection $inspection
    ): RedirectResponse {
        [$organizationModel, $projectModel] = $this->resolveScope('edit', $organization, $project);
        $this->assertInspectionScope($inspection, $organizationModel, $projectModel);

        $validated = $request->validate([
            'source_label' => ['required', 'string', 'max:255'],
            'source_reference' => ['nullable', 'string', 'max:255'],
            'clause_code' => ['nullable', 'string', 'max:120'],
            'excerpt' => ['nullable', 'string', 'max:4000'],
            'notes' => ['nullable', 'string', 'max:3000'],
        ]);

        EngineeringQaNormativeLink::create([
            'organization_id' => (string) $organizationModel->getKey(),
            'project_id' => (string) $projectModel->getKey(),
            'engineering_qa_inspection_id' => $inspection->getKey(),
            'source_label' => $validated['source_label'],
            'source_reference' => $validated['source_reference'] ?? null,
            'clause_code' => $validated['clause_code'] ?? null,
            'excerpt' => $validated['excerpt'] ?? null,
            'confidence_score' => 100,
            'match_reason' => 'Référence ajoutée manuellement par un utilisateur.',
            'project_profile_match' => true,
            'status' => EngineeringQaNormativeLink::STATUS_VALIDATED,
            'suggested_by' => 'human_manual',
            'validated_by' => $request->user() ? (string) $request->user()->getAuthIdentifier() : null,
            'validated_at' => now(),
            'notes' => $validated['notes'] ?? null,
        ]);

        return back()->with('success', 'Référence normative manuelle ajoutée et validée.');
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
