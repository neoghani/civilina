<?php

namespace Tests\Feature;

use App\Enums\DesignBasisCategory;
use App\Enums\EngineeringRevisionStatus;
use App\Enums\ProjectStatus;
use App\Models\User;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\TechnicalModelService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EngineeringCoreV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_project_is_tenant_owned(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station de pompage'
            );

        $this->assertSame(
            $organization->id,
            $project->organization_id
        );

        $this->assertSame(
            ProjectStatus::Draft,
            $project->status
        );

        $this->assertNotEmpty(
            $project->public_id
        );
    }

    public function test_same_project_code_isolated_between_tenants(): void
    {
        $ownerA = User::factory()->create();
        $ownerB = User::factory()->create();

        $organizations =
            app(OrganizationService::class);

        $organizationA =
            $organizations->createForOwner(
                $ownerA,
                'Org A'
            );

        $organizationB =
            $organizations->createForOwner(
                $ownerB,
                'Org B'
            );

        $projects =
            app(EngineeringProjectService::class);

        $projects->createProject(
            $organizationA,
            'SP-01',
            'A'
        );

        $projectB =
            $projects->createProject(
                $organizationB,
                'SP-01',
                'B'
            );

        $this->assertSame(
            $organizationB->id,
            $projectB->organization_id
        );
    }

    public function test_wbs_zone_and_ouvrage_are_separate_dimensions(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $wbs =
            $projects->createWbsNode(
                $project,
                'SP-01-CIV',
                'Génie civil'
            );

        $zone =
            $projects->createZone(
                $project,
                'Z-01',
                'Zone process'
            );

        $ouvrage =
            $projects->createOuvrage(
                $project,
                'OUV-01',
                'Bâtiment pompes'
            );

        $this->assertSame(
            $project->id,
            $wbs->project_id
        );

        $this->assertSame(
            $project->id,
            $zone->project_id
        );

        $this->assertSame(
            $project->id,
            $ouvrage->project_id
        );

        $this->assertNull(
            $wbs->parent_id
        );
    }

    public function test_design_basis_keeps_source_and_assumption_explicit(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $designBasis =
            app(DesignBasisService::class);

        $revision =
            $designBasis->createRevision(
                $project,
                'Design Basis — SP-01'
            );

        $item =
            $designBasis->addItem(
                $revision,
                DesignBasisCategory::Seismic,
                'ag',
                'Accélération de calcul',
                0.25,
                'g',
                'client_document',
                'DB-SP01-001',
                false
            );

        $assumption =
            $designBasis->addItem(
                $revision,
                DesignBasisCategory::Assumption,
                'temporary_soil_class',
                'Classe de sol provisoire',
                'À confirmer par étude géotechnique',
                null,
                'engineer',
                null,
                true
            );

        $this->assertSame(
            'client_document',
            $item->source_type
        );

        $this->assertFalse(
            $item->is_assumption
        );

        $this->assertTrue(
            $assumption->is_assumption
        );
    }

    public function test_approved_design_basis_cannot_be_edited(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $designBasis =
            app(DesignBasisService::class);

        $revision =
            $designBasis->createRevision(
                $project,
                'Design Basis'
            );

        $designBasis->markForReview(
            $revision
        );

        $approved =
            $designBasis->approve(
                $revision->refresh()
            );

        $this->assertSame(
            EngineeringRevisionStatus::Approved,
            $approved->status
        );

        $this->expectException(
            DomainException::class
        );

        $designBasis->addItem(
            $approved,
            DesignBasisCategory::Climate,
            'temperature',
            'Température',
            45,
            '°C'
        );
    }

    public function test_technical_object_links_context_without_becoming_wbs(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $wbs =
            $projects->createWbsNode(
                $project,
                'SP-01-STR',
                'Structure'
            );

        $zone =
            $projects->createZone(
                $project,
                'Z-01',
                'Zone process'
            );

        $ouvrage =
            $projects->createOuvrage(
                $project,
                'OUV-01',
                'Bâtiment pompes'
            );

        $technical =
            app(TechnicalModelService::class);

        $column =
            $technical->createObject(
                $project,
                'STR-COL-001',
                'Poteau C01',
                'column',
                'structure',
                null,
                $wbs,
                $zone,
                $ouvrage
            );

        $this->assertSame(
            $wbs->id,
            $column->wbs_node_id
        );

        $this->assertSame(
            $zone->id,
            $column->zone_id
        );

        $this->assertSame(
            $ouvrage->id,
            $column->ouvrage_id
        );

        $this->assertSame(
            'STR-COL-001',
            $column->code
        );
    }

    public function test_technical_object_rejects_cross_project_context(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $projectA =
            $projects->createProject(
                $organization,
                'P-A',
                'Project A'
            );

        $projectB =
            $projects->createProject(
                $organization,
                'P-B',
                'Project B'
            );

        $foreignZone =
            $projects->createZone(
                $projectB,
                'Z-B',
                'Foreign zone'
            );

        $technical =
            app(TechnicalModelService::class);

        $this->expectException(
            DomainException::class
        );

        $technical->createObject(
            $projectA,
            'OBJ-A',
            'Object A',
            'column',
            'structure',
            null,
            null,
            $foreignZone
        );
    }

    public function test_technical_revision_versions_geometry_material_and_assumptions(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $technical =
            app(TechnicalModelService::class);

        $material =
            $technical->createMaterial(
                $project,
                'C30-37',
                'Béton C30/37',
                'concrete',
                'EN 206',
                [
                    'fck_mpa' => 30,
                ]
            );

        $object =
            $technical->createObject(
                $project,
                'STR-COL-001',
                'Poteau C01',
                'column',
                'structure'
            );

        $revision1 =
            $technical->createRevision(
                $object,
                [
                    'shape' => 'rectangle',
                    'width_m' => 0.40,
                    'height_m' => 0.40,
                    'length_m' => 4.20,
                ],
                [
                    'support_level' => 'L00',
                ],
                [
                    'effective_length_factor' => 1.0,
                ],
                $material,
                null,
                [
                    'origin' => 'engineer_input',
                ],
                'Initial technical definition'
            );

        $revision2 =
            $technical->createRevision(
                $object,
                [
                    'shape' => 'rectangle',
                    'width_m' => 0.45,
                    'height_m' => 0.45,
                    'length_m' => 4.20,
                ],
                [],
                [],
                $material,
                null,
                [
                    'origin' => 'engineer_input',
                ],
                'Section increased'
            );

        $this->assertSame(
            1,
            $revision1->revision_no
        );

        $this->assertSame(
            2,
            $revision2->revision_no
        );

        $this->assertSame(
            0.40,
            $revision1->geometry['width_m']
        );

        $this->assertSame(
            0.45,
            $revision2->geometry['width_m']
        );

        $this->assertSame(
            $material->id,
            $revision2->material_id
        );
    }

    public function test_technical_revision_rejects_material_from_another_project(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $projectA =
            $projects->createProject(
                $organization,
                'P-A',
                'Project A'
            );

        $projectB =
            $projects->createProject(
                $organization,
                'P-B',
                'Project B'
            );

        $technical =
            app(TechnicalModelService::class);

        $object =
            $technical->createObject(
                $projectA,
                'OBJ-A',
                'Object A',
                'column',
                'structure'
            );

        $foreignMaterial =
            $technical->createMaterial(
                $projectB,
                'C40-50',
                'Béton C40/50',
                'concrete'
            );

        $this->expectException(
            DomainException::class
        );

        $technical->createRevision(
            $object,
            [
                'shape' => 'rectangle',
                'width_m' => 0.40,
                'height_m' => 0.40,
            ],
            [],
            [],
            $foreignMaterial
        );
    }

    public function test_technical_revision_requires_human_review_before_approval(): void
    {
        [$organization, $projects] =
            $this->projectContext();

        $project =
            $projects->createProject(
                $organization,
                'SP-01',
                'Station'
            );

        $technical =
            app(TechnicalModelService::class);

        $object =
            $technical->createObject(
                $project,
                'STR-COL-001',
                'Poteau C01',
                'column',
                'structure'
            );

        $revision =
            $technical->createRevision(
                $object,
                [
                    'shape' => 'rectangle',
                    'width_m' => 0.40,
                    'height_m' => 0.40,
                ]
            );

        $this->expectException(
            DomainException::class
        );

        $technical->approve(
            $revision
        );
    }

    private function projectContext(): array
    {
        $owner =
            User::factory()->create();

        $organization =
            app(OrganizationService::class)
                ->createForOwner(
                    $owner,
                    'Civilina Engineering'
                );

        return [
            $organization,
            app(EngineeringProjectService::class),
        ];
    }
}
