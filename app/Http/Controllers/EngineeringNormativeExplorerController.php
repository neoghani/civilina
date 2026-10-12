<?php

namespace App\Http\Controllers;

use App\Models\EngineeringNormativeRequirement;
use App\Models\EngineeringNormativeSection;
use App\Models\EngineeringNormativeSource;
use App\Models\EngineeringNormativeStandard;
use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\Normative\NormativeControlCompatibilityService;
use App\Services\Engineering\Normative\NormativeQaMatcher;
use App\Services\Engineering\Normative\NormativeRegistryService;
use App\Services\Engineering\Normative\NormativeTaxonomyIndexer;
use App\Services\Engineering\Normative\NormativeTaxonomyService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EngineeringNormativeExplorerController extends Controller
{
    public function index(Request $request, Organization $organization, Project $project, NormativeTaxonomyService $taxonomy, NormativeControlCompatibilityService $compatibility): View
    {
        $this->guardProject($organization, $project);

        $tab = (string) $request->query('tab', 'registry');
        $q = trim((string) $request->query('q', ''));
        $domain = trim((string) $request->query('domain', ''));
        $category = trim((string) $request->query('category', ''));
        $issuer = trim((string) $request->query('issuer', ''));

        $query = EngineeringNormativeStandard::query()->withCount(['sources','editions'])->orderByRaw('canonical_code is null')->orderBy('canonical_code');

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('canonical_code','like',"%{$q}%")
                    ->orWhere('title','like',"%{$q}%")
                    ->orWhere('family_code','like',"%{$q}%")
                    ->orWhere('category','like',"%{$q}%")
                    ->orWhere('subcategory','like',"%{$q}%");
            });
        }
        if ($domain !== '') $query->where('domain',$domain);
        if ($category !== '') $query->where('category',$category);
        if ($issuer !== '') $query->where('issuing_body',$issuer);

        $standards = $query->limit(500)->get();

        $sectionCounts = EngineeringNormativeSection::query()
            ->selectRaw('engineering_normative_editions.standard_id as standard_id, count(*) as aggregate')
            ->join('engineering_normative_editions','engineering_normative_editions.id','=','engineering_normative_sections_v2.edition_id')
            ->groupBy('engineering_normative_editions.standard_id')->pluck('aggregate','standard_id');

        $requirementCounts = EngineeringNormativeRequirement::query()
            ->selectRaw('engineering_normative_editions.standard_id as standard_id, count(*) as aggregate')
            ->join('engineering_normative_sections_v2','engineering_normative_sections_v2.id','=','engineering_normative_requirements.section_id')
            ->join('engineering_normative_editions','engineering_normative_editions.id','=','engineering_normative_sections_v2.edition_id')
            ->groupBy('engineering_normative_editions.standard_id')->pluck('aggregate','standard_id');

        $categories = EngineeringNormativeStandard::query()->whereNotNull('category')->where('category','!=','')->distinct()->orderBy('category')->pluck('category');
        $domains = EngineeringNormativeStandard::query()->whereNotNull('domain')->where('domain','!=','')->distinct()->orderBy('domain')->pluck('domain');
        $issuers = EngineeringNormativeStandard::query()->whereNotNull('issuing_body')->where('issuing_body','!=','')->distinct()->orderBy('issuing_body')->pluck('issuing_body');

        $sources = EngineeringNormativeSource::query()->with(['standard','duplicateOf'])->latest('updated_at')->limit(300)->get();
        $inspections = EngineeringQaInspection::query()->where('project_id',$project->getKey())->orderBy('work_type')->orderBy('code')->get();
        $links = EngineeringQaNormativeLink::query()->with(['standard','edition','section','requirement'])->whereIn('inspection_id',$inspections->pluck('id'))->orderByDesc('score')->get()->groupBy('inspection_id');

        $inspectionDomains = [];
        $activityWarnings = [];
        foreach ($inspections as $inspection) {
            $inspectionDomains[$inspection->id] = $compatibility->inspectionDomain($inspection);
            $activityWarnings[$inspection->id] = $compatibility->activityLooksWrong($inspection);
        }

        $counts = [
            'standards' => EngineeringNormativeStandard::query()->count(),
            'categories' => $categories->count(),
            'domains' => $domains->count(),
            'sections' => EngineeringNormativeSection::query()->count(),
            'requirements' => EngineeringNormativeRequirement::query()->count(),
            'duplicates' => EngineeringNormativeSource::query()->where('is_exact_duplicate',true)->count(),
            'links' => EngineeringQaNormativeLink::query()->whereIn('inspection_id',$inspections->pluck('id'))->count(),
        ];

        return view('engineering.normative-explorer-v22', compact('organization','project','tab','q','domain','category','issuer','standards','sectionCounts','requirementCounts','categories','domains','issuers','sources','inspections','links','inspectionDomains','activityWarnings','counts','taxonomy'));
    }

    public function refresh(Organization $organization, Project $project, NormativeTaxonomyIndexer $indexer): RedirectResponse
    {
        $this->guardProject($organization,$project);
        $stats = $indexer->refresh();
        return back()->with('normative_message',"Taxonomie actualisée : {$stats['sources']} source(s), {$stats['standards']} norme(s) actualisée(s).");
    }

    public function deepScan(Request $request, Organization $organization, Project $project, NormativeRegistryService $registry, NormativeTaxonomyIndexer $indexer): RedirectResponse
    {
        $this->guardProject($organization,$project);
        $limit = $request->boolean('all') ? null : max(1,$request->integer('limit',100));
        $stats = $registry->scan($limit,$request->boolean('force'));
        $tax = $indexer->refresh();
        return back()->with('normative_message','Indexation : '.json_encode($stats,JSON_UNESCAPED_UNICODE)." | Taxonomie : {$tax['sources']} source(s).");
    }

    public function runAgent(Organization $organization, Project $project, NormativeQaMatcher $matcher): RedirectResponse
    {
        $this->guardProject($organization,$project);
        $created = 0;
        foreach (EngineeringQaInspection::query()->where('project_id',$project->getKey())->get() as $inspection) {
            $created += $matcher->suggestForInspection($inspection);
        }
        return back()->with('normative_message',"Agent normatif : {$created} proposition(s) créée(s).");
    }

    public function suggest(Organization $organization, Project $project, EngineeringQaInspection $inspection, NormativeQaMatcher $matcher): RedirectResponse
    {
        $this->guardProject($organization,$project);
        abort_unless((int)$inspection->project_id === (int)$project->getKey(),404);
        $created = $matcher->suggestForInspection($inspection);
        return back()->with('normative_message',"{$created} proposition(s) créée(s) pour {$inspection->code}.");
    }

    private function guardProject(Organization $organization, Project $project): void
    {
        if (isset($project->organization_id)) abort_unless((int)$project->organization_id === (int)$organization->getKey(),404);
    }
}
