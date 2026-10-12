<?php

namespace App\Http\Controllers\Api;

use App\Enums\ActivityStatus;
use App\Enums\WorkPackageStatus;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\ProjectOuvrage;
use App\Models\ProjectZone;
use App\Models\WbsNode;
use App\Models\WorkPackage;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class EngineeringProjectStructureController extends Controller
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
            'data' => [
                'wbs_nodes' =>
                    $projectModel
                        ->wbsNodes()
                        ->orderBy('sort_order')
                        ->orderBy('id')
                        ->get(),
                'work_packages' =>
                    $projectModel
                        ->workPackages()
                        ->orderBy('sort_order')
                        ->orderBy('code')
                        ->get(),
                'activities' =>
                    $projectModel
                        ->activities()
                        ->orderBy('sort_order')
                        ->orderBy('code')
                        ->get(),
                'zones' =>
                    $projectModel
                        ->zones()
                        ->orderBy('code')
                        ->get(),
                'ouvrages' =>
                    $projectModel
                        ->ouvrages()
                        ->orderBy('code')
                        ->get(),
                'materials' =>
                    $projectModel
                        ->materials()
                        ->orderBy('code')
                        ->get(),
            ],
        ]);
    }

    public function storeWbsNode(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
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
            'code' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'integer'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $parent =
            isset($validated['parent_id'])
                ? WbsNode::query()
                    ->where(
                        'project_id',
                        $projectModel->getKey()
                    )
                    ->whereKey(
                        $validated['parent_id']
                    )
                    ->firstOrFail()
                : null;

        $node = $projects->createWbsNode(
            $projectModel,
            $validated['code'],
            $validated['name'],
            $parent,
            $validated['description'] ?? null,
            (int) ($validated['sort_order'] ?? 0)
        );

        return response()->json([
            'data' => $node,
        ], 201);
    }

    public function storeWorkPackage(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
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
            'wbs_node_id' => ['required', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'ouvrage_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'status' => [
                'sometimes',
                Rule::enum(WorkPackageStatus::class),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $workPackage = $projects->createWorkPackage(
            $projectModel,
            $this->wbsForProject(
                $projectModel->getKey(),
                $validated['wbs_node_id']
            ),
            $validated['code'],
            $validated['name'],
            $this->zoneForProject(
                $projectModel->getKey(),
                $validated['zone_id'] ?? null
            ),
            $this->ouvrageForProject(
                $projectModel->getKey(),
                $validated['ouvrage_id'] ?? null
            ),
            $validated['description'] ?? null,
            WorkPackageStatus::from(
                $validated['status']
                    ?? WorkPackageStatus::Draft->value
            ),
            (int) ($validated['sort_order'] ?? 0)
        );

        return response()->json([
            'data' => $workPackage,
        ], 201);
    }

    public function storeActivity(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
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
            'work_package_id' => ['required', 'integer'],
            'zone_id' => ['nullable', 'integer'],
            'ouvrage_id' => ['nullable', 'integer'],
            'code' => ['required', 'string', 'max:150'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
            'activity_type' => ['nullable', 'string', 'max:100'],
            'status' => [
                'sometimes',
                Rule::enum(ActivityStatus::class),
            ],
            'sort_order' => ['sometimes', 'integer', 'min:0'],
        ]);

        $workPackage =
            WorkPackage::query()
                ->where(
                    'project_id',
                    $projectModel->getKey()
                )
                ->whereKey(
                    $validated['work_package_id']
                )
                ->firstOrFail();

        $activity = $projects->createActivity(
            $projectModel,
            $workPackage,
            $validated['code'],
            $validated['name'],
            $this->zoneForProject(
                $projectModel->getKey(),
                $validated['zone_id'] ?? null
            ),
            $this->ouvrageForProject(
                $projectModel->getKey(),
                $validated['ouvrage_id'] ?? null
            ),
            $validated['description'] ?? null,
            $validated['activity_type'] ?? null,
            ActivityStatus::from(
                $validated['status']
                    ?? ActivityStatus::Planned->value
            ),
            (int) ($validated['sort_order'] ?? 0)
        );

        return response()->json([
            'data' => $activity,
        ], 201);
    }

    public function storeZone(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
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
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $zone = $projects->createZone(
            $projectModel,
            $validated['code'],
            $validated['name'],
            $validated['description'] ?? null
        );

        return response()->json([
            'data' => $zone,
        ], 201);
    }

    public function storeOuvrage(
        Request $request,
        Organization $organization,
        string $project,
        EngineeringTenantAccessService $access,
        EngineeringProjectService $projects
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
            'code' => ['required', 'string', 'max:100'],
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string'],
        ]);

        $ouvrage = $projects->createOuvrage(
            $projectModel,
            $validated['code'],
            $validated['name'],
            $validated['description'] ?? null
        );

        return response()->json([
            'data' => $ouvrage,
        ], 201);
    }

    private function wbsForProject(
        int $projectId,
        int $id
    ): WbsNode {
        return WbsNode::query()
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
}
