<?php

namespace App\Http\Controllers\Api;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Organizations\OrganizationAccessService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class OrganizationMembershipController extends Controller
{
    public function index(
        Organization $organization
    ): JsonResponse {
        Gate::authorize(
            'manageMembers',
            $organization
        );

        $memberships = $organization
            ->memberships()
            ->with('user:id,name,email')
            ->orderBy('id')
            ->get()
            ->map(
                fn (
                    OrganizationMembership $membership
                ): array => $this->serializeMembership(
                    $membership
                )
            )
            ->values();

        return response()->json([
            'data' => $memberships,
        ]);
    }

    public function store(
        Request $request,
        Organization $organization,
        OrganizationService $organizations,
        OrganizationAccessService $access
    ): JsonResponse {
        Gate::authorize(
            'manageMembers',
            $organization
        );

        $validated = $request->validate([
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
            ],
            'role' => [
                'required',
                Rule::enum(
                    OrganizationRole::class
                ),
            ],
            'status' => [
                'sometimes',
                Rule::enum(
                    OrganizationMembershipStatus::class
                ),
            ],
        ]);

        $role = OrganizationRole::from(
            $validated['role']
        );

        if (
            $role === OrganizationRole::Owner &&
            ! $access->can(
                $request->user(),
                $organization,
                'manage_owners'
            )
        ) {
            abort(403);
        }

        $status =
            isset($validated['status'])
                ? OrganizationMembershipStatus::from(
                    $validated['status']
                )
                : OrganizationMembershipStatus::Active;

        $membership =
            $organizations->addOrUpdateMember(
                $organization,
                User::query()->findOrFail(
                    $validated['user_id']
                ),
                $role,
                $status
            );

        $membership->load(
            'user:id,name,email'
        );

        return response()->json(
            [
                'data' =>
                    $this->serializeMembership(
                        $membership
                    ),
            ],
            201
        );
    }

    private function serializeMembership(
        OrganizationMembership $membership
    ): array {
        return [
            'id' => $membership->getKey(),
            'role' => $membership->role->value,
            'status' => $membership->status->value,
            'joined_at' =>
                $membership->joined_at
                    ?->toISOString(),
            'user' => [
                'id' => $membership->user->getKey(),
                'name' => $membership->user->name,
                'email' => $membership->user->email,
            ],
        ];
    }
}
