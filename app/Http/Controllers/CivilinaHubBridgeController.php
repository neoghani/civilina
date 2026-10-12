<?php

namespace App\Http\Controllers;

use App\Models\Organization;
use App\Models\Project;
use Illuminate\Routing\Route as LaravelRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Illuminate\View\View;

class CivilinaHubBridgeController extends Controller
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

        $modules = [];

        $engineering = $this->namedModule(
            'Ingénierie',
            'Bureau d’études, QA/QC et référentiels',
            ['engineering.home.bridge', 'engineering.workspace.bridge'],
            $organization,
            $project
        );

        if ($engineering) {
            $modules[] = $engineering;
        }

        /*
         * Détection prudente des autres blocs Civilina.
         * On privilégie les noms de routes "racines" courants et on n'affiche
         * jamais les routes d'authentification, d'API ou de debug.
         */
        $preferred = [
            ['label'=>'Projets', 'description'=>'Pilotage et gestion des projets', 'routes'=>[
                'projects.index','project.index','civilina.projects.index','dashboard.projects',
            ]],
            ['label'=>'Qualité', 'description'=>'Management qualité et conformité', 'routes'=>[
                'quality.index','qa.index','qms.index','civilina.quality.index',
            ]],
            ['label'=>'HSE', 'description'=>'Santé, sécurité et environnement', 'routes'=>[
                'hse.index','safety.index','civilina.hse.index',
            ]],
            ['label'=>'Dashboard', 'description'=>'Vue générale Civilina', 'routes'=>[
                'dashboard','dashboard.index','civilina.dashboard','home',
            ]],
            ['label'=>'Organisation', 'description'=>'Organisation, équipes et paramètres', 'routes'=>[
                'organizations.index','organization.index','civilina.organizations.index',
            ]],
            ['label'=>'Administration', 'description'=>'Administration Civilina', 'routes'=>[
                'admin.index','admin.dashboard','civilina.admin.index',
            ]],
        ];

        foreach ($preferred as $definition) {
            $module = $this->namedModule(
                $definition['label'],
                $definition['description'],
                $definition['routes'],
                $organization,
                $project
            );

            if ($module && ! $this->containsUrl($modules, $module['url'])) {
                $modules[] = $module;
            }
        }

        /*
         * Complément automatique :
         * récupère au maximum 6 vraies routes GET sans paramètre obligatoire
         * situées hors du bloc Engineering.
         */
        foreach (Route::getRoutes() as $route) {
            if (count($modules) >= 8) {
                break;
            }

            if (! $this->eligibleTopLevelRoute($route)) {
                continue;
            }

            $name = $route->getName();

            try {
                $url = route($name);
            } catch (\Throwable) {
                continue;
            }

            if ($this->containsUrl($modules, $url)) {
                continue;
            }

            $modules[] = [
                'label'=>$this->labelFromRoute($name),
                'description'=>'Bloc Civilina disponible',
                'route'=>$name,
                'url'=>$url,
            ];
        }

        return view(
            'civilina.hub-bridge',
            compact('organization', 'project', 'modules')
        );
    }

    private function namedModule(
        string $label,
        string $description,
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
                    || in_array($normalized, ['org','organisation'], true)
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
                    'description'=>$description,
                    'route'=>$name,
                    'url'=>route($name, $parameters),
                ];
            } catch (\Throwable) {
                // Essaie la route suivante.
            }
        }

        return null;
    }

    private function eligibleTopLevelRoute(LaravelRoute $route): bool
    {
        $name = (string) $route->getName();

        if ($name === '' || str_starts_with($name, 'engineering.')) {
            return false;
        }

        $methods = $route->methods();

        if (! in_array('GET', $methods, true)) {
            return false;
        }

        if ($route->parameterNames()) {
            return false;
        }

        $blocked = [
            'login','logout','register','password','verification',
            'sanctum','api.','debug','ignition','storage','profile.',
            'two-factor','confirm-password',
        ];

        foreach ($blocked as $needle) {
            if (str_contains(strtolower($name), strtolower($needle))) {
                return false;
            }
        }

        $uri = strtolower($route->uri());

        foreach (['api/','_debug','ignition','storage/'] as $needle) {
            if (str_contains($uri, $needle)) {
                return false;
            }
        }

        return true;
    }

    private function labelFromRoute(string $name): string
    {
        $root = explode('.', $name)[0] ?: $name;

        return Str::headline($root);
    }

    private function containsUrl(array $modules, string $url): bool
    {
        foreach ($modules as $module) {
            if (($module['url'] ?? null) === $url) {
                return true;
            }
        }

        return false;
    }
}
