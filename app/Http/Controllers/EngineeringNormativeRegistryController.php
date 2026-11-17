<?php

namespace App\Http\Controllers;

use App\Models\EngineeringNormativeRequirement;
use App\Models\EngineeringNormativeSource;
use App\Models\EngineeringNormativeStandard;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\Normative\NormativeQaMatcher;
use App\Services\Engineering\Normative\NormativeRegistryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class EngineeringNormativeRegistryController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeQaMatcher $matcher
    ): View {
        $this->guardProject('view', $organization, $project);

        $tab = (string) $request->query('tab', 'registry');

        $counts = [
            'standards' => EngineeringNormativeStandard::query()->count(),
            'sources' => EngineeringNormativeSource::query()->count(),
            'requirements' => EngineeringNormativeRequirement::query()->count(),
            'duplicates' => EngineeringNormativeSource::query()->where('is_exact_duplicate', true)->count(),
            'pending' => EngineeringNormativeSource::query()
                ->whereIn('extraction_status', ['pending', 'needs_pdf_parser', 'needs_ocr', 'extract_error'])
                ->count(),
            'links' => EngineeringQaNormativeLink::query()
                ->whereIn('inspection_id', EngineeringQaInspection::query()->where('project_id', $project->getKey())->select('id'))
                ->count(),
        ];

        $standards = EngineeringNormativeStandard::query()
            ->withCount(['sources', 'editions'])
            ->orderByRaw('domain is null, domain')
            ->orderBy('canonical_code')
            ->limit(250)
            ->get();

        $sources = EngineeringNormativeSource::query()
            ->with(['standard', 'duplicateOf'])
            ->orderByDesc('updated_at')
            ->limit(300)
            ->get();

        $inspections = EngineeringQaInspection::query()
            ->where('project_id', $project->getKey())
            ->orderBy('id')
            ->get();

        $links = EngineeringQaNormativeLink::query()
            ->with(['standard', 'edition', 'section', 'requirement'])
            ->whereIn('inspection_id', $inspections->pluck('id'))
            ->orderByDesc('score')
            ->get()
            ->groupBy('inspection_id');

        $activityCompatibility = [];
        foreach ($inspections as $inspection) {
            $activityCompatibility[$inspection->getKey()] = $matcher->isActivityCompatible($inspection);
        }

        return view('engineering.normative-agent-v2', compact(
            'organization',
            'project',
            'tab',
            'counts',
            'standards',
            'sources',
            'inspections',
            'links',
            'activityCompatibility'
        ));
    }

    public function scan(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeRegistryService $registry
    ): RedirectResponse {
        $this->guardProject('edit', $organization, $project);

        $validated = $request->validate([
            'limit' => ['nullable', 'integer', 'min:1', 'max:2000'],
            'force' => ['nullable', 'boolean'],
        ]);

        $stats = $registry->scan(
            isset($validated['limit']) ? (int) $validated['limit'] : 50,
            (bool) ($validated['force'] ?? false)
        );

        return back()->with('normative_message', 'Scan termine : '.json_encode($stats, JSON_UNESCAPED_UNICODE));
    }

    public function runAgent(
        Organization $organization,
        Project $project,
        NormativeQaMatcher $matcher
    ): RedirectResponse {
        $this->guardProject('edit', $organization, $project);

        $total = 0;
        $errors = [];

        $inspections = EngineeringQaInspection::query()
            ->where('project_id', $project->getKey())
            ->get();

        foreach ($inspections as $inspection) {
            try {
                $total += $matcher->suggestForInspection($inspection);
            } catch (\Throwable $e) {
                report($e);
                $errors[] = ($inspection->code ?? ('#'.$inspection->id)).' : '.$e->getMessage();
            }
        }

        if ($errors) {
            return back()->with(
                'normative_message',
                'Agent partiellement execute. '.$total.' proposition(s). Erreurs : '.implode(' | ', array_slice($errors, 0, 3))
            );
        }

        return back()->with(
            'normative_message',
            'Agent termine : '.$total.' proposition(s) creees ou mises a jour.'
        );
    }

    public function suggest(
        Organization $organization,
        Project $project,
        EngineeringQaInspection $inspection,
        NormativeQaMatcher $matcher
    ): RedirectResponse {
        $this->guardProject('edit', $organization, $project);
        abort_unless((int) $inspection->project_id === (int) $project->getKey(), 404);

        try {
            $count = $matcher->suggestForInspection($inspection);

            return back()->with(
                'normative_message',
                $count.' proposition(s) trouvee(s) pour '.($inspection->code ?? ('#'.$inspection->id)).'.'
            );
        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'normative_message',
                'Erreur Agent sur '.($inspection->code ?? ('#'.$inspection->id)).' : '.$e->getMessage()
            );
        }
    }

    public function validateLink(
        Organization $organization,
        Project $project,
        EngineeringQaNormativeLink $link
    ): RedirectResponse {
        $this->guardProject('approve', $organization, $project);

        $inspection = EngineeringQaInspection::query()->findOrFail($link->inspection_id);
        abort_unless((int) $inspection->project_id === (int) $project->getKey(), 404);

        $link->update([
            'status' => 'validated',
            'validated_by' => auth()->id(),
            'validated_at' => now(),
            'rejected_at' => null,
        ]);

        if (DB::getSchemaBuilder()->hasTable('engineering_project_normative_standards')) {
            DB::table('engineering_project_normative_standards')->updateOrInsert(
                [
                    'project_id' => $project->getKey(),
                    'standard_id' => $link->standard_id,
                ],
                [
                    'applicability' => 'applicable',
                    'source' => 'validated_qa_link',
                    'validated_by' => auth()->id(),
                    'validated_at' => now(),
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        return back()->with('normative_message', 'Reference normative validee.');
    }

    public function rejectLink(
        Organization $organization,
        Project $project,
        EngineeringQaNormativeLink $link
    ): RedirectResponse {
        $this->guardProject('approve', $organization, $project);

        $inspection = EngineeringQaInspection::query()->findOrFail($link->inspection_id);
        abort_unless((int) $inspection->project_id === (int) $project->getKey(), 404);

        $link->update([
            'status' => 'rejected',
            'validated_by' => auth()->id(),
            'validated_at' => null,
            'rejected_at' => now(),
        ]);

        return back()->with('normative_message', 'Proposition rejetee.');
    }

    private function guardProject(
        string $ability,
        Organization $organization,
        Project $project
    ): void {
        app(EngineeringTenantAccessService::class)->authorizeProject(
            $ability,
            request()->user(),
            $organization,
            $project
        );
    }
}
