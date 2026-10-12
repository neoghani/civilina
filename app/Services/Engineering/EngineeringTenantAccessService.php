<?php

namespace App\Services\Engineering;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Project;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

class EngineeringTenantAccessService
{
    private const VIEW_ROLES = [
        'owner',
        'admin',
        'manager',
        'member',
        'viewer',
    ];

    private const EDIT_ROLES = [
        'owner',
        'admin',
        'manager',
        'member',
    ];

    private const APPROVE_ROLES = [
        'owner',
        'admin',
        'manager',
    ];

    public function authorizeView(
        User $user,
        Organization $organization
    ): OrganizationMembership {
        return $this->authorizeRoles(
            $user,
            $organization,
            self::VIEW_ROLES
        );
    }

    public function authorizeEdit(
        User $user,
        Organization $organization
    ): OrganizationMembership {
        return $this->authorizeRoles(
            $user,
            $organization,
            self::EDIT_ROLES
        );
    }

    public function authorizeApprove(
        User $user,
        Organization $organization
    ): OrganizationMembership {
        return $this->authorizeRoles(
            $user,
            $organization,
            self::APPROVE_ROLES
        );
    }

    public function scopedProject(
        Organization $organization,
        string $publicId
    ): Project {
        $project = Project::query()
            ->where(
                'organization_id',
                $organization->getKey()
            )
            ->where(
                'public_id',
                $publicId
            )
            ->first();

        if (! $project) {
            throw (new ModelNotFoundException())
                ->setModel(
                    Project::class,
                    [$publicId]
                );
        }

        return $project;
    }

    private function authorizeRoles(
        User $user,
        Organization $organization,
        array $allowedRoles
    ): OrganizationMembership {
        $membership =
            OrganizationMembership::query()
                ->where(
                    'organization_id',
                    $organization->getKey()
                )
                ->where(
                    'user_id',
                    $user->getKey()
                )
                ->where(
                    'status',
                    'active'
                )
                ->first();

        $role =
            $membership
                ? (
                    is_object($membership->role)
                        && property_exists(
                            $membership->role,
                            'value'
                        )
                            ? $membership->role->value
                            : (string) $membership->role
                )
                : null;

        if (
            ! $membership ||
            ! in_array(
                $role,
                $allowedRoles,
                true
            )
        ) {
            throw new AuthorizationException(
                'You do not have access to this engineering resource.'
            );
        }

        return $membership;
    }
}
