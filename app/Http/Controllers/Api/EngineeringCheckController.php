<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EngineeringCheck;
use App\Models\Organization;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringCheckController extends Controller
{
    public function index(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $checks =
            EngineeringCheck::query()
                ->whereHas(
                    'resultSet.analysisRun.analysisModel',
                    fn ($query) =>
                        $query->where(
                            'project_id',
                            $projectModel->getKey()
                        )
                )
                ->with([
                    'technicalObjectRevision.technicalObject',
                    'normativeRequirement.section.edition.document',
                    'findings',
                ])
                ->orderByDesc('id')
                ->get();

        return response()->json([
            'data' => $checks,
        ]);
    }

    public function show(
        Request $request,
        Organization $organization,
        string $project,
        int $check,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $model =
            EngineeringCheck::query()
                ->whereKey($check)
                ->whereHas(
                    'resultSet.analysisRun.analysisModel',
                    fn ($query) =>
                        $query->where(
                            'project_id',
                            $projectModel->getKey()
                        )
                )
                ->with([
                    'resultSet.analysisRun.analysisModel',
                    'technicalObjectRevision.technicalObject',
                    'normativeRequirement.section.edition.document',
                    'findings',
                ])
                ->first();

        if (! $model) {
            throw (new ModelNotFoundException())
                ->setModel(
                    EngineeringCheck::class,
                    [$check]
                );
        }

        return response()->json([
            'data' => $model,
        ]);
    }
}
