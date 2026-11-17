<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The shared normative corpus (standards files, scans, indexing and
 * corpus-wide edits) is managed by Civilina staff only, even for the owner
 * of an engineering-firm organization.
 */
class CivilinaPlatformStaffAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_firm_owner_is_denied_on_corpus_management_routes(): void
    {
        [$owner, $organization, $project] = $this->scope();
        config(['civilina.platform_staff' => []]);

        $this->actingAs($owner);

        foreach ($this->staffGetPaths($organization, $project) as $path) {
            $this->get($path)->assertForbidden();
        }

        foreach ($this->staffPostPaths($organization, $project) as $path) {
            $this->post($path)->assertForbidden();
        }

        $base = $this->base($organization, $project);

        $this->patch($base.'/normative-library/standards/1', ['title' => 'x'])
            ->assertForbidden();
        $this->patch($base.'/normative-library/requirements/1', ['requirement_type' => 'x', 'requirement_text' => 'x'])
            ->assertForbidden();
        $this->patch($base.'/normative-librarian/1', [])
            ->assertForbidden();
        $this->patch($base.'/normative-librarian/canonical-review/1/validate', [])
            ->assertForbidden();
    }

    public function test_firm_owner_keeps_read_access_to_published_registry_and_library(): void
    {
        [$owner, $organization, $project] = $this->scope();
        $base = $this->base($organization, $project);

        $this->actingAs($owner)->get($base.'/normative-registry')->assertOk();
        $this->actingAs($owner)->get($base.'/normative-library')->assertOk();
    }

    public function test_corpus_controls_are_hidden_from_firm_users_and_shown_to_staff(): void
    {
        [$owner, $organization, $project] = $this->scope();
        $base = $this->base($organization, $project);

        foreach (['/qa-normative-v22' => 'Indexer 100 fichiers', '/qa-normative' => 'Scanner le lot suivant'] as $path => $label) {
            config(['civilina.platform_staff' => []]);
            $this->actingAs($owner)->get($base.$path)->assertOk()->assertDontSee($label);

            config(['civilina.platform_staff' => [$owner->email]]);
            $this->actingAs($owner)->get($base.$path)->assertOk()->assertSee($label);
        }
    }

    public function test_listed_staff_member_passes_the_staff_gate(): void
    {
        [$owner, $organization, $project] = $this->scope();
        config(['civilina.platform_staff' => [' '.strtoupper($owner->email).' ']]);

        $this->actingAs($owner)
            ->get($this->base($organization, $project).'/normative-librarian')
            ->assertOk();
    }

    public function test_staff_membership_does_not_bypass_tenant_access(): void
    {
        [, $organization, $project] = $this->scope();
        $staffOutsider = User::factory()->create();
        config(['civilina.platform_staff' => [$staffOutsider->email]]);

        $this->actingAs($staffOutsider)
            ->get($this->base($organization, $project).'/normative-librarian')
            ->assertForbidden();
    }

    /**
     * @return array{0: User, 1: Organization, 2: Project}
     */
    private function scope(): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, 'Bureau pilote');
        $project = Project::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => 'P-01',
            'name' => 'Projet P-01',
        ]);

        return [$owner, $organization, $project];
    }

    private function base(Organization $organization, Project $project): string
    {
        return '/engineering/'.$organization->getKey().'/projects/'.$project->getKey();
    }

    private function staffGetPaths(Organization $organization, Project $project): array
    {
        $base = $this->base($organization, $project);

        return [
            $base.'/normative-librarian',
            $base.'/normative-librarian/1',
            $base.'/normative-librarian/1/inline',
            $base.'/normative-librarian/canonical-review',
            $base.'/normative-deep-review',
            $base.'/normative-deep-review/1',
            $base.'/normative-source-review',
            $base.'/normative-source-review/1',
            $base.'/normative-library/standards/1/sources/1/inline',
        ];
    }

    private function staffPostPaths(Organization $organization, Project $project): array
    {
        $base = $this->base($organization, $project);

        return [
            $base.'/normative-librarian/scan',
            $base.'/normative-librarian/refine',
            $base.'/normative-librarian/publish',
            $base.'/normative-librarian/export',
            $base.'/normative-librarian/audit',
            $base.'/normative-librarian/physical-library/build',
            $base.'/normative-librarian/recover-references',
            $base.'/normative-librarian/ocr-queue',
            $base.'/normative-deep-review/1/decision',
            $base.'/normative-source-review/1/decision',
            $base.'/qa-normative/scan',
            $base.'/qa-normative-v2/scan',
            $base.'/qa-normative-v22/refresh',
            $base.'/qa-normative-v22/scan',
        ];
    }
}
