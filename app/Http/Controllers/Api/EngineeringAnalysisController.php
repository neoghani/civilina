<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AnalysisModel;
use App\Models\AnalysisModelItem;
use App\Models\DesignBasisRevision;
use App\Models\Organization;
use App\Models\ProjectStandardProfile;
use App\Models\TechnicalObjectRevision;
use App\Services\Engineering\AnalysisModelService;
use App\Services\Engineering\AnalysisRunService;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\RobotStructuralAnalysisAdapter;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringAnalysisController extends Controller
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

        return response()->json([
            'data' =>
                AnalysisModel::query()
                    ->where(
                        'project_id',
                        $projectModel->getKey()
                    )
                    ->with([
                        'items.technicalObjectRevision.technicalObject',
                        'runs',
                    ])
                    ->orderBy('code')
                    ->get(),
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        AnalysisModelService $service
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $validated = $request->validate([
            'design_basis_revision_id' => [
                'required',
                'integer',
            ],
            'project_standard_profile_id' => [
                'nullable',
                'integer',
            ],
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
            'solver_key' => [
                'sometimes',
                'string',
                'max:100',
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
        ]);

        $designBasis =
            DesignBasisRevision::query()
                ->where(
                    'project_id',
                    $projectModel->getKey()
                )
                ->whereKey(
                    $validated[
                        'design_basis_revision_id'
                    ]
                )
                ->firstOrFail();

        $profile =
            isset(
                $validated[
                    'project_standard_profile_id'
                ]
            )
                ? ProjectStandardProfile::query()
                    ->where(
                        'project_id',
                        $projectModel->getKey()
                    )
                    ->whereKey(
                        $validated[
                            'project_standard_profile_id'
                        ]
                    )
                    ->firstOrFail()
                : null;

        $model = $service->createModel(
            $projectModel,
            $designBasis,
            $validated['code'],
            $validated['name'],
            $profile,
            $request->user(),
            $validated['solver_key'] ??
                'robot_structural_analysis',
            $validated['metadata'] ?? []
        );

        return response()->json([
            'data' => $model,
        ], 201);
    }

    public function addItem(
        Request $request,
        Organization $organization,
        string $project,
        int $analysisModel,
        EngineeringTenantAccessService $access,
        AnalysisModelService $service
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $model =
            $this->modelForProject(
                $projectModel->getKey(),
                $analysisModel
            );

        $validated = $request->validate([
            'technical_object_revision_id' => [
                'required',
                'integer',
            ],
            'external_key' => [
                'nullable',
                'string',
                'max:150',
            ],
            'metadata' => [
                'sometimes',
                'array',
            ],
        ]);

        $revision =
            TechnicalObjectRevision::query()
                ->whereKey(
                    $validated[
                        'technical_object_revision_id'
                    ]
                )
                ->firstOrFail();

        $item = $service->addTechnicalRevision(
            $model,
            $revision,
            $validated['external_key'] ?? null,
            $validated['metadata'] ?? []
        );

        return response()->json([
            'data' => $item,
        ], 201);
    }

    public function markForReview(
        Request $request,
        Organization $organization,
        string $project,
        int $analysisModel,
        EngineeringTenantAccessService $access,
        AnalysisModelService $service
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $model =
            $this->modelForProject(
                $projectModel->getKey(),
                $analysisModel
            );

        return response()->json([
            'data' =>
                $service->markForReview(
                    $model
                ),
        ]);
    }

    public function approve(
        Request $request,
        Organization $organization,
        string $project,
        int $analysisModel,
        EngineeringTenantAccessService $access,
        AnalysisModelService $service
    ): JsonResponse {
        $access->authorizeApprove(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $model =
            $this->modelForProject(
                $projectModel->getKey(),
                $analysisModel
            );

        return response()->json([
            'data' =>
                $service->approve(
                    $model
                ),
        ]);
    }

    public function prepareRun(
        Request $request,
        Organization $organization,
        string $project,
        int $analysisModel,
        EngineeringTenantAccessService $access,
        AnalysisRunService $runs,
        RobotStructuralAnalysisAdapter $adapter
    ): JsonResponse {
        $access->authorizeEdit(
            $request->user(),
            $organization
        );

        $projectModel =
            $access->scopedProject(
                $organization,
                $project
            );

        $model =
            $this->modelForProject(
                $projectModel->getKey(),
                $analysisModel
            );

        $run = $runs->prepareRun(
            $model,
            $adapter
        );

        return response()->json([
            'data' => $run,
        ], 201);
    }

    private function modelForProject(
        int $projectId,
        int $modelId
    ): AnalysisModel {
        $model =
            AnalysisModel::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($modelId)
                ->first();

        if (! $model) {
            throw (new ModelNotFoundException())
                ->setModel(
                    AnalysisModel::class,
                    [$modelId]
                );
        }

        return $model;
    }
}
