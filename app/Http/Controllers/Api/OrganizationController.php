<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\Organizations\OrganizationAccessService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class OrganizationController extends Controller
{
    public function index(
        Request $request,
        OrganizationAccessService $access
    ): JsonResponse {
        $organizations = $access
            ->visibleOrganizationsFor(
                $request->user()
            )
            ->map(
                fn (Organization $organization): array =>
                    $this->serializeOrganization(
                        $organization
                    )
            )
            ->values();

        return response()->json([
            'data' => $organizations,
        ]);
    }

    public function show(
        Request $request,
        Organization $organization
    ): JsonResponse {
        Gate::authorize(
            'view',
            $organization
        );

        return response()->json([
            'data' => $this->serializeOrganization(
                $organization
            ),
        ]);
    }

    private function serializeOrganization(
        Organization $organization
    ): array {
        return [
            'public_id' => $organization->public_id,
            'name' => $organization->name,
            'slug' => $organization->slug,
        ];
    }
}
