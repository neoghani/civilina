<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Support\Facades\Route;
use Illuminate\View\View;

class EngineeringWorkspaceBridgeController extends Controller
{
    public function __invoke(
        Organization $organization,
        Project $project
    ): View {
        // Same tenant rule as the workspace: active member of the
        // organization, project belonging to it.
        app(EngineeringTenantAccessService::class)->authorizeProject(
            'view',
            request()->user(),
            $organization,
            $project
        );

        $links = array_values(array_filter([
            $this->link(
                'Structure projet',
                ['engineering.structure.index', 'engineering.project-structure.index', 'engineering.wbs.index'],
                $organization,
                $project
            ),
            $this->link(
                'ITP',
                ['engineering.itp.index', 'engineering.qa-itp.index'],
                $organization,
                $project
            ),
            $this->link(
                'Contrôles & PV',
                ['engineering.qa-control.index'],
                $organization,
                $project
            ),
            $this->link(
                'Bibliothèque normative',
                ['engineering.normative-library.index'],
                $organization,
                $project
            ),
            $this->link(
                'Normative Librarian',
                ['engineering.normative-librarian.index'],
                $organization,
                $project
            ),
            $this->link(
                'Liaisons QA/QC ↔ Normes',
                ['engineering.qa-normative.index', 'engineering.normative-v22.index'],
                $organization,
                $project
            ),
            $this->link(
                'Revue canonique',
                ['engineering.normative-canonical-review.index'],
                $organization,
                $project
            ),
        ]));

        return view(
            'engineering.workspace-bridge',
            compact('organization', 'project', 'links')
        );
    }

    private function link(
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
                    continue;
                }
            }

            try {
                return [
                    'label' => $label,
                    'route' => $name,
                    'url' => route($name, $parameters),
                ];
            } catch (\Throwable) {
                // Essaie la route candidate suivante.
            }
        }

        return null;
    }
}
