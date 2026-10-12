<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class EngineeringNormativePublishedRegistryController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        Project $project
    ) {
        if (isset($project->organization_id)) {
            abort_unless(
                (int) $project->organization_id === (int) $organization->id,
                404
            );
        }

        $state = strtoupper(trim((string) $request->query('state', 'ACTIVE')));
        $type = strtoupper(trim((string) $request->query('type', '')));
        $q = trim((string) $request->query('q', ''));

        $query = DB::table('civilina_normative_registry_requirements')
            ->orderBy('reference_code')
            ->orderBy('edition_key')
            ->orderBy('section_code')
            ->orderBy('id');

        if (in_array($state, ['ACTIVE', 'REVOKED'], true)) {
            $query->where('publication_state', $state);
        }

        if (in_array($type, ['REQUIREMENT', 'TOLERANCE'], true)) {
            $query->where('requirement_type', $type);
        }

        if ($q !== '') {
            $query->where(function ($sub) use ($q) {
                $sub->where('reference_code', 'like', '%'.$q.'%')
                    ->orWhere('heading', 'like', '%'.$q.'%')
                    ->orWhere('requirement_text', 'like', '%'.$q.'%');
            });
        }

        $items = $query->paginate(50)->withQueryString();

        $metrics = [
            'active'=>DB::table('civilina_normative_registry_requirements')
                ->where('publication_state', 'ACTIVE')->count(),
            'revoked'=>DB::table('civilina_normative_registry_requirements')
                ->where('publication_state', 'REVOKED')->count(),
            'requirements'=>DB::table('civilina_normative_registry_requirements')
                ->where('publication_state', 'ACTIVE')
                ->where('requirement_type', 'REQUIREMENT')->count(),
            'tolerances'=>DB::table('civilina_normative_registry_requirements')
                ->where('publication_state', 'ACTIVE')
                ->where('requirement_type', 'TOLERANCE')->count(),
        ];

        return view('engineering.normative-published-registry.index', compact(
            'organization',
            'project',
            'items',
            'metrics',
            'state',
            'type',
            'q'
        ));
    }
}
