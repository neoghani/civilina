<?php

namespace App\Services\Organizations;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class OrganizationService
{
    public function createForOwner(User $owner, string $name, ?string $slug = null): Organization
    {
        return DB::transaction(function () use ($owner, $name, $slug): Organization {
            $organization = Organization::query()->create([
                'name' => trim($name),
                'slug' => $this->uniqueSlug($slug ?: $name),
            ]);

            OrganizationMembership::query()->create([
                'organization_id' => $organization->getKey(),
                'user_id' => $owner->getKey(),
                'role' => OrganizationRole::Owner,
                'status' => OrganizationMembershipStatus::Active,
                'joined_at' => now(),
            ]);

            return $organization->refresh();
        });
    }

    public function addOrUpdateMember(
        Organization $organization,
        User $user,
        OrganizationRole $role,
        OrganizationMembershipStatus $status = OrganizationMembershipStatus::Active
    ): OrganizationMembership {
        return DB::transaction(function () use ($organization, $user, $role, $status): OrganizationMembership {
            $membership = OrganizationMembership::query()->firstOrNew([
                'organization_id' => $organization->getKey(),
                'user_id' => $user->getKey(),
            ]);

            $membership->fill([
                'role' => $role,
                'status' => $status,
                'joined_at' => $status === OrganizationMembershipStatus::Active
                    ? ($membership->joined_at ?: now())
                    : $membership->joined_at,
            ]);

            $membership->save();

            return $membership->refresh();
        });
    }

    private function uniqueSlug(string $source): string
    {
        $base = Str::slug($source) ?: 'organization';
        $candidate = $base;
        $suffix = 2;

        while (Organization::query()->where('slug', $candidate)->exists()) {
            $candidate = $base.'-'.$suffix;
            $suffix++;
        }

        return $candidate;
    }
}
