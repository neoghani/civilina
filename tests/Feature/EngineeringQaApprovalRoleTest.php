<?php

namespace Tests\Feature;

use App\Enums\OrganizationRole;
use App\Models\EngineeringQaHandover;
use App\Models\EngineeringQaInspection;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Inspection verdicts, client authorizations and handover decisions are
 * approvals: members (edit role) may prepare them, only owner/admin/manager
 * may decide.
 */
class EngineeringQaApprovalRoleTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_update_inspection_without_decision(): void
    {
        [$organization, $project, $inspection] = $this->scope();
        $member = $this->memberWithRole($organization, OrganizationRole::Member);

        $this->actingAs($member)
            ->post($this->inspectionUrl($organization, $project, $inspection), [
                'status' => EngineeringQaInspection::STATUS_REQUESTED,
                'authorization_status' => EngineeringQaInspection::AUTH_REQUESTED,
                'notes' => 'Prêt pour contrôle',
            ])
            ->assertRedirect();

        $this->assertSame(EngineeringQaInspection::STATUS_REQUESTED, $inspection->fresh()->status);
    }

    public function test_member_cannot_set_inspection_verdict(): void
    {
        [$organization, $project, $inspection] = $this->scope();
        $member = $this->memberWithRole($organization, OrganizationRole::Member);

        $this->actingAs($member)
            ->post($this->inspectionUrl($organization, $project, $inspection), [
                'status' => EngineeringQaInspection::STATUS_CONFORMING,
                'authorization_status' => EngineeringQaInspection::AUTH_NONE,
            ])
            ->assertForbidden();

        $this->assertSame(EngineeringQaInspection::STATUS_PLANNED, $inspection->fresh()->status);
    }

    public function test_member_cannot_grant_client_authorization(): void
    {
        [$organization, $project, $inspection] = $this->scope();
        $member = $this->memberWithRole($organization, OrganizationRole::Member);

        $this->actingAs($member)
            ->post($this->inspectionUrl($organization, $project, $inspection), [
                'status' => EngineeringQaInspection::STATUS_PLANNED,
                'authorization_status' => EngineeringQaInspection::AUTH_GRANTED,
                'client_authorized_at' => now()->toDateTimeString(),
            ])
            ->assertForbidden();

        $this->assertSame(EngineeringQaInspection::AUTH_NONE, $inspection->fresh()->authorization_status);
    }

    public function test_member_cannot_withdraw_existing_verdict(): void
    {
        [$organization, $project, $inspection] = $this->scope();
        $inspection->update(['status' => EngineeringQaInspection::STATUS_NONCONFORMING]);
        $member = $this->memberWithRole($organization, OrganizationRole::Member);

        $this->actingAs($member)
            ->post($this->inspectionUrl($organization, $project, $inspection), [
                'status' => EngineeringQaInspection::STATUS_PLANNED,
                'authorization_status' => EngineeringQaInspection::AUTH_NONE,
            ])
            ->assertForbidden();

        $this->assertSame(EngineeringQaInspection::STATUS_NONCONFORMING, $inspection->fresh()->status);
    }

    public function test_manager_can_set_inspection_verdict(): void
    {
        [$organization, $project, $inspection] = $this->scope();
        $manager = $this->memberWithRole($organization, OrganizationRole::Manager);

        $this->actingAs($manager)
            ->post($this->inspectionUrl($organization, $project, $inspection), [
                'status' => EngineeringQaInspection::STATUS_CONFORMING,
                'authorization_status' => EngineeringQaInspection::AUTH_NONE,
            ])
            ->assertRedirect();

        $this->assertSame(EngineeringQaInspection::STATUS_CONFORMING, $inspection->fresh()->status);
    }

    public function test_member_cannot_accept_handover_but_manager_can(): void
    {
        [$organization, $project] = $this->scope();
        $handover = $this->handover($organization, $project);
        $url = '/engineering/'.$organization->getKey().'/projects/'.$project->getKey().'/qa-control/handovers/'.$handover->getKey();
        $payload = ['status' => 'accepted', 'accepted_at' => now()->toDateTimeString()];

        $member = $this->memberWithRole($organization, OrganizationRole::Member);
        $this->actingAs($member)->post($url, $payload)->assertForbidden();
        $this->assertSame('draft', $handover->fresh()->status);

        $this->actingAs($member)->post($url, ['status' => 'requested'])->assertRedirect();
        $this->assertSame('requested', $handover->fresh()->status);

        $manager = $this->memberWithRole($organization, OrganizationRole::Manager);
        $this->actingAs($manager)->post($url, $payload)->assertRedirect();
        $this->assertSame('accepted', $handover->fresh()->status);
    }

    /**
     * @return array{0: Organization, 1: Project, 2: EngineeringQaInspection}
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
        $inspection = EngineeringQaInspection::query()->create([
            'organization_id' => (string) $organization->getKey(),
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

        return [$organization, $project, $inspection];
    }

    private function handover(Organization $organization, Project $project): EngineeringQaHandover
    {
        return EngineeringQaHandover::query()->create([
            'organization_id' => (string) $organization->getKey(),
            'project_id' => (string) $project->getKey(),
            'code' => 'H-01',
            'from_work_type' => 'coffrage',
            'to_work_type' => 'betonnage',
            'scope_label' => 'Test',
            'criteria' => [],
        ]);
    }

    private function memberWithRole(Organization $organization, OrganizationRole $role): User
    {
        $user = User::factory()->create();
        app(OrganizationService::class)->addOrUpdateMember($organization, $user, $role);

        return $user;
    }

    private function inspectionUrl(Organization $organization, Project $project, EngineeringQaInspection $inspection): string
    {
        return '/engineering/'.$organization->getKey().'/projects/'.$project->getKey().'/qa-control/inspections/'.$inspection->getKey();
    }
}
