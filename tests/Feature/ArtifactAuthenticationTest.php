<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Artifact;
use App\Models\Organization;
use App\Models\User;
use App\Services\Artifacts\ArtifactService;
use App\Services\Artifacts\ArtifactTenantAccessService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ArtifactAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private function makeArtifact(
        User $owner,
        ?Organization $organization = null
    ): Artifact {
        $artifact = app(ArtifactService::class)->create([
            'type' => Artifact::TYPE_DASHBOARD,
            'title' => 'Test artifact',
            'status' => Artifact::STATUS_DRAFT,
            'payload' => ['rows' => []],
            'provenance' => ['origin' => 'test'],
            'created_by' => $owner->getKey(),
            'owner_id' => $owner->getKey(),
        ]);

        if ($organization !== null) {
            $artifact = app(ArtifactTenantAccessService::class)
                ->assignToOrganization($artifact, $organization);
        }

        return $artifact;
    }

    public function test_anonymous_cannot_create_artifact(): void
    {
        $this->postJson('/api/artifacts', [
            'type' => Artifact::TYPE_DASHBOARD,
            'title' => 'Anonymous artifact',
            'payload' => ['rows' => []],
        ])->assertStatus(401);

        $this->assertDatabaseCount('artifacts', 0);
    }

    public function test_anonymous_cannot_read_artifact(): void
    {
        $owner = User::factory()->create();
        $artifact = $this->makeArtifact($owner);

        $this->getJson('/api/artifacts/'.$artifact->public_id)
            ->assertStatus(401);
    }

    public function test_anonymous_cannot_revise_artifact(): void
    {
        $owner = User::factory()->create();
        $artifact = $this->makeArtifact($owner);

        $this->postJson(
            '/api/artifacts/'.$artifact->public_id.'/revisions',
            [
                'payload' => ['rows' => ['updated']],
            ]
        )->assertStatus(401);

        $this->assertDatabaseCount('artifact_revisions', 1);
    }

    public function test_anonymous_cannot_transition_artifact(): void
    {
        $owner = User::factory()->create();
        $artifact = $this->makeArtifact($owner);

        $this->postJson(
            '/api/artifacts/'.$artifact->public_id.'/transitions',
            [
                'status' => Artifact::STATUS_IN_REVIEW,
            ]
        )->assertStatus(401);
    }

    public function test_created_by_and_owner_id_are_forced_to_authenticated_user(): void
    {
        $authenticated = User::factory()->create();
        $other = User::factory()->create();
        $this->actingAs($authenticated);

        $response = $this->postJson('/api/artifacts', [
            'type' => Artifact::TYPE_DASHBOARD,
            'title' => 'Ownership test',
            'payload' => ['rows' => []],
            'created_by' => $other->getKey(),
            'owner_id' => $other->getKey(),
        ])->assertCreated();

        $artifact = Artifact::query()
            ->where('public_id', $response->json('data.public_id'))
            ->firstOrFail();

        $this->assertSame(
            (int) $authenticated->getKey(),
            (int) $artifact->owner_id
        );

        $this->assertSame(
            (int) $authenticated->getKey(),
            (int) $artifact->created_by
        );
    }

    public function test_user_cannot_read_another_organizations_artifact(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $artifactB = $this->makeArtifact($ownerB, $organizationB);

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $userA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $userA,
            OrganizationRole::Member
        );

        $this->actingAs($userA);

        $this->getJson('/api/artifacts/'.$artifactB->public_id)
            ->assertStatus(403);
    }

    public function test_user_cannot_revise_another_organizations_artifact(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $artifactB = $this->makeArtifact($ownerB, $organizationB);

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $userA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $userA,
            OrganizationRole::Member
        );

        $this->actingAs($userA);

        $this->postJson(
            '/api/artifacts/'.$artifactB->public_id.'/revisions',
            [
                'payload' => ['rows' => ['stolen']],
            ]
        )->assertStatus(403);

        $this->assertDatabaseCount('artifact_revisions', 1);
    }

    public function test_user_cannot_transition_another_organizations_artifact(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $artifactB = $this->makeArtifact($ownerB, $organizationB);

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $userA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $userA,
            OrganizationRole::Member
        );

        $this->actingAs($userA);

        $this->postJson(
            '/api/artifacts/'.$artifactB->public_id.'/transitions',
            [
                'status' => Artifact::STATUS_IN_REVIEW,
            ]
        )->assertStatus(403);
    }

    public function test_owner_can_read_and_revise_own_artifact(): void
    {
        $owner = User::factory()->create();
        $artifact = $this->makeArtifact($owner);
        $this->actingAs($owner);

        $this->getJson('/api/artifacts/'.$artifact->public_id)
            ->assertOk();

        $this->postJson(
            '/api/artifacts/'.$artifact->public_id.'/revisions',
            [
                'payload' => ['rows' => ['updated']],
            ]
        )->assertCreated();
    }

    public function test_organization_member_can_view_organization_artifact(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)
            ->createForOwner($owner, 'Org');

        $member = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organization,
            $member,
            OrganizationRole::Member
        );

        $artifact = $this->makeArtifact($owner, $organization);

        $this->actingAs($member);

        $this->getJson('/api/artifacts/'.$artifact->public_id)
            ->assertOk();
    }

    public function test_organization_scoped_creation_still_works(): void
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)
            ->createForOwner($owner, 'Org');

        $this->actingAs($owner);

        $this->postJson(
            '/api/organizations/'.$organization->public_id.'/artifacts',
            [
                'type' => Artifact::TYPE_DASHBOARD,
                'title' => 'Org artifact',
                'payload' => ['rows' => []],
            ]
        )->assertCreated();

        $this->assertDatabaseCount('artifacts', 1);
    }

    private function organizationWithOwner(string $name): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)
            ->createForOwner($owner, $name);

        return [$owner, $organization];
    }
}
