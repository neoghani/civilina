<?php

namespace Tests\Feature;

use App\Models\OrganizationMembership;
use App\Models\User;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Organizations\OrganizationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringProjectStructureApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_creates_wbs_root_and_child(): void
    {
        $c = $this->context('member');

        $root =
            $this->actingAs($c['user'])
                ->postJson(
                    $c['base'].'/wbs-nodes',
                    [
                        'code' => 'P-001-STR',
                        'name' => 'Structure',
                    ]
                )
                ->assertCreated()
                ->json('data');

        $child =
            $this->actingAs($c['user'])
                ->postJson(
                    $c['base'].'/wbs-nodes',
                    [
                        'code' => 'P-001-STR-CONC',
                        'name' => 'Concrete',
                        'parent_id' => $root['id'],
                        'sort_order' => 10,
                    ]
                )
                ->assertCreated()
                ->json('data');

        $this->assertSame(
            $root['id'],
            $child['parent_id']
        );
    }

    public function test_wbs_parent_from_other_project_is_not_visible(): void
    {
        $c = $this->context('member');

        $other =
            app(EngineeringProjectService::class)
                ->createProject(
                    $c['organization'],
                    'P-002',
                    'Other'
                );

        $foreignNode =
            app(EngineeringProjectService::class)
                ->createWbsNode(
                    $other,
                    'P-002-STR',
                    'Foreign'
                );

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/wbs-nodes',
                [
                    'code' => 'P-001-STR',
                    'name' => 'Structure',
                    'parent_id' => $foreignNode->id,
                ]
            )
            ->assertNotFound();
    }

    public function test_member_creates_zone_and_ouvrage(): void
    {
        $c = $this->context('member');

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/zones',
                [
                    'code' => 'Z-01',
                    'name' => 'Process zone',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.project_id',
                $c['project']->id
            );

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/ouvrages',
                [
                    'code' => 'OUV-01',
                    'name' => 'Pump building',
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.project_id',
                $c['project']->id
            );
    }

    public function test_member_creates_material_with_structured_properties(): void
    {
        $c = $this->context('member');

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/materials',
                [
                    'code' => 'C30-37',
                    'name' => 'Concrete C30/37',
                    'material_type' => 'concrete',
                    'standard_reference' => 'EN 206',
                    'properties' => [
                        'fck_mpa' => 30,
                        'density_kg_m3' => 2500,
                    ],
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.properties.fck_mpa',
                30
            );
    }

    public function test_structure_endpoint_returns_all_project_dimensions(): void
    {
        $c = $this->context('viewer');

        $service =
            app(EngineeringProjectService::class);

        $service->createWbsNode(
            $c['project'],
            'P-001-CIV',
            'Civil'
        );

        $service->createZone(
            $c['project'],
            'Z-01',
            'Zone'
        );

        $service->createOuvrage(
            $c['project'],
            'OUV-01',
            'Ouvrage'
        );

        $this->actingAs($c['user'])
            ->getJson(
                $c['base'].'/structure'
            )
            ->assertOk()
            ->assertJsonCount(
                1,
                'data.wbs_nodes'
            )
            ->assertJsonCount(
                1,
                'data.zones'
            )
            ->assertJsonCount(
                1,
                'data.ouvrages'
            );
    }

    public function test_viewer_cannot_modify_project_structure(): void
    {
        $c = $this->context('viewer');

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/zones',
                [
                    'code' => 'Z-01',
                    'name' => 'Zone',
                ]
            )
            ->assertForbidden();
    }

    public function test_cross_tenant_project_structure_returns_not_found(): void
    {
        $c = $this->context('member');

        $otherOwner =
            User::factory()->create();

        $otherOrganization =
            app(OrganizationService::class)
                ->createForOwner(
                    $otherOwner,
                    'Other Organization'
                );

        $foreignProject =
            app(EngineeringProjectService::class)
                ->createProject(
                    $otherOrganization,
                    'P-999',
                    'Foreign'
                );

        $url =
            '/api/organizations/'.
            $c['organization']->public_id.
            '/engineering/projects/'.
            $foreignProject->public_id.
            '/structure';

        $this->actingAs($c['user'])
            ->getJson($url)
            ->assertNotFound();
    }

    public function test_technical_object_can_reference_api_created_wbs_zone_and_ouvrage(): void
    {
        $c = $this->context('member');

        $wbs =
            $this->actingAs($c['user'])
                ->postJson(
                    $c['base'].'/wbs-nodes',
                    [
                        'code' => 'P-001-STR',
                        'name' => 'Structure',
                    ]
                )
                ->assertCreated()
                ->json('data');

        $zone =
            $this->actingAs($c['user'])
                ->postJson(
                    $c['base'].'/zones',
                    [
                        'code' => 'Z-01',
                        'name' => 'Zone',
                    ]
                )
                ->assertCreated()
                ->json('data');

        $ouvrage =
            $this->actingAs($c['user'])
                ->postJson(
                    $c['base'].'/ouvrages',
                    [
                        'code' => 'OUV-01',
                        'name' => 'Pump building',
                    ]
                )
                ->assertCreated()
                ->json('data');

        $this->actingAs($c['user'])
            ->postJson(
                $c['base'].'/technical-objects',
                [
                    'code' => 'STR-COL-001',
                    'name' => 'Column C01',
                    'object_type' => 'column',
                    'discipline' => 'structure',
                    'wbs_node_id' => $wbs['id'],
                    'zone_id' => $zone['id'],
                    'ouvrage_id' => $ouvrage['id'],
                ]
            )
            ->assertCreated()
            ->assertJsonPath(
                'data.wbs_node_id',
                $wbs['id']
            )
            ->assertJsonPath(
                'data.zone_id',
                $zone['id']
            )
            ->assertJsonPath(
                'data.ouvrage_id',
                $ouvrage['id']
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
