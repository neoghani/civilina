<?php

namespace Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringWorkPackageActivityTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_work_package_and_activity_through_api(): void
    {
        $c = $this->context('member');

        $wbs = $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/wbs-nodes',
                [
                    'code' => 'P-001-STR',
                    'name' => 'Structure',
                ]
            )
            ->assertCreated()
            ->json('data');

        $zone = $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/zones',
                [
                    'code' => 'Z-01',
                    'name' => 'Zone 01',
                ]
            )
            ->assertCreated()
            ->json('data');

        $workPackage = $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/work-packages',
                [
                    'wbs_node_id' => $wbs['id'],
                    'zone_id' => $zone['id'],
                    'code' => 'WP-STR-001',
                    'name' => 'Structure béton',
                    'status' => 'active',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.wbs_node_id',
                $wbs['id']
            )
            ->assertJsonPath(
                'data.status',
                'active'
            )
            ->json('data');

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/activities',
                [
                    'work_package_id' =>
                        $workPackage['id'],
                    'code' => 'ACT-STR-001',
                    'name' => 'Ferraillage poteaux',
                    'activity_type' => 'construction',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.work_package_id',
                $workPackage['id']
            )
            ->assertJsonPath(
                'data.zone_id',
                $zone['id']
            )
            ->assertJsonPath(
                'data.status',
                'planned'
            );

        $this->actingAs($c['user'])
            ->getJson(
                $c['base'].'/structure'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.work_packages'
            )
            ->assertJsonCount(
                1,
                'data.activities'
            );
    }

    public function test_work_package_cannot_reference_foreign_wbs(): void
    {
        $c = $this->context('member');

        $foreignProject =
            app(EngineeringProjectService::class)
                ->createProject(
                    $c['organization'],
                    'P-002',
                    'Foreign Project'
                );

        $foreignWbs =
            app(EngineeringProjectService::class)
                ->createWbsNode(
                    $foreignProject,
                    'P-002-STR',
                    'Foreign WBS'
                );

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/work-packages',
                [
                    'wbs_node_id' => $foreignWbs->id,
                    'code' => 'WP-001',
                    'name' => 'Invalid',
                ]
            )
            ->assertNotFound();
    }

    public function test_activity_cannot_reference_foreign_work_package(): void
    {
        $c = $this->context('member');

        $foreignProject =
            app(EngineeringProjectService::class)
                ->createProject(
                    $c['organization'],
                    'P-002',
                    'Foreign Project'
                );

        $foreignWbs =
            app(EngineeringProjectService::class)
                ->createWbsNode(
                    $foreignProject,
                    'P-002-STR',
                    'Foreign WBS'
                );

        $foreignWorkPackage =
            app(EngineeringProjectService::class)
                ->createWorkPackage(
                    $foreignProject,
                    $foreignWbs,
                    'WP-002',
                    'Foreign'
                );

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/activities',
                [
                    'work_package_id' =>
                        $foreignWorkPackage->id,
                    'code' => 'ACT-001',
                    'name' => 'Invalid',
                ]
            )
            ->assertNotFound();
    }

    public function test_technical_object_can_reference_activity_and_inherits_wbs_from_work_package(): void
    {
        $c = $this->context('member');

        $service =
            app(EngineeringProjectService::class);

        $wbs = $service->createWbsNode(
            $c['project'],
            'P-001-STR',
            'Structure'
        );

        $workPackage = $service->createWorkPackage(
            $c['project'],
            $wbs,
            'WP-STR-001',
            'Structure béton'
        );

        $activity = $service->createActivity(
            $c['project'],
            $workPackage,
            'ACT-STR-001',
            'Ferraillage poteaux'
        );

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/technical-objects',
                [
                    'code' => 'COL-C01',
                    'name' => 'Poteau C01',
                    'object_type' => 'column',
                    'discipline' => 'structure',
                    'activity_id' => $activity->id,
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.wbs_node_id',
                $wbs->id
            )
            ->assertJsonPath(
                'data.work_package_id',
                $workPackage->id
            )
            ->assertJsonPath(
                'data.activity_id',
                $activity->id
            );
    }

    public function test_viewer_sees_structure_but_cannot_create_work_package(): void
    {
        $c = $this->context('viewer');

        $this->actingAs($c['user'])
            ->getJson(
                $c['base'].'/structure'
            )
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'wbs_nodes',
                    'work_packages',
                    'activities',
                    'zones',
                    'ouvrages',
                    'materials',
                ],
            ]);

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/work-packages',
                [
                    'wbs_node_id' => 1,
                    'code' => 'WP-001',
                    'name' => 'Forbidden',
                ]
            )
            ->assertForbidden();
    }

    public function test_workspace_exposes_work_package_and_activity_controls_to_owner(): void
    {
        $c = $this->context('owner');

        $url =
            '/engineering/'.
            $c['organization']->public_id.
            '/projects/'.
            $c['project']->public_id.
            '/workspace';

        $this->actingAs($c['user'])
            ->get($url)
            ->assertOk()
            ->assertSee('Work Packages')
            ->assertSee('Activities')
            ->assertSee(
                'data-endpoint="/work-packages"',
                false
            )
            ->assertSee(
                'data-endpoint="/activities"',
                false
            )
            ->assertSee(
                'data-select="work-package"',
                false
            )
            ->assertSee(
                'data-select="activity"',
                false
            );
    }

    private function context(
        string $role
    ): array {
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
                    'Project'
                );

        if ($role === 'owner') {
            $user = $owner;
        } else {
            $user =
                User::factory()->create();

            OrganizationMembership::query()
                ->create([
                    'organization_id' =>
                        $organization->id,
                    'user_id' =>
                        $user->id,
                    'role' =>
                        $role,
                    'status' =>
                        'active',
                ]);
        }

        return [
            'user' => $user,
            'organization' => $organization,
            'project' => $project,
            'base' =>
                '/api/organizations/'.
                $organization->public_id.
                '/engineering/projects/'.
                $project->public_id,
        ];
    }
}
