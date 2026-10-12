<?php

namespace Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringWorkspaceOperationsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_sees_engineering_operational_forms_and_approval_actions(): void
    {
        $c = $this->context();

        $this->actingAs($c['owner'])
            ->get($c['url'])
            ->assertOk()
            ->assertSee('data-engineering-operation="create-design-basis"', false)
            ->assertSee('data-engineering-operation="create-technical-object"', false)
            ->assertSee('data-engineering-operation="create-analysis-model"', false)
            ->assertSee('data-engineering-operation="approve-design-basis"', false)
            ->assertSee('data-engineering-operation="approve-technical-revision"', false)
            ->assertSee('data-engineering-operation="approve-analysis-model"', false);
    }

    public function test_member_can_edit_but_does_not_receive_approval_actions(): void
    {
        $c = $this->context();
        $member = User::factory()->create();

        OrganizationMembership::query()->create([
            'organization_id' => $c['organization']->id,
            'user_id' => $member->id,
            'role' => 'member',
            'status' => 'active',
        ]);

        $this->actingAs($member)
            ->get($c['url'])
            ->assertOk()
            ->assertSee('data-engineering-operation="create-design-basis"', false)
            ->assertDontSee('data-engineering-operation="approve-design-basis"', false)
            ->assertDontSee('data-engineering-operation="approve-technical-revision"', false)
            ->assertDontSee('data-engineering-operation="approve-analysis-model"', false);
    }

    public function test_viewer_does_not_receive_editing_forms(): void
    {
        $c = $this->context();
        $viewer = User::factory()->create();

        OrganizationMembership::query()->create([
            'organization_id' => $c['organization']->id,
            'user_id' => $viewer->id,
            'role' => 'viewer',
            'status' => 'active',
        ]);

        $this->actingAs($viewer)
            ->get($c['url'])
            ->assertOk()
            ->assertDontSee('data-engineering-operation=', false)
            ->assertSee('Design Basis')
            ->assertSee('Modèle technique Civilina')
            ->assertSee('Calcul structurel');
    }

    public function test_workspace_exposes_project_and_engineering_api_roots(): void
    {
        $c = $this->context();

        $projectBase = '/api/organizations/'.$c['organization']->public_id.
            '/engineering/projects/'.$c['project']->public_id;
        $engineeringBase = '/api/organizations/'.$c['organization']->public_id.'/engineering';

        $this->actingAs($c['owner'])
            ->get($c['url'])
            ->assertOk()
            ->assertSee('data-api-base="'.$projectBase.'"', false)
            ->assertSee('data-engineering-api-base="'.$engineeringBase.'"', false);
    }

    private function context(): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Civilina Engineering');
        $project = app(EngineeringProjectService::class)->createProject($organization, 'P-001', 'Station de pompage');

        return [
            'owner' => $owner,
            'organization' => $organization,
            'project' => $project,
            'url' => '/engineering/'.$organization->public_id.'/projects/'.$project->public_id.'/workspace',
        ];
    }
}
