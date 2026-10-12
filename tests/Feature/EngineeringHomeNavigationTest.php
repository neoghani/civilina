<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringHomeNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_engineering_entry_requires_login(): void
    {
        $this->get('/engineering')
            ->assertRedirect('/login');
    }

    public function test_owner_engineering_entry_redirects_to_first_project_workspace(): void
    {
        $owner = User::factory()->create();

        $organization = app(OrganizationService::class)
            ->createForOwner(
                $owner,
                'Civilina Engineering'
            );

        $project = app(EngineeringProjectService::class)
            ->createProject(
                $organization,
                'P-001',
                'Projet pilote'
            );

        $expected = route(
            'engineering.workspace',
            [
                'organization' => $organization->public_id,
                'project' => $project->public_id,
            ],
            false
        );

        $this->actingAs($owner)
            ->get('/engineering')
            ->assertRedirect($expected);
    }

    public function test_workspace_brand_links_back_to_civilina_home(): void
    {
        $owner = User::factory()->create();

        $organization = app(OrganizationService::class)
            ->createForOwner(
                $owner,
                'Civilina Engineering'
            );

        $project = app(EngineeringProjectService::class)
            ->createProject(
                $organization,
                'P-001',
                'Projet pilote'
            );

        $url = route(
            'engineering.workspace',
            [
                'organization' => $organization->public_id,
                'project' => $project->public_id,
            ],
            false
        );

        $this->actingAs($owner)
            ->get($url)
            ->assertOk()
            ->assertSee(
                'href="'.url('/').'"',
                false
            )
            ->assertSee(
                "Retour à l'accueil Civilina",
                false
            );
    }

    public function test_home_points_engineering_links_to_canonical_entry(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(
                'href="'.route('engineering.entry').'"',
                false
            );
    }
}
