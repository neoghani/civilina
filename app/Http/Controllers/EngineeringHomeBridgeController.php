<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class EngineeringHomeBridgeController extends Controller
{
    public function __invoke(
        Organization $organization,
        Project $project
    ): View {
        if (isset($project->organization_id)) {
            abort_unless(
                (int) $project->organization_id === (int) $organization->id,
                404
            );
        }

        $modules = array_values(array_filter([
            $this->module('Workspace', [
                'engineering.workspace.bridge',
            ], $organization, $project),

            $this->module('Structure projet', [
                'engineering.structure.index',
                'engineering.project-structure.index',
                'engineering.wbs.index',
            ], $organization, $project),

            $this->module('ITP', [
                'engineering.itp.index',
                'engineering.qa-itp.index',
            ], $organization, $project),

            $this->module('Contrôles & PV', [
                'engineering.qa-control.index',
            ], $organization, $project),

            $this->module('Bibliothèque normative', [
                'engineering.normative-library.index',
            ], $organization, $project),

            $this->module('Normative Librarian', [
                'engineering.normative-librarian.index',
            ], $organization, $project),

            $this->module('Liaisons QA/QC ↔ Normes', [
                'engineering.qa-normative.index',
                'engineering.normative-v22.index',
            ], $organization, $project),

            $this->module('Revue canonique', [
                'engineering.normative-canonical-review.index',
            ], $organization, $project),
        ]));

        return view(
            'engineering.home-bridge',
            compact('organization', 'project', 'modules')
        );
    }

    private function module(
        string $label,
        array $routeNames,
        Organization $organization,
        Project $project
    ): ?array {
        foreach ($routeNames as $name) {
            if (! Route::has($name)) {
                continue;
            }

            $route = Route::getRoutes()->getByName($name);
            if (! $route) {
                continue;
            }

            $parameters = [];

            foreach ($route->parameterNames() as $parameter) {
                $normalized = strtolower($parameter);

                if (
                    str_contains($normalized, 'organization')
                    || in_array($normalized, ['org', 'organisation'], true)
                ) {
                    $parameters[$parameter] = $organization;
                    continue;
                }

                if (str_contains($normalized, 'project')) {
                    $parameters[$parameter] = $project;
                }
            }

            try {
                return [
                    'label'=>$label,
                    'route'=>$name,
                    'url'=>route($name, $parameters),
                ];
            } catch (\Throwable) {
                // Essaie la route candidate suivante.
            }
        }

        return null;
    }
}
