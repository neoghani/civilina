<?php

namespace Tests\Feature;

use App\Enums\EngineeringCheckOutcome;
use App\Enums\EngineeringCheckStatus;
use App\Enums\EngineeringFindingSeverity;
use App\Enums\AnalysisRunStatus;
use App\Models\User;
use App\Services\Engineering\AnalysisModelService;
use App\Services\Engineering\AnalysisResultImportService;
use App\Services\Engineering\AnalysisRunService;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\EngineeringVerificationService;
use App\Services\Engineering\RobotStructuralAnalysisAdapter;
use App\Services\Engineering\TechnicalModelService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RobotResultsEngineeringVerificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_import_maps_solver_result_to_civilina_revision(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'force',
                'component' => 'N',
                'combination_code' => 'ULS-01',
                'value' => -850.5,
                'unit' => 'kN',
            ]],
            '2026.1',
            'RSA-RUN-001'
        );

        $result = $set->results()->first();

        $this->assertSame(
            $c['revision']->id,
            $result->technical_object_revision_id
        );

        $this->assertSame(
            AnalysisRunStatus::Succeeded,
            $c['run']->refresh()->status
        );
    }

    public function test_import_rejects_unknown_solver_object(): void
    {
        $c = $this->context();

        $this->expectException(DomainException::class);

        $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'UNKNOWN',
                'result_type' => 'force',
                'value' => 10,
            ]]
        );
    }

    public function test_result_set_keeps_deterministic_hash(): void
    {
        $c = $this->context();

        $rows = [[
            'external_key' => 'COL-001',
            'result_type' => 'displacement',
            'component' => 'UZ',
            'value' => 0.0042,
            'unit' => 'm',
        ]];

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            $rows
        );

        $this->assertSame(
            64,
            strlen($set->result_hash)
        );

        $this->assertSame(
            1,
            $set->results()->count()
        );
    }

    public function test_engineering_check_requires_same_analysis_project(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'force',
                'value' => -100,
                'unit' => 'kN',
            ]]
        );

        $foreignProject =
            $c['projects']->createProject(
                $c['organization'],
                'P-002',
                'Other project'
            );

        $foreignRevision =
            $this->approvedRevision(
                $foreignProject,
                $c['technical'],
                'COL-002'
            );

        $this->expectException(DomainException::class);

        $c['verification']->createCheck(
            $set,
            $foreignRevision,
            'CHK-001',
            'resistance',
            'Resistance check',
            EngineeringCheckOutcome::Pass,
            ['capacity_kn' => 1000],
            ['demand_kn' => 100]
        );
    }

    public function test_check_records_criterion_observed_trace_and_utilization(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'force',
                'component' => 'N',
                'value' => -850,
                'unit' => 'kN',
            ]]
        );

        $check = $c['verification']->createCheck(
            $set,
            $c['revision'],
            'CHK-N-001',
            'resistance',
            'Axial resistance',
            EngineeringCheckOutcome::Pass,
            ['capacity_kn' => 1200],
            ['demand_kn' => 850],
            0.708333,
            [
                'formula' => 'abs(Ned) / Nrd',
                'source_result_type' => 'force',
                'component' => 'N',
            ],
            null,
            'Section acceptable.'
        );

        $this->assertSame(
            EngineeringCheckStatus::Draft,
            $check->status
        );

        $this->assertSame(
            'abs(Ned) / Nrd',
            $check->calculation_trace['formula']
        );

        $this->assertEquals(
            0.708333,
            (float) $check->utilization_ratio
        );
    }

    public function test_failed_check_can_create_engineering_finding(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'displacement',
                'value' => 0.030,
                'unit' => 'm',
            ]]
        );

        $check = $c['verification']->createCheck(
            $set,
            $c['revision'],
            'CHK-DISP-001',
            'serviceability',
            'Displacement check',
            EngineeringCheckOutcome::Fail,
            ['max_m' => 0.020],
            ['observed_m' => 0.030],
            1.5
        );

        $finding = $c['verification']->addFinding(
            $check,
            EngineeringFindingSeverity::High,
            'FND-001',
            'Excessive displacement',
            'Calculated displacement exceeds the accepted criterion.',
            'Review stiffness and member sizing.'
        );

        $this->assertSame(
            EngineeringFindingSeverity::High,
            $finding->severity
        );

        $this->assertSame(
            'open',
            $finding->status
        );
    }

    public function test_check_requires_human_review_before_acceptance(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'force',
                'value' => -100,
            ]]
        );

        $check = $c['verification']->createCheck(
            $set,
            $c['revision'],
            'CHK-001',
            'resistance',
            'Resistance',
            EngineeringCheckOutcome::Pass,
            ['capacity' => 1000],
            ['demand' => 100]
        );

        $this->expectException(DomainException::class);

        $c['verification']->accept(
            $check,
            $c['owner']
        );
    }

    public function test_reviewed_check_can_be_human_accepted(): void
    {
        $c = $this->context();

        $set = $c['importer']->importSucceededRun(
            $c['run'],
            [[
                'external_key' => 'COL-001',
                'result_type' => 'force',
                'value' => -100,
            ]]
        );

        $check = $c['verification']->createCheck(
            $set,
            $c['revision'],
            'CHK-001',
            'resistance',
            'Resistance',
            EngineeringCheckOutcome::Pass,
            ['capacity' => 1000],
            ['demand' => 100]
        );

        $check =
            $c['verification']->markForReview(
                $check
            );

        $check =
            $c['verification']->accept(
                $check,
                $c['owner']
            );

        $this->assertSame(
            EngineeringCheckStatus::Accepted,
            $check->status
        );

        $this->assertSame(
            $c['owner']->id,
            $check->reviewed_by_user_id
        );
    }

    private function context(): array
    {
        $owner =
            User::factory()->create();

        $organization =
            app(OrganizationService::class)
                ->createForOwner(
                    $owner,
                    'Civilina'
                );

        $projects =
            app(EngineeringProjectService::class);

        $project =
            $projects->createProject(
                $organization,
                'P-001',
                'Project'
            );

        $dbs =
            app(DesignBasisService::class);

        $db =
            $dbs->createRevision(
                $project,
                'Design Basis'
            );

        $db =
            $dbs->markForReview($db);

        $db =
            $dbs->approve($db);

        $technical =
            app(TechnicalModelService::class);

        $revision =
            $this->approvedRevision(
                $project,
                $technical,
                'COL-001'
            );

        $models =
            app(AnalysisModelService::class);

        $model =
            $models->createModel(
                $project,
                $db,
                'RSA-001',
                'Robot model'
            );

        $models->addTechnicalRevision(
            $model,
            $revision,
            'COL-001'
        );

        $model =
            $models->markForReview($model);

        $model =
            $models->approve($model);

        $run =
            app(AnalysisRunService::class)
                ->prepareRun(
                    $model,
                    app(RobotStructuralAnalysisAdapter::class)
                );

        return [
            'owner' => $owner,
            'organization' => $organization,
            'projects' => $projects,
            'project' => $project,
            'technical' => $technical,
            'revision' => $revision,
            'run' => $run,
            'importer' => app(AnalysisResultImportService::class),
            'verification' => app(EngineeringVerificationService::class),
        ];
    }

    private function approvedRevision(
        $project,
        TechnicalModelService $technical,
        string $code
    ) {
        $material =
            $technical->createMaterial(
                $project,
                'MAT-'.$code,
                'Concrete',
                'concrete',
                'EN 206',
                ['fck_mpa' => 30]
            );

        $object =
            $technical->createObject(
                $project,
                $code,
                'Column '.$code,
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
                    'length_m' => 4.20,
                ],
                [],
                [],
                $material
            );

        $revision =
            $technical->markForReview(
                $revision
            );

        return $technical->approve(
            $revision
        );
    }
}
