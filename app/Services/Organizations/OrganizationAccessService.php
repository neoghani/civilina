<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;

class OrganizationAccessService
{
    public function membershipFor(User $user, Organization $organization): ?OrganizationMembership
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->first();
    }

    public function can(User $user, Organization $organization, string $ability): bool
    {
        $membership = $this->membershipFor($user, $organization);

        return $membership ? $membership->allows($ability) : false;
    }

    public function visibleOrganizationsFor(User $user): Collection
    {
        return Organization::query()->visibleTo($user)->orderBy('name')->get();
    }

    public function hasActiveMembership(User $user, Organization $organization): bool
    {
        return OrganizationMembership::query()
            ->where('organization_id', $organization->getKey())
            ->where('user_id', $user->getKey())
            ->where('status', OrganizationMembershipStatus::Active->value)
            ->exists();
    }
}
