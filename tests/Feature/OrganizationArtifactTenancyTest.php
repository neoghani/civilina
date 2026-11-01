<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Artifact;
use App\Models\User;
use App\Services\Artifacts\ArtifactTenantAccessService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationArtifactTenancyTest extends TestCase
{
    use RefreshDatabase;

    public function test_artifact_can_be_assigned_to_one_organization(): void
    {
        $owner = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Civilina Engineering'
        );

        $artifact = $this->createArtifact();

        $artifact = app(
            ArtifactTenantAccessService::class
        )->assignToOrganization(
            $artifact,
            $organization
        );

        $this->assertSame(
            $organization->id,
            (int) $artifact->organization_id
        );
    }

    public function test_artifact_cannot_be_reassigned_to_another_organization(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $organizations = app(
            OrganizationService::class
        );

        $organizationA =
            $organizations->createForOwner(
                $ownerA,
                'Organization A'
            );

        $organizationB =
            $organizations->createForOwner(
                $ownerB,
                'Organization B'
            );

        $artifact = $this->createArtifact();

        $access = app(
            ArtifactTenantAccessService::class
        );

        $access->assignToOrganization(
            $artifact,
            $organizationA
        );

        $this->expectException(
            DomainException::class
        );

        $access->assignToOrganization(
            $artifact->refresh(),
            $organizationB
        );
    }

    public function test_member_only_lists_artifacts_from_current_organization(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();
        $member = User::factory()->create();

        $organizations = app(
            OrganizationService::class
        );

        $organizationA =
            $organizations->createForOwner(
                $ownerA,
                'Organization A'
            );

        $organizationB =
            $organizations->createForOwner(
                $ownerB,
                'Organization B'
            );

        $organizations->addOrUpdateMember(
            $organizationA,
            $member,
            OrganizationRole::Viewer
        );

        $artifactA = $this->createArtifact(
            'WBS — Organization A'
        );

        $artifactB = $this->createArtifact(
            'WBS — Organization B'
        );

        $tenantAccess = app(
            ArtifactTenantAccessService::class
        );

        $tenantAccess->assignToOrganization(
            $artifactA,
            $organizationA
        );

        $tenantAccess->assignToOrganization(
            $artifactB,
            $organizationB
        );

        $this
            ->actingAs($member)
            ->getJson(
                '/api/organizations/'.
                $organizationA->public_id.
                '/artifacts'
            )
            ->assertOk()
            ->assertJsonFragment([
                'public_id' =>
                    $artifactA->public_id,
            ])
            ->assertJsonMissing([
                'public_id' =>
                    $artifactB->public_id,
            ]);
    }

    public function test_foreign_user_cannot_list_organization_artifacts(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Restricted'
        );

        $artifact = $this->createArtifact();

        app(
            ArtifactTenantAccessService::class
        )->assignToOrganization(
            $artifact,
            $organization
        );

        $this
            ->actingAs($outsider)
            ->getJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts'
            )
            ->assertForbidden();
    }

    public function test_cross_tenant_artifact_lookup_returns_not_found(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $organizations = app(
            OrganizationService::class
        );

        $organizationA =
            $organizations->createForOwner(
                $ownerA,
                'Organization A'
            );

        $organizationB =
            $organizations->createForOwner(
                $ownerB,
                'Organization B'
            );

        $artifact = $this->createArtifact();

        app(
            ArtifactTenantAccessService::class
        )->assignToOrganization(
            $artifact,
            $organizationB
        );

        $this
            ->actingAs($ownerA)
            ->getJson(
                '/api/organizations/'.
                $organizationA->public_id.
                '/artifacts/'.
                $artifact->public_id
            )
            ->assertNotFound();
    }

    public function test_viewer_can_view_but_cannot_edit_artifact(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $organizations = app(
            OrganizationService::class
        );

        $organization =
            $organizations->createForOwner(
                $owner,
                'QA QC'
            );

        $organizations->addOrUpdateMember(
            $organization,
            $viewer,
            OrganizationRole::Viewer
        );

        $artifact = $this->createArtifact();

        $access = app(
            ArtifactTenantAccessService::class
        );

        $access->assignToOrganization(
            $artifact,
            $organization
        );

        $this->assertTrue(
            $access->canView(
                $viewer,
                $organization,
                $artifact->refresh()
            )
        );

        $this->assertFalse(
            $access->canEdit(
                $viewer,
                $organization,
                $artifact->refresh()
            )
        );
    }

    public function test_manager_can_approve_artifact(): void
    {
        $owner = User::factory()->create();
        $manager = User::factory()->create();

        $organizations = app(
            OrganizationService::class
        );

        $organization =
            $organizations->createForOwner(
                $owner,
                'Project Controls'
            );

        $organizations->addOrUpdateMember(
            $organization,
            $manager,
            OrganizationRole::Manager
        );

        $artifact = $this->createArtifact();

        $access = app(
            ArtifactTenantAccessService::class
        );

        $access->assignToOrganization(
            $artifact,
            $organization
        );

        $this->assertTrue(
            $access->canApprove(
                $manager,
                $organization,
                $artifact->refresh()
            )
        );
    }

    private function createArtifact(
        string $title = 'WBS — SP-01'
    ): Artifact {
        $creator = User::factory()->create();

        $response = $this
            ->actingAs($creator)
            ->postJson(
                '/api/artifacts',
            [
                'type' => 'wbs',
                'title' => $title,
                'payload' => [
                    'rows' => [],
                ],
                'provenance' => [
                    'origin' =>
                        'organization-tenancy-test',
                ],
                'source' => 'test',
            ]
        );

        $response->assertCreated();

        return Artifact::query()
            ->where(
                'public_id',
                $response->json(
                    'data.public_id'
                )
            )
            ->firstOrFail();
    }
}
