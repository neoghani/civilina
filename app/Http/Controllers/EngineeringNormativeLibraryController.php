<?php

namespace App\Http\Controllers;

use App\Models\EngineeringNormativeRequirement;
use App\Models\EngineeringNormativeSource;
use App\Models\EngineeringNormativeStandard;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\Normative\NormativeLibraryTaxonomyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class EngineeringNormativeLibraryController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        Project $project,
        NormativeLibraryTaxonomyService $taxonomy
    ): View {
        $this->guardProject($organization, $project);

        $q = trim((string) $request->query('q', ''));
        $discipline = trim((string) $request->query('discipline', ''));
        $domain = trim((string) $request->query('domain', ''));
        $subdomain = trim((string) $request->query('subdomain', ''));
        $collection = trim((string) $request->query('collection', ''));

        $query = EngineeringNormativeStandard::query()
            ->withCount(['sources', 'editions'])
            ->orderByRaw("case when classification_status = 'review' then 1 else 0 end")
            ->orderBy('discipline')
            ->orderBy('domain')
            ->orderBy('subdomain')
            ->orderBy('canonical_code');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('canonical_code', 'like', "%{$q}%")
                    ->orWhere('title', 'like', "%{$q}%")
                    ->orWhere('family_name', 'like', "%{$q}%")
                    ->orWhere('source_collection', 'like', "%{$q}%");
            });
        }
        if ($discipline !== '') $query->where('discipline', $discipline);
        if ($domain !== '') $query->where('domain', $domain);
        if ($subdomain !== '') $query->where('subdomain', $subdomain);
        if ($collection !== '') $query->where('source_collection', $collection);

        $standards = $query->limit(700)->get();

        $disciplines = EngineeringNormativeStandard::query()
            ->whereNotNull('discipline')
            ->selectRaw('discipline, count(*) total')
            ->groupBy('discipline')->orderBy('discipline')->get();

        $domains = EngineeringNormativeStandard::query()
            ->whereNotNull('domain')
            ->selectRaw('domain, count(*) total')
            ->groupBy('domain')->orderBy('domain')->get();

        $subdomains = EngineeringNormativeStandard::query()
            ->whereNotNull('subdomain')
            ->selectRaw('subdomain, count(*) total')
            ->groupBy('subdomain')->orderBy('subdomain')->get();

        $collections = EngineeringNormativeStandard::query()
            ->whereNotNull('source_collection')
            ->selectRaw('source_collection, count(*) total')
            ->groupBy('source_collection')->orderByDesc('total')->get();

        $counts = [
            'standards' => EngineeringNormativeStandard::query()->count(),
            'classified' => EngineeringNormativeStandard::query()->where('classification_status', 'auto')->count(),
            'review' => EngineeringNormativeStandard::query()->where('classification_status', 'review')->count(),
            'links' => EngineeringQaNormativeLink::query()
                ->whereIn('inspection_id', EngineeringQaInspection::query()->where('project_id', $project->id)->select('id'))
                ->count(),
        ];

        return view('engineering.normative-library-v24', compact(
            'organization','project','taxonomy','q','discipline','domain','subdomain','collection',
            'standards','disciplines','domains','subdomains','collections','counts'
        ));
    }

    public function show(
        Request $request,
        Organization $organization,
        Project $project,
        EngineeringNormativeStandard $standard,
        NormativeLibraryTaxonomyService $taxonomy
    ): View {
        $this->guardProject($organization, $project);

        $standard->load([
            'sources' => fn ($q) => $q->orderBy('relative_path'),
            'editions' => fn ($q) => $q->with(['sections' => fn ($q) => $q->with('requirements')->orderBy('sequence')])
                ->orderByDesc('publication_year'),
        ]);

        $sourceId = $request->integer('source');
        $selectedSource = $sourceId
            ? $standard->sources->firstWhere('id', $sourceId)
            : $standard->sources->first();

        $inspections = EngineeringQaInspection::query()
            ->where('project_id', $project->id)
            ->orderBy('code')
            ->get();

        $links = EngineeringQaNormativeLink::query()
            ->with(['requirement','section'])
            ->where('standard_id', $standard->id)
            ->whereIn('inspection_id', $inspections->pluck('id'))
            ->orderByDesc('score')
            ->get();

        return view('engineering.normative-standard-review-v24', compact(
            'organization','project','standard','selectedSource','inspections','links','taxonomy'
        ));
    }

    public function inline(
        Organization $organization,
        Project $project,
        EngineeringNormativeStandard $standard,
        EngineeringNormativeSource $source
    ): BinaryFileResponse {
        $this->guardProject($organization, $project);
        abort_unless((int) $source->standard_id === (int) $standard->id, 404);

        $root = realpath(storage_path('app/private/engineering/standards_inbox'));
        abort_unless($root !== false, 404);

        $candidate = realpath($root.DIRECTORY_SEPARATOR.str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $source->relative_path));
        abort_unless($candidate !== false && str_starts_with($candidate, $root.DIRECTORY_SEPARATOR), 404);
        abort_unless(File::exists($candidate), 404);

        $extension = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
        $allowed = ['pdf','txt','md','png','jpg','jpeg','webp'];
        abort_unless(in_array($extension, $allowed, true), 415);

        $mime = match ($extension) {
            'pdf' => 'application/pdf',
            'png' => 'image/png',
            'jpg', 'jpeg' => 'image/jpeg',
            'webp' => 'image/webp',
            default => 'text/plain; charset=UTF-8',
        };

        return response()->file($candidate, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.basename($candidate).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function updateStandard(
        Request $request,
        Organization $organization,
        Project $project,
        EngineeringNormativeStandard $standard
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $validated = $request->validate([
            'title' => ['nullable','string','max:255'],
            'issuing_body' => ['nullable','string','max:120'],
            'discipline' => ['nullable','string','max:120'],
            'domain' => ['nullable','string','max:120'],
            'subdomain' => ['nullable','string','max:190'],
            'family_name' => ['nullable','string','max:190'],
            'source_collection' => ['nullable','string','max:190'],
        ]);

        $standard->forceFill($validated + ['classification_status' => 'validated'])->save();

        return back()->with('normative_message', 'Fiche normative mise à jour.');
    }

    public function updateRequirement(
        Request $request,
        Organization $organization,
        Project $project,
        EngineeringNormativeRequirement $requirement
    ): RedirectResponse {
        $this->guardProject($organization, $project);

        $validated = $request->validate([
            'requirement_type' => ['required','string','max:60'],
            'subject' => ['nullable','string','max:190'],
            'requirement_text' => ['required','string','max:5000'],
            'operator' => ['nullable','string','max:20'],
            'min_value' => ['nullable','numeric'],
            'max_value' => ['nullable','numeric'],
            'nominal_value' => ['nullable','numeric'],
            'tolerance_plus' => ['nullable','numeric'],
            'tolerance_minus' => ['nullable','numeric'],
            'unit' => ['nullable','string','max:32'],
        ]);

        $requirement->forceFill($validated)->save();

        return back()->with('normative_message', 'Exigence / tolérance corrigée.');
    }

    private function guardProject(Organization $organization, Project $project): void
    {
        if (isset($project->organization_id)) {
            abort_unless((int) $project->organization_id === (int) $organization->id, 404);
        }
    }
}
