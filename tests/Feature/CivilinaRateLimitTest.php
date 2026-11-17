<?php

namespace Tests\Feature;

use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CivilinaRateLimitTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_engineering_page_is_limited_per_account(): void
    {
        config(['civilina.rate_limits.app' => 3]);
        [$owner, $base] = $this->firm();

        $this->actingAs($owner);

        for ($i = 0; $i < 3; $i++) {
            $this->get($base.'/qa-control')->assertOk();
        }

        $this->get($base.'/itp')->assertStatus(429);

        // Another account keeps its own allowance.
        [$other, $otherBase] = $this->firm('BET Deux');
        $this->actingAs($other)->get($otherBase.'/qa-control')->assertOk();
    }

    public function test_agent_runs_have_a_stricter_limit(): void
    {
        config(['civilina.rate_limits.heavy' => 2]);
        [$owner, $base] = $this->firm();

        $this->actingAs($owner);

        for ($i = 0; $i < 2; $i++) {
            $this->post($base.'/qa-normative-v22/run-agent')->assertStatus(302);
        }

        $this->post($base.'/qa-normative-v22/run-agent')->assertStatus(429);
        $this->post($base.'/qa-control/generate')->assertStatus(429);
        $this->get($base.'/qa-control')->assertOk();
    }

    public function test_guests_are_limited_per_ip(): void
    {
        config(['civilina.rate_limits.app' => 2]);

        $this->get('/login')->assertOk();
        $this->get('/login')->assertOk();
        $this->get('/login')->assertStatus(429);
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

        return [$owner, '/engineering/'.$organization->public_id.'/projects/'.$project->public_id];
    }
}
