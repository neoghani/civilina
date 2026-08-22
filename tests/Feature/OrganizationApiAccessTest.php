<?php

namespace Tests\Feature;

use App\Enums\OrganizationMembershipStatus;
use App\Enums\OrganizationRole;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationApiAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_only_lists_active_organizations(): void
    {
        $user = User::factory()->create();
        $otherOwner = User::factory()->create();

        $service = app(
            OrganizationService::class
        );

        $visible = $service->createForOwner(
            $user,
            'Visible Organization'
        );

        $hidden = $service->createForOwner(
            $otherOwner,
            'Hidden Organization'
        );

        $service->addOrUpdateMember(
            $hidden,
            $user,
            OrganizationRole::Member,
            OrganizationMembershipStatus::Suspended
        );

        $response = $this
            ->actingAs($user)
            ->getJson('/api/organizations');

        $response
            ->assertOk()
            ->assertJsonFragment([
                'public_id' =>
                    $visible->public_id,
            ])
            ->assertJsonMissing([
                'public_id' =>
                    $hidden->public_id,
            ]);
    }

    public function test_active_member_can_view_organization(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Engineering'
        );

        app(
            OrganizationService::class
        )->addOrUpdateMember(
            $organization,
            $member,
            OrganizationRole::Viewer
        );

        $this
            ->actingAs($member)
            ->getJson(
                '/api/organizations/'.
                $organization->public_id
            )
            ->assertOk()
            ->assertJsonPath(
                'data.public_id',
                $organization->public_id
            );
    }

    public function test_user_cannot_view_foreign_organization(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Restricted Organization'
        );

        $this
            ->actingAs($outsider)
            ->getJson(
                '/api/organizations/'.
                $organization->public_id
            )
            ->assertForbidden();
    }

    public function test_owner_can_add_member(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Project Controls'
        );

        $this
            ->actingAs($owner)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/members',
                [
                    'user_id' => $member->id,
                    'role' =>
                        OrganizationRole::Manager
                            ->value,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.role',
                OrganizationRole::Manager
                    ->value
            );

        $this->assertDatabaseHas(
            'organization_memberships',
            [
                'organization_id' =>
                    $organization->id,
                'user_id' =>
                    $member->id,
                'role' =>
                    OrganizationRole::Manager
                        ->value,
            ]
        );
    }

    public function test_regular_member_cannot_manage_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $candidate = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Construction'
        );

        app(
            OrganizationService::class
        )->addOrUpdateMember(
            $organization,
            $member,
            OrganizationRole::Member
        );

        $this
            ->actingAs($member)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/members',
                [
                    'user_id' =>
                        $candidate->id,
                    'role' =>
                        OrganizationRole::Viewer
                            ->value,
                ]
            )
            ->assertForbidden();
    }

    public function test_admin_cannot_create_another_owner(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create();
        $candidate = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Executive'
        );

        app(
            OrganizationService::class
        )->addOrUpdateMember(
            $organization,
            $admin,
            OrganizationRole::Admin
        );

        $this
            ->actingAs($admin)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/members',
                [
                    'user_id' =>
                        $candidate->id,
                    'role' =>
                        OrganizationRole::Owner
                            ->value,
                ]
            )
            ->assertForbidden();
    }

    public function test_owner_can_list_members(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'QA QC'
        );

        app(
            OrganizationService::class
        )->addOrUpdateMember(
            $organization,
            $member,
            OrganizationRole::Viewer
        );

        $this
            ->actingAs($owner)
            ->getJson(
                '/api/organizations/'.
                $organization->public_id.
                '/members'
            )
            ->assertOk()
            ->assertJsonCount(
                2,
                'data'
            );
    }
}
