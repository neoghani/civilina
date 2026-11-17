<?php

namespace Tests\Feature;

use App\Models\EngineeringQaInspection;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * What a pilot firm sees on the QA/QC screens (found while testing the
 * pilot workspace): French messages and labels, the Engineering navigation,
 * no internal server path.
 */
class EngineeringPilotUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_messages_are_in_french(): void
    {
        [$owner, $base] = $this->firm();

        $this->actingAs($owner)
            ->from($base.'/qa-control')
            ->post($base.'/qa-control/generate', ['work_type' => 'reinforced_concrete', 'scope_type' => 'project'])
            ->assertSessionHasErrors(['scope_label' => 'Le champ libellé de la portée est obligatoire.']);
    }

    public function test_qa_control_shows_navigation_title_and_french_statuses(): void
    {
        [$owner, $base, $project] = $this->firm();
        $this->inspection($project);

        $this->actingAs($owner)
            ->get($base.'/qa-control')
            ->assertOk()
            ->assertSee('<title>Contrôles &amp; PV · Civilina Engineering</title>', false)
            ->assertSee('civ-eng-nav', false)
            ->assertSee('Statut : <strong>Planifié</strong>', false)
            ->assertSee('Autorisation : <strong>Non requise</strong>', false)
            ->assertDontSee('Statut : <strong>planned</strong>', false);
    }

    public function test_normative_agent_hides_the_server_path_from_firms(): void
    {
        [$owner, $base] = $this->firm();

        $this->actingAs($owner)
            ->get($base.'/qa-normative')
            ->assertOk()
            ->assertSee('civ-eng-nav', false)
            ->assertSee('Base normative')
            ->assertDontSee('standards_inbox');

        config(['civilina.platform_staff' => [$owner->email]]);

        $this->get($base.'/qa-normative')->assertOk()->assertSee('standards_inbox');
    }

    private function firm(): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'BET Pilote');
        $project = Project::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => 'P-001',
            'name' => 'Projet pilote',
        ]);

        return [$owner, '/engineering/'.$organization->public_id.'/projects/'.$project->public_id, $project];
    }

    private function inspection(Project $project): void
    {
        EngineeringQaInspection::query()->create([
            'organization_id' => (string) $project->organization_id,
            'project_id' => (string) $project->getKey(),
            'template_key' => 'test',
            'work_type' => 'test',
            'inspection_code' => 'C-01',
            'title' => 'Contrôle test',
            'record_type' => 'test',
            'scope_label' => 'Test',
            'status' => EngineeringQaInspection::STATUS_PLANNED,
            'authorization_status' => EngineeringQaInspection::AUTH_NONE,
        ]);
    }
}
