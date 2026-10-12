<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\ProjectOuvrage;
use App\Models\ProjectZone;
use App\Models\TechnicalObject;
use App\Models\TechnicalObjectRevision;
use App\Models\WbsNode;
use App\Models\WorkPackage;
use App\Services\Engineering\EngineeringTenantAccessService;
use App\Services\Engineering\TechnicalModelService;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringTechnicalModelController extends Controller
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
                $projectModel
                    ->technicalObjects()
                    ->with([
                        'revisions' => fn ($query) =>
                            $query->orderByDesc(
                                'revision_no'
                            ),
                    ])
                    ->orderBy('code')
                    ->get(),
        ]);
    }

    public function storeObject(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        TechnicalModelService $service
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
            'code' => [
                'required',
                'string',
                'max:150',
            ],
            'name' => [
                'required',
                'string',
                'max:255',
            ],
            'object_type' => [
                'required',
                'string',
                'max:100',
            ],
            'discipline' => [
                'required',
                'string',
                'max:100',
            ],
            'parent_id' => [
                'nullable',
                'integer',
            ],
            'wbs_node_id' => [
                'nullable',
                'integer',
            ],
            'work_package_id' => [
                'nullable',
                'integer',
            ],
            'activity_id' => [
                'nullable',
                'integer',
            ],
            'zone_id' => [
                'nullable',
                'integer',
            ],
            'ouvrage_id' => [
                'nullable',
                'integer',
            ],
        ]);

        $object = $service->createObject(
            $projectModel,
            $validated['code'],
            $validated['name'],
            $validated['object_type'],
            $validated['discipline'],
            $this->objectForProject(
                $projectModel->getKey(),
                $validated['parent_id'] ?? null
            ),
            $this->wbsForProject(
                $projectModel->getKey(),
                $validated['wbs_node_id'] ?? null
            ),
            $this->zoneForProject(
                $projectModel->getKey(),
                $validated['zone_id'] ?? null
            ),
            $this->ouvrageForProject(
                $projectModel->getKey(),
                $validated['ouvrage_id'] ?? null
            ),
            $this->workPackageForProject(
                $projectModel->getKey(),
                $validated['work_package_id'] ?? null
            ),
            $this->activityForProject(
                $projectModel->getKey(),
                $validated['activity_id'] ?? null
            )
        );

        return response()->json([
            'data' => $object,
        ], 201);
    }

    public function storeRevision(
        Request $request,
        Organization $organization,
        string $project,
        int $object,
        EngineeringTenantAccessService $access,
        TechnicalModelService $service
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

        $objectModel =
            $this->requiredObjectForProject(
                $projectModel->getKey(),
                $object
            );

        $validated = $request->validate([
            'geometry' => [
                'required',
                'array',
                'min:1',
            ],
            'properties' => [
                'sometimes',
                'array',
            ],
            'assumptions' => [
                'sometimes',
                'array',
            ],
            'material_id' => [
                'nullable',
                'integer',
            ],
            'provenance' => [
                'sometimes',
                'array',
            ],
            'change_summary' => [
                'nullable',
                'string',
            ],
        ]);

        $material =
            isset($validated['material_id'])
                ? $projectModel
                    ->materials()
                    ->whereKey(
                        $validated['material_id']
                    )
                    ->firstOrFail()
                : null;

        $revision = $service->createRevision(
            $objectModel,
            $validated['geometry'],
            $validated['properties'] ?? [],
            $validated['assumptions'] ?? [],
            $material,
            $request->user(),
            $validated['provenance'] ?? [],
            $validated['change_summary'] ?? null
        );

        return response()->json([
            'data' => $revision,
        ], 201);
    }

    public function markRevisionForReview(
        Request $request,
        Organization $organization,
        string $project,
        int $object,
        int $revision,
        EngineeringTenantAccessService $access,
        TechnicalModelService $service
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

        $objectModel =
            $this->requiredObjectForProject(
                $projectModel->getKey(),
                $object
            );

        $revisionModel =
            $this->revisionForObject(
                $objectModel->getKey(),
                $revision
            );

        return response()->json([
            'data' =>
                $service->markForReview(
                    $revisionModel
                ),
        ]);
    }

    public function approveRevision(
        Request $request,
        Organization $organization,
        string $project,
        int $object,
        int $revision,
        EngineeringTenantAccessService $access,
        TechnicalModelService $service
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

        $objectModel =
            $this->requiredObjectForProject(
                $projectModel->getKey(),
                $object
            );

        $revisionModel =
            $this->revisionForObject(
                $objectModel->getKey(),
                $revision
            );

        return response()->json([
            'data' =>
                $service->approve(
                    $revisionModel
                ),
        ]);
    }

    private function requiredObjectForProject(
        int $projectId,
        int $id
    ): TechnicalObject {
        $object =
            TechnicalObject::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->first();

        if (! $object) {
            throw (new ModelNotFoundException())
                ->setModel(
                    TechnicalObject::class,
                    [$id]
                );
        }

        return $object;
    }

    private function objectForProject(
        int $projectId,
        ?int $id
    ): ?TechnicalObject {
        return $id === null
            ? null
            : $this->requiredObjectForProject(
                $projectId,
                $id
            );
    }

    private function wbsForProject(
        int $projectId,
        ?int $id
    ): ?WbsNode {
        return $id === null
            ? null
            : WbsNode::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->firstOrFail();
    }

    private function zoneForProject(
        int $projectId,
        ?int $id
    ): ?ProjectZone {
        return $id === null
            ? null
            : ProjectZone::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->firstOrFail();
    }

    private function ouvrageForProject(
        int $projectId,
        ?int $id
    ): ?ProjectOuvrage {
        return $id === null
            ? null
            : ProjectOuvrage::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->firstOrFail();
    }

    private function workPackageForProject(
        int $projectId,
        ?int $id
    ): ?WorkPackage {
        return $id === null
            ? null
            : WorkPackage::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->firstOrFail();
    }

    private function activityForProject(
        int $projectId,
        ?int $id
    ): ?Activity {
        return $id === null
            ? null
            : Activity::query()
                ->where(
                    'project_id',
                    $projectId
                )
                ->whereKey($id)
                ->firstOrFail();
    }

    private function revisionForObject(
        int $objectId,
        int $revisionId
    ): TechnicalObjectRevision {
        $revision =
            TechnicalObjectRevision::query()
                ->where(
                    'technical_object_id',
                    $objectId
                )
                ->whereKey($revisionId)
                ->first();

        if (! $revision) {
            throw (new ModelNotFoundException())
                ->setModel(
                    TechnicalObjectRevision::class,
                    [$revisionId]
                );
        }

        return $revision;
    }
}
