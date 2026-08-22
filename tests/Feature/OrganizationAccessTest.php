<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Organizations\OrganizationAccessService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_organization_with_an_active_owner(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Civilina Engineering');

        $this->assertNotNull($organization->public_id);
        $this->assertSame('civilina-engineering', $organization->slug);

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $owner->id)
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Owner, $membership->role);
        $this->assertSame(OrganizationMembershipStatus::Active, $membership->status);
        $this->assertNotNull($membership->joined_at);
    }

    public function test_user_only_sees_organizations_with_active_membership(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $service = app(OrganizationService::class);

        $visible = $service->createForOwner($user, 'Visible Organization');
        $suspended = $service->createForOwner($otherUser, 'Suspended Organization');

        $service->addOrUpdateMember(
            $suspended,
            $user,
            OrganizationRole::Member,
            OrganizationMembershipStatus::Suspended
        );

        $organizations = app(OrganizationAccessService::class)->visibleOrganizationsFor($user);

        $this->assertTrue($organizations->contains('id', $visible->id));
        $this->assertFalse($organizations->contains('id', $suspended->id));
    }

    public function test_role_permissions_are_deterministic(): void
    {
        $this->assertTrue(OrganizationRole::Owner->allows('manage_owners'));
        $this->assertFalse(OrganizationRole::Admin->allows('manage_owners'));
        $this->assertTrue(OrganizationRole::Manager->allows('approve_artifacts'));
        $this->assertFalse(OrganizationRole::Member->allows('approve_artifacts'));
        $this->assertTrue(OrganizationRole::Viewer->allows('view_artifacts'));
        $this->assertFalse(OrganizationRole::Viewer->allows('edit_artifacts'));
    }

    public function test_suspended_membership_has_no_access(): void
    {
        $owner = User::factory()->create();
        $user = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Project Controls');

        app(OrganizationService::class)->addOrUpdateMember(
            $organization,
            $user,
            OrganizationRole::Admin,
            OrganizationMembershipStatus::Suspended
        );

        $access = app(OrganizationAccessService::class);

        $this->assertFalse($access->can($user, $organization, 'view_artifacts'));
        $this->assertFalse($access->hasActiveMembership($user, $organization));
    }

    public function test_membership_is_unique_per_user_and_organization(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'QA QC');

        $this->expectException(QueryException::class);

        OrganizationMembership::query()->create([
            'organization_id' => $organization->id,
            'user_id' => $owner->id,
            'role' => OrganizationRole::Member,
            'status' => OrganizationMembershipStatus::Active,
            'joined_at' => now(),
        ]);
    }

    public function test_add_or_update_member_does_not_duplicate_membership(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $service = app(OrganizationService::class);
        $organization = $service->createForOwner($owner, 'Construction');

        $service->addOrUpdateMember($organization, $member, OrganizationRole::Member);
        $service->addOrUpdateMember($organization, $member, OrganizationRole::Manager);

        $this->assertDatabaseCount('organization_memberships', 2);

        $membership = OrganizationMembership::query()
            ->where('organization_id', $organization->id)
            ->where('user_id', $member->id)
            ->firstOrFail();

        $this->assertSame(OrganizationRole::Manager, $membership->role);
    }

    public function test_user_and_organization_relationships_are_available(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Engineering');

        $this->assertTrue($owner->organizations()->whereKey($organization->id)->exists());
        $this->assertTrue($organization->users()->whereKey($owner->id)->exists());
    }
}
