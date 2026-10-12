<?php

namespace Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_open_engineering_workspace(): void
    {
        $c = $this->context();

        $this->actingAs($c['owner'])
            ->get($c['url'])
            ->assertOk()
            ->assertSee('Civilina')
            ->assertSee('Engineering Core')
            ->assertSee($c['project']->code)
            ->assertSee($c['project']->name);
    }

    public function test_viewer_can_open_workspace_in_read_only_mode(): void
    {
        $c = $this->context();

        $viewer =
            User::factory()->create();

        OrganizationMembership::query()
            ->create([
                'organization_id' =>
                    $c['organization']->id,
                'user_id' => $viewer->id,
                'role' => 'viewer',
                'status' => 'active',
            ]);

        $this->actingAs($viewer)
            ->get($c['url'])
            ->assertOk()
            ->assertSee(
                'data-can-edit="0"',
                false
            )
            ->assertDontSee(
                'Ajout rapide'
            );
    }

    public function test_member_can_open_workspace_with_edit_capability(): void
    {
        $c = $this->context();

        $member =
            User::factory()->create();

        OrganizationMembership::query()
            ->create([
                'organization_id' =>
                    $c['organization']->id,
                'user_id' => $member->id,
                'role' => 'member',
                'status' => 'active',
            ]);

        $this->actingAs($member)
            ->get($c['url'])
            ->assertOk()
            ->assertSee(
                'data-can-edit="1"',
                false
            )
            ->assertSee(
                'Ajout rapide'
            );
    }

    public function test_outsider_cannot_open_workspace(): void
    {
        $c = $this->context();

        $outsider =
            User::factory()->create();

        $this->actingAs($outsider)
            ->get($c['url'])
            ->assertForbidden();
    }

    public function test_foreign_project_is_not_exposed_under_current_organization(): void
    {
        $c = $this->context();

        $foreignOwner =
            User::factory()->create();

        $foreignOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $foreignOwner,
                    'Foreign Organization'
                );

        $foreignProject =
            app(EngineeringProjectService::class)
                ->createProject(
                    $foreignOrganization,
                    'P-999',
                    'Foreign Project'
                );

        $url =
            '/engineering/'.
            $c['organization']->public_id.
            '/projects/'.
            $foreignProject->public_id.
            '/workspace';

        $this->actingAs($c['owner'])
            ->get($url)
            ->assertNotFound();
    }

    public function test_workspace_embeds_tenant_scoped_api_base(): void
    {
        $c = $this->context();

        $expected =
            '/api/organizations/'.
            $c['organization']->public_id.
            '/engineering/projects/'.
            $c['project']->public_id;

        $this->actingAs($c['owner'])
            ->get($c['url'])
            ->assertOk()
            ->assertSee(
                'data-api-base="'.
                $expected.
                '"',
                false
            );
    }

    private function context(): array
    {
        $owner =
            User::factory()->create();

        $organization =
            app(OrganizationService::class)
                ->createForOwner(
                    $owner,
                    'Civilina Engineering'
                );

        $project =
            app(EngineeringProjectService::class)
                ->createProject(
                    $organization,
                    'P-001',
                    'Station de pompage'
                );

        return [
            'owner' => $owner,
            'organization' => $organization,
            'project' => $project,
            'url' =>
                '/engineering/'.
                $organization->public_id.
                '/projects/'.
                $project->public_id.
                '/workspace',
        ];
    }
}
