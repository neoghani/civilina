<?php

namespace App\Http\Controllers\Api;

use App\Enums\DesignBasisCategory;
use App\Http\Controllers\Controller;
use App\Models\DesignBasisRevision;
use App\Models\Organization;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringDesignBasisController extends Controller
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

        $revisions =
            $projectModel
                ->designBasisRevisions()
                ->with('items')
                ->orderByDesc('revision_no')
                ->get();

        return response()->json([
            'data' => $revisions,
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        DesignBasisService $service
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
            'title' => [
                'required',
                'string',
                'max:255',
            ],
            'change_summary' => [
                'nullable',
                'string',
            ],
        ]);

        $revision = $service->createRevision(
            $projectModel,
            $validated['title'],
            $request->user(),
            $validated['change_summary'] ?? null
        );

        return response()->json([
            'data' => $revision,
        ], 201);
    }

    public function addItem(
        Request $request,
        Organization $organization,
        string $project,
        int $revision,
        EngineeringTenantAccessService $access,
        DesignBasisService $service
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

        $revisionModel =
            $this->revisionForProject(
                $projectModel->getKey(),
                $revision
            );

        $validated = $request->validate([
            'category' => [
                'required',
                'string',
            ],
            'key' => [
                'required',
                'string',
                'max:150',
            ],
            'label' => [
                'required',
                'string',
                'max:255',
            ],
            'value' => [
                'required',
            ],
            'unit' => [
                'nullable',
                'string',
                'max:50',
            ],
            'source_type' => [
                'nullable',
                'string',
                'max:80',
            ],
            'source_reference' => [
                'nullable',
                'string',
                'max:255',
            ],
            'is_assumption' => [
                'sometimes',
                'boolean',
            ],
        ]);

        $item = $service->addItem(
            $revisionModel,
            DesignBasisCategory::from(
                $validated['category']
            ),
            $validated['key'],
            $validated['label'],
            $validated['value'],
            $validated['unit'] ?? null,
            $validated['source_type'] ?? null,
            $validated['source_reference'] ?? null,
            (bool) (
                $validated['is_assumption'] ?? false
            )
        );

        return response()->json([
            'data' => $item,
        ], 201);
    }

    public function markForReview(
        Request $request,
        Organization $organization,
        string $project,
        int $revision,
        EngineeringTenantAccessService $access,
        DesignBasisService $service
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

        $revisionModel =
            $this->revisionForProject(
                $projectModel->getKey(),
                $revision
            );

        return response()->json([
            'data' =>
                $service->markForReview(
                    $revisionModel
                ),
        ]);
    }

    public function approve(
        Request $request,
        Organization $organization,
        string $project,
        int $revision,
        EngineeringTenantAccessService $access,
        DesignBasisService $service
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

        $revisionModel =
            $this->revisionForProject(
                $projectModel->getKey(),
                $revision
            );

        return response()->json([
            'data' =>
                $service->approve(
                    $revisionModel
                ),
        ]);
    }

    private function revisionForProject(
        int $projectId,
        int $revisionId
    ): DesignBasisRevision {
        $revision =
            DesignBasisRevision::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($revisionId)
                ->first();

        if (! $revision) {
            throw (new ModelNotFoundException())
                ->setModel(
                    DesignBasisRevision::class,
                    [$revisionId]
                );
        }

        return $revision;
    }
}
