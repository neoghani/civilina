<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringProjectController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $projects = \App\Models\Project::query()
            ->where(
                'organization_id',
                $organization->getKey()
            )
            ->orderBy('code')
            ->get([
                'public_id',
                'code',
                'name',
                'description',
                'status',
                'created_at',
                'updated_at',
            ]);

        return response()->json([
            'data' => $projects,
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $validated = $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'description' => [
                'nullable',
                'string',
            ],
        ]);

        $project = $projects->createProject(
            $organization,
            $validated['code'],
            $validated['name'],
            $validated['description'] ?? null
        );

        return response()->json([
            'data' => $project->only([
                'public_id',
                'code',
                'name',
                'description',
                'status',
                'created_at',
                'updated_at',
            ]),
        ], 201);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $model = $access->scopedProject(
            $organization,
            $project
        );

        return response()->json([
            'data' => [
                ...$model->only([
                    'public_id',
                    'code',
                    'name',
                    'description',
                    'status',
                    'created_at',
                    'updated_at',
                ]),
                'counts' => [
                    'wbs_nodes' =>
                        $model->wbsNodes()->count(),
                    'zones' =>
                        $model->zones()->count(),
                    'ouvrages' =>
                        $model->ouvrages()->count(),
                    'technical_objects' =>
                        $model->technicalObjects()->count(),
                    'design_basis_revisions' =>
                        $model->designBasisRevisions()->count(),
                    'standard_profiles' =>
                        $model->standardProfiles()->count(),
                ],
            ],
        ]);
    }
}
