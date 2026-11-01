<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\EngineeringItp;
use App\Models\EngineeringQaEvidence;
use App\Models\EngineeringQaInspection;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringCrossTenantAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_outsider_cannot_view_engineering_modules(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        $outsider = User::factory()->create();

        $this->actingAs($outsider);

        foreach ($this->moduleReadPaths($organizationB, $projectB) as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_viewer_of_other_organization_cannot_view_modules(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $viewerA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $viewerA,
            OrganizationRole::Viewer
        );

        $this->actingAs($viewerA);

        foreach ($this->moduleReadPaths($organizationB, $projectB) as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    public function test_member_cannot_edit_other_organization(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $memberA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $memberA,
            OrganizationRole::Member
        );

        $this->actingAs($memberA)
            ->post('/engineering/'.$organizationB->getKey().'/projects/'.$projectB->getKey().'/itp')
            ->assertForbidden();

        $this->assertDatabaseCount('engineering_itps', 0);
    }

    public function test_viewer_cannot_edit_own_organization(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        $viewer = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationB,
            $viewer,
            OrganizationRole::Viewer
        );

        $this->actingAs($viewer)
            ->post('/engineering/'.$organizationB->getKey().'/projects/'.$projectB->getKey().'/itp')
            ->assertForbidden();

        $this->assertDatabaseCount('engineering_itps', 0);
    }

    public function test_viewer_cannot_approve_own_organization(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        $itp = EngineeringItp::query()->create([
            'organization_id' => (string) $organizationB->getKey(),
            'project_id' => (string) $projectB->getKey(),
            'code' => 'ITP-01',
            'title' => 'Test ITP',
            'status' => EngineeringItp::STATUS_IN_REVIEW,
            'scope_label' => 'Test',
            'reference_documents' => [],
        ]);

        $viewer = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationB,
            $viewer,
            OrganizationRole::Viewer
        );

        $this->actingAs($viewer)
            ->post('/engineering/'.$organizationB->getKey().'/projects/'.$projectB->getKey().'/itp/'.$itp->getKey().'/approve')
            ->assertForbidden();
    }

    public function test_owner_can_view_own_module(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        $this->actingAs($ownerB)
            ->get('/engineering/'.$organizationB->getKey().'/projects/'.$projectB->getKey().'/itp')
            ->assertOk();
    }

    public function test_download_evidence_is_protected_from_other_organizations(): void
    {
        [$ownerB, $organizationB] = $this->organizationWithOwner('Organization B');
        $projectB = $this->projectFor($organizationB, 'P-02');

        $inspection = EngineeringQaInspection::query()->create([
            'organization_id' => (string) $organizationB->getKey(),
            'project_id' => (string) $projectB->getKey(),
            'template_key' => 'test',
            'work_type' => 'test',
            'inspection_code' => 'C-01',
            'title' => 'Test inspection',
            'record_type' => 'test',
            'scope_label' => 'Test',
        ]);

        $evidence = EngineeringQaEvidence::query()->create([
            'engineering_qa_inspection_id' => $inspection->getKey(),
            'organization_id' => (string) $organizationB->getKey(),
            'project_id' => (string) $projectB->getKey(),
            'reference' => 'E-01',
            'document_type' => 'pdf',
            'issued_at' => now()->toDateString(),
            'original_name' => 'test.pdf',
            'disk' => 'local',
            'path' => 'nonexistent.pdf',
            'mime_type' => 'application/pdf',
            'size_bytes' => 0,
            'sha256' => str_repeat('0', 64),
        ]);

        [$ownerA, $organizationA] = $this->organizationWithOwner('Organization A');
        $viewerA = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember(
            $organizationA,
            $viewerA,
            OrganizationRole::Viewer
        );

        $this->actingAs($viewerA)
            ->get('/engineering/'.$organizationB->getKey().'/projects/'.$projectB->getKey().'/qa-control/evidences/'.$evidence->getKey().'/download')
            ->assertForbidden();
    }

    private function organizationWithOwner(string $name): array
    {
        $owner = User::factory()->create();
        $organization = app(OrganizationService::class)->createForOwner($owner, $name);

        return [$owner, $organization];
    }

    private function projectFor(Organization $organization, string $code): Project
    {
        return Project::query()->create([
            'organization_id' => $organization->getKey(),
            'code' => $code,
            'name' => 'Project '.$code,
        ]);
    }

    private function moduleReadPaths(Organization $organization, Project $project): array
    {
        $base = '/engineering/'.$organization->getKey().'/projects/'.$project->getKey();

        return [
            $base.'/itp',
            $base.'/qa-control',
            $base.'/qa-normative',
            $base.'/qa-normative-v2',
            $base.'/qa-normative-v22',
            $base.'/normative-library',
            $base.'/normative-librarian',
            $base.'/normative-registry',
        ];
    }
}
