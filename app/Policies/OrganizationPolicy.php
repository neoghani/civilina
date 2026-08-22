<?php

namespace App\Policies;

use App\Models\Organization;
use App\Models\User;
use App\Services\Organizations\OrganizationAccessService;

class OrganizationPolicy
{
    public function __construct(
        private readonly OrganizationAccessService $access
    ) {
    }

    public function view(
        User $user,
        Organization $organization
    ): bool {
        return $this->access->hasActiveMembership(
            $user,
            $organization
        );
    }

    public function update(
        User $user,
        Organization $organization
    ): bool {
        return $this->access->can(
            $user,
            $organization,
            'manage_organization'
        );
    }

    public function manageMembers(
        User $user,
        Organization $organization
    ): bool {
        return $this->access->can(
            $user,
            $organization,
            'manage_members'
        );
    }

    public function manageRoles(
        User $user,
        Organization $organization
    ): bool {
        return $this->access->can(
            $user,
            $organization,
            'manage_roles'
        );
    }
}
