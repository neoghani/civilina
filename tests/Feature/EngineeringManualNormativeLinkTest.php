<?php

namespace Tests\Feature;

use App\Models\EngineeringQaInspection;
use App\Models\EngineeringQaNormativeLink;
use App\Models\Project;
use App\Models\User;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Audit E4: the manual link form wrote only the legacy column
 * engineering_qa_inspection_id while the inspection reads inspection_id,
 * so an engineer's manual reference never appeared on the control.
 */
class EngineeringManualNormativeLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_reference_appears_on_its_inspection(): void
    {
        [$owner, $base, $inspection] = $this->scope();

        $this->actingAs($owner)
            ->from($base.'/qa-normative')
            ->post($base.'/qa-normative/inspections/'.$inspection->getKey().'/manual', [
                'source_label' => 'Fascicule 65',
                'source_reference' => 'CCTG Fascicule 65 (2017)',
                'clause_code' => '8.3.2',
            ])
            ->assertRedirect($base.'/qa-normative');

        $link = EngineeringQaNormativeLink::query()->sole();
        $this->assertSame($inspection->getKey(), (int) $link->inspection_id);
        $this->assertSame($inspection->getKey(), (int) DB::table('engineering_qa_normative_links')->value('engineering_qa_inspection_id'));
        $this->assertCount(1, $inspection->fresh()->normativeLinks);

        $this->get($base.'/qa-normative')
            ->assertOk()
            ->assertSee('CCTG Fascicule 65 (2017)')
            ->assertSee('8.3.2');
    }

    public function test_approver_can_validate_and_reject_a_proposed_reference(): void
    {
        [$owner, $base, $inspection] = $this->scope();

        $link = EngineeringQaNormativeLink::query()->create([
            'organization_id' => (string) $inspection->organization_id,
            'project_id' => (string) $inspection->project_id,
            'engineering_qa_inspection_id' => $inspection->getKey(),
            'source_label' => 'NF EN 13670',
            'status' => EngineeringQaNormativeLink::STATUS_SUGGESTED,
        ]);

        $this->actingAs($owner)
            ->from($base.'/qa-normative')
            ->post($base.'/qa-normative/links/'.$link->getKey().'/decision', ['status' => 'validated'])
            ->assertRedirect($base.'/qa-normative');

        $this->assertSame('validated', $link->fresh()->status);
        $this->assertNotNull($link->fresh()->validated_at);

        $this->post($base.'/qa-normative/links/'.$link->getKey().'/decision', ['status' => 'rejected'])
            ->assertRedirect();

        $this->assertSame('rejected', $link->fresh()->status);
        $this->assertNull($link->fresh()->validated_at);
    }

    public function test_link_written_with_the_v2_column_keeps_the_legacy_one_in_sync(): void
    {
        [, , $inspection] = $this->scope();

        EngineeringQaNormativeLink::query()->create([
            'organization_id' => (string) $inspection->organization_id,
            'project_id' => (string) $inspection->project_id,
            'inspection_id' => $inspection->getKey(),
            'status' => EngineeringQaNormativeLink::STATUS_SUGGESTED,
        ]);

        $this->assertSame(
            $inspection->getKey(),
            (int) DB::table('engineering_qa_normative_links')->value('engineering_qa_inspection_id')
        );
    }

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

        return [$owner, '/engineering/'.$organization->public_id.'/projects/'.$project->public_id, $inspection];
    }
}
