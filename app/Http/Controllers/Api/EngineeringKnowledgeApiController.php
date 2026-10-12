<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NormativeRequirement;
use App\Models\Organization;
use App\Models\StandardDocument;
use App\Services\Engineering\EngineeringTenantAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EngineeringKnowledgeApiController extends Controller
{
    public function standards(
        Request $request,
        Organization $organization,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $documents =
            StandardDocument::query()
                ->where(
                    'organization_id',
                    $organization->getKey()
                )
                ->with([
                    'editions' => fn ($query) =>
                        $query->orderByDesc(
                            'publication_date'
                        ),
                ])
                ->orderBy('code')
                ->get();

        return response()->json([
            'data' => $documents,
        ]);
    }

    public function requirements(
        Request $request,
        Organization $organization,
        EngineeringTenantAccessService $access
    ): JsonResponse {
        $access->authorizeView(
            $request->user(),
            $organization
        );

        $requirements =
            NormativeRequirement::query()
                ->whereHas(
                    'section.edition.document',
                    fn ($query) =>
                        $query->where(
                            'organization_id',
                            $organization->getKey()
                        )
                )
                ->with([
                    'section.edition.document',
                ])
                ->orderBy('id')
                ->get();

        return response()->json([
            'data' => $requirements,
        ]);
    }

    public function projectProfiles(
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
                    ->standardProfiles()
                    ->with([
                        'standards.edition.document',
                        'requirementDecisions.requirement',
                    ])
                    ->orderByDesc(
                        'revision_no'
                    )
                    ->get(),
        ]);
    }
}
