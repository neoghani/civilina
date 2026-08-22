<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\Artifact;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OrganizationArtifactCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_creates_artifact_directly_in_organization(): void
    {
        $owner = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Civilina Engineering'
        );

        $response = $this
            ->actingAs($owner)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts',
                $this->artifactPayload()
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.organization_id',
                $organization->id
            )
            ->assertJsonPath(
                'data.current_revision.revision_label',
                'DRAFT-01'
            );

        $artifact = Artifact::query()
            ->where(
                'public_id',
                $response->json(
                    'data.public_id'
                )
            )
            ->firstOrFail();

        $this->assertSame(
            $organization->id,
            (int) $artifact->organization_id
        );
    }

    public function test_member_can_create_artifact_in_organization(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();

        $service = app(
            OrganizationService::class
        );

        $organization =
            $service->createForOwner(
                $owner,
                'Project Controls'
            );

        $service->addOrUpdateMember(
            $organization,
            $member,
            OrganizationRole::Member
        );

        $this
            ->actingAs($member)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts',
                $this->artifactPayload(
                    'WBS — Member'
                )
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.organization_id',
                $organization->id
            );
    }

    public function test_viewer_cannot_create_artifact(): void
    {
        $owner = User::factory()->create();
        $viewer = User::factory()->create();

        $service = app(
            OrganizationService::class
        );

        $organization =
            $service->createForOwner(
                $owner,
                'QA QC'
            );

        $service->addOrUpdateMember(
            $organization,
            $viewer,
            OrganizationRole::Viewer
        );

        $this
            ->actingAs($viewer)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts',
                $this->artifactPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'artifacts',
            0
        );
    }

    public function test_outsider_cannot_create_artifact(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Restricted'
        );

        $this
            ->actingAs($outsider)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts',
                $this->artifactPayload()
            )
            ->assertForbidden();

        $this->assertDatabaseCount(
            'artifacts',
            0
        );
    }

    public function test_scoped_creation_keeps_existing_artifact_contract(): void
    {
        $owner = User::factory()->create();

        $organization = app(
            OrganizationService::class
        )->createForOwner(
            $owner,
            'Engineering'
        );

        $response = $this
            ->actingAs($owner)
            ->postJson(
                '/api/organizations/'.
                $organization->public_id.
                '/artifacts',
                $this->artifactPayload(
                    'Risk Register — SP-01',
                    'risk_register'
                )
            );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.type',
                'risk_register'
            )
            ->assertJsonPath(
                'data.title',
                'Risk Register — SP-01'
            )
            ->assertJsonPath(
                'data.current_revision.revision_label',
                'DRAFT-01'
            );
    }

    public function test_legacy_global_endpoint_remains_available(): void
    {
        $response = $this->postJson(
            '/api/artifacts',
            $this->artifactPayload(
                'Legacy WBS'
            )
        );

        $response->assertCreated();

        $artifact = Artifact::query()
            ->where(
                'public_id',
                $response->json(
                    'data.public_id'
                )
            )
            ->firstOrFail();

        $this->assertNull(
            $artifact->organization_id
        );
    }

    private function artifactPayload(
        string $title = 'WBS — SP-01',
        string $type = 'wbs'
    ): array {
        return [
            'type' => $type,
            'title' => $title,
            'payload' => [
                'rows' => [],
            ],
            'provenance' => [
                'origin' =>
                    'organization-scoped-test',
            ],
            'source' => 'test',
        ];
    }
}
