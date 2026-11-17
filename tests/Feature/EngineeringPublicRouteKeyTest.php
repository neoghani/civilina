<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Organization and project URLs carry the public UUID: sequential ids
 * would let anyone count the firms and projects on the platform.
 */
class EngineeringPublicRouteKeyTest extends TestCase
{
    use RefreshDatabase;

    public function test_numeric_ids_no_longer_resolve(): void
    {
        [$owner, $organization, $project] = $this->firm();

        $this->actingAs($owner);

        foreach (['/itp', '/qa-control', '/qa-normative', '/workspace-safe'] as $suffix) {
            $this->get('/engineering/'.$organization->getKey().'/projects/'.$project->getKey().$suffix)
                ->assertNotFound();

            $this->get('/engineering/'.$organization->public_id.'/projects/'.$project->public_id.$suffix)
                ->assertOk();
        }
    }

    public function test_generated_links_use_public_ids_only(): void
    {
        [$owner, $organization, $project] = $this->firm();

        $html = $this->actingAs($owner)
            ->get(route('engineering.workspace', ['organization' => $organization, 'project' => $project]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('/engineering/'.$organization->public_id.'/projects/'.$project->public_id.'/itp', $html);
        $this->assertDoesNotMatchRegularExpression('#/engineering/\d+/projects/#', $html);

        $html = $this->get('/engineering/'.$organization->public_id.'/projects/'.$project->public_id.'/qa-control')
            ->assertOk()
            ->getContent();

        $this->assertDoesNotMatchRegularExpression('#/engineering/\d+/projects/#', $html);
    }

    public function test_project_of_another_firm_is_not_reachable_through_own_organization(): void
    {
        [$owner, $organization] = $this->firm();
        [, , $otherProject] = $this->firm('BET Autre');

        $this->actingAs($owner)
            ->get('/engineering/'.$organization->public_id.'/projects/'.$otherProject->public_id.'/workspace-safe')
            ->assertNotFound();
    }

    private function firm(string $name = 'BET Pilote'): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, $name);
        $project = Project::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => 'P-001',
            'name' => 'Projet pilote',
        ]);

        return [$owner, $organization, $project];
    }
}
