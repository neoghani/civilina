<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\TechnicalModelService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringTenantApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_active_member_lists_only_current_organization_projects(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $otherOwner =
            User::factory()->create();

        $otherOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $otherOwner,
                    'Other Org'
                );

        $projects =
            app(EngineeringProjectService::class);

        $visible =
            $projects->createProject(
                $organization,
                'P-001',
                'Visible'
            );

        $projects->createProject(
            $otherOrganization,
            'P-002',
            'Hidden'
        );

        $response =
            $this->actingAs($owner)
                ->getJson(
                    $this->base(
                        $organization
                    ).'/projects'
                );

        $response
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.public_id',
                $visible->public_id
            );
    }

    public function test_member_can_create_tenant_owned_project(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $member =
            $this->member(
                $organization,
                'member'
            );

        $response =
            $this->actingAs($member)
                ->postJson(
                    $this->base(
                        $organization
                    ).'/projects',
                    [
                        'code' => 'SP-01',
                        'name' => 'Station',
                    ]
                );

        $response
            ->assertCreated()
            ->assertJsonPath(
                'data.code',
                'SP-01'
            );

        $this->assertDatabaseHas(
            'projects',
            [
                'organization_id' =>
                    $organization->id,
                'code' => 'SP-01',
            ]
        );
    }

    public function test_viewer_cannot_create_project(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $viewer =
            $this->member(
                $organization,
                'viewer'
            );

        $this->actingAs($viewer)
            ->postJson(
                $this->base(
                    $organization
                ).'/projects',
                [
                    'code' => 'SP-01',
                    'name' => 'Station',
                ]
            )
            ->assertForbidden();
    }

    public function test_cross_tenant_project_lookup_returns_not_found(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $otherOwner =
            User::factory()->create();

        $otherOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $otherOwner,
                    'Other Org'
                );

        $foreignProject =
            app(EngineeringProjectService::class)
                ->createProject(
                    $otherOrganization,
                    'P-002',
                    'Foreign'
                );

        $this->actingAs($owner)
            ->getJson(
                $this->base(
                    $organization
                ).'/projects/'.
                $foreignProject->public_id
            )
            ->assertNotFound();
    }

    public function test_member_creates_design_basis_but_cannot_approve_it(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $member =
            $this->member(
                $organization,
                'member'
            );

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        $create =
            $this->actingAs($member)
                ->postJson(
                    $this->projectBase(
                        $organization,
                        $project
                    ).'/design-basis',
                    [
                        'title' => 'Design Basis',
                    ]
                )
                ->assertCreated();

        $revisionId =
            $create->json('data.id');

        $this->actingAs($member)
            ->postJson(
                $this->projectBase(
                    $organization,
                    $project
                ).'/design-basis/'.
                $revisionId.
                '/review'
            )
            ->assertOk();

        $this->actingAs($member)
            ->postJson(
                $this->projectBase(
                    $organization,
                    $project
                ).'/design-basis/'.
                $revisionId.
                '/approve'
            )
            ->assertForbidden();
    }

    public function test_manager_can_approve_design_basis(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $manager =
            $this->member(
                $organization,
                'manager'
            );

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        $service =
            app(DesignBasisService::class);

        $revision =
            $service->createRevision(
                $project,
                'Design Basis',
                $owner
            );

        $revision =
            $service->markForReview(
                $revision
            );

        $this->actingAs($manager)
            ->postJson(
                $this->projectBase(
                    $organization,
                    $project
                ).'/design-basis/'.
                $revision->id.
                '/approve'
            )
            ->assertOk()
            ->assertJsonPath(
                'data.status',
                'approved'
            );
    }

    public function test_member_creates_technical_object_inside_project(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $member =
            $this->member(
                $organization,
                'member'
            );

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Project'
                );

        $this->actingAs($member)
            ->postJson(
                $this->projectBase(
                    $organization,
                    $project
                ).'/technical-objects',
                [
                    'code' => 'STR-COL-001',
                    'name' => 'Column',
                    'object_type' => 'column',
                    'discipline' => 'structure',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.project_id',
                $project->id
            );
    }

    public function test_knowledge_api_never_lists_foreign_organization_standard(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $otherOwner =
            User::factory()->create();

        $otherOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $otherOwner,
                    'Other Org'
                );

        \App\Models\StandardDocument::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'code' => 'STD-A',
                'title' => 'Visible',
            ]);

        \App\Models\StandardDocument::query()
            ->create([
                'organization_id' =>
                    $otherOrganization->id,
                'code' => 'STD-B',
                'title' => 'Hidden',
            ]);

        $this->actingAs($owner)
            ->getJson(
                $this->base(
                    $organization
                ).'/knowledge/standards'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data'
            )
            ->assertJsonPath(
                'data.0.code',
                'STD-A'
            );
    }

    public function test_analysis_api_rejects_foreign_project_design_basis(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $otherOwner =
            User::factory()->create();

        $otherOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $otherOwner,
                    'Other Org'
                );

        $projects =
            app(EngineeringProjectService::class);

        $project =
            $projects->createProject(
                $organization,
                'P-001',
                'Project'
            );

        $foreignProject =
            $projects->createProject(
                $otherOrganization,
                'P-002',
                'Foreign'
            );

        $db =
            app(DesignBasisService::class)
                ->createRevision(
                    $foreignProject,
                    'Foreign DB',
                    $otherOwner
                );

        $db =
            app(DesignBasisService::class)
                ->markForReview($db);

        $db =
            app(DesignBasisService::class)
                ->approve($db);

        $this->actingAs($owner)
            ->postJson(
                $this->projectBase(
                    $organization,
                    $project
                ).'/analysis-models',
                [
                    'design_basis_revision_id' =>
                        $db->id,
                    'code' => 'RSA-001',
                    'name' => 'Robot',
                ]
            )
            ->assertNotFound();
    }

    public function test_outsider_cannot_read_engineering_api(): void
    {
        [$owner, $organization] =
            $this->organizationContext();

        $outsider =
            User::factory()->create();

        $this->actingAs($outsider)
            ->getJson(
                $this->base(
                    $organization
                ).'/projects'
            )
            ->assertForbidden();
    }

    private function organizationContext(): array
    {
        $owner =
            User::factory()->create();

        $organization =
            app(OrganizationService::class)
                ->createForOwner(
                    $owner,
                    'Civilina Engineering'
                );

        return [
            $owner,
            $organization,
        ];
    }

    private function member(
        $organization,
        string $role
    ): User {
        $user =
            User::factory()->create();

        OrganizationMembership::query()
            ->create([
                'organization_id' =>
                    $organization->id,
                'user_id' =>
                    $user->id,
                'role' =>
                    $role,
                'status' =>
                    'active',
            ]);

        return $user;
    }

    private function base(
        $organization
    ): string {
        return
            '/api/organizations/'.
            $organization->public_id.
            '/engineering';
    }

    private function projectBase(
        $organization,
        $project
    ): string {
        return
            $this->base(
                $organization
            ).
            '/projects/'.
            $project->public_id;
    }
}
