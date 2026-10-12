<?php

namespace Tests\Feature;

use App\Enums\EngineeringCheckOutcome;
use App\Enums\EngineeringFindingSeverity;
use App\Enums\EngineeringVerificationOperator;
use App\Enums\EngineeringVerificationRuleStatus;
use App\Enums\RequirementApplicability;
use App\Enums\StandardEditionStatus;
use App\Models\User;
use App\Services\Engineering\AnalysisModelService;
use App\Services\Engineering\AnalysisResultImportService;
use App\Services\Engineering\AnalysisRunService;
use App\Services\Engineering\DesignBasisService;
use App\Services\Engineering\DeterministicEngineeringVerificationService;
use App\Services\Engineering\EngineeringKnowledgeService;
use App\Services\Engineering\EngineeringProjectService;
use App\Services\Engineering\EngineeringVerificationRuleService;
use App\Services\Engineering\RobotStructuralAnalysisAdapter;
use App\Services\Engineering\TechnicalModelService;
use App\Services\Organizations\OrganizationService;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NormativeVerificationRulesTest extends TestCase
{
    use RefreshDatabase;

    public function test_rule_requires_approved_project_standard_profile(): void
    {
        $c = $this->context(false);

        $this->expectException(DomainException::class);

        $c['rules']->createRule(
            $c['project'],
            $c['profile'],
            $c['requirement'],
            'DISP-001',
            'Maximum displacement',
            'displacement',
            EngineeringVerificationOperator::MaxAbsLessOrEqual,
            0.020,
            'm',
            'UZ'
        );
    }

    public function test_rule_requires_requirement_applicable_to_project(): void
    {
        $c = $this->context(true, false);

        $this->expectException(DomainException::class);

        $c['rules']->createRule(
            $c['project'],
            $c['profile'],
            $c['requirement'],
            'DISP-001',
            'Maximum displacement',
            'displacement',
            EngineeringVerificationOperator::MaxAbsLessOrEqual,
            0.020,
            'm',
            'UZ'
        );
    }

    public function test_rule_requires_review_before_approval(): void
    {
        $c = $this->context();

        $rule = $this->rule($c);

        $this->expectException(DomainException::class);

        $c['rules']->approve(
            $rule,
            $c['owner']
        );
    }

    public function test_approved_rule_passes_deterministically(): void
    {
        $c = $this->context();

        $set = $this->resultSet(
            $c,
            [0.004, -0.012, 0.009]
        );

        $rule = $this->approvedRule($c);

        $check =
            app(DeterministicEngineeringVerificationService::class)
                ->evaluate(
                    $set,
                    $rule,
                    $c['revision']
                );

        $this->assertSame(
            EngineeringCheckOutcome::Pass,
            $check->outcome
        );

        $this->assertEquals(
            0.012,
            (float) $check->observed['value']
        );

        $this->assertEquals(
            0.6,
            (float) $check->utilization_ratio
        );

        $this->assertSame(
            'civilina_deterministic_verification_v1',
            $check->calculation_trace['engine']
        );
    }

    public function test_failed_rule_creates_traceable_finding(): void
    {
        $c = $this->context();

        $set = $this->resultSet(
            $c,
            [0.011, -0.032, 0.008]
        );

        $rule = $this->approvedRule($c);

        $check =
            app(DeterministicEngineeringVerificationService::class)
                ->evaluate(
                    $set,
                    $rule,
                    $c['revision']
                );

        $this->assertSame(
            EngineeringCheckOutcome::Fail,
            $check->outcome
        );

        $finding = $check->findings()->first();

        $this->assertNotNull($finding);

        $this->assertSame(
            EngineeringFindingSeverity::High,
            $finding->severity
        );

        $this->assertSame(
            $c['requirement']->id,
            $check->normative_requirement_id
        );
    }

    public function test_unapproved_rule_cannot_execute(): void
    {
        $c = $this->context();

        $set = $this->resultSet(
            $c,
            [0.010]
        );

        $rule = $this->rule($c);

        $this->expectException(DomainException::class);

        app(DeterministicEngineeringVerificationService::class)
            ->evaluate(
                $set,
                $rule,
                $c['revision']
            );
    }

    private function context(
        bool $approveProfile = true,
        bool $makeApplicable = true
    ): array {
        $owner = User::factory()->create();

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

        $knowledge =
            app(EngineeringKnowledgeService::class);

        $document =
            $knowledge->registerDocument(
                $organization,
                'STD-001',
                'Project standard'
            );

        $edition =
            $knowledge->registerEdition(
                $document,
                '2026',
                '2026-01-01',
                StandardEditionStatus::Active
            );

        $section =
            $knowledge->createSection(
                $edition,
                '7.3.1',
                'Serviceability',
                null,
                10,
                10
            );

        $requirement =
            $knowledge->captureRequirementCandidate(
                $section,
                'REQ-DISP-001',
                'serviceability',
                'Maximum vertical displacement criterion'
            );

        $requirement =
            $knowledge->validateRequirement(
                $requirement,
                $owner
            );

        $profile =
            $knowledge->createProjectProfile(
                $project,
                'Project standards',
                $owner
            );

        $knowledge->addStandardToProfile(
            $profile,
            $edition,
            $owner,
            'Structural verification',
            10,
            true
        );

        if ($makeApplicable) {
            $knowledge->decideRequirementApplicability(
                $profile,
                $requirement,
                RequirementApplicability::Applicable,
                $owner,
                'Applicable to the structural model.'
            );
        }

        if ($approveProfile) {
            $profile =
                $knowledge->markProfileForReview($profile);

            $profile =
                $knowledge->approveProfile(
                    $profile,
                    $owner
                );
        }

        $dbs = app(DesignBasisService::class);

        $db = $dbs->createRevision(
            $project,
            'Design Basis'
        );

        $db = $dbs->markForReview($db);
        $db = $dbs->approve($db);

        $technical =
            app(TechnicalModelService::class);

        $material =
            $technical->createMaterial(
                $project,
                'C30-37',
                'Concrete',
                'concrete'
            );

        $object =
            $technical->createObject(
                $project,
                'COL-001',
                'Column',
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
            $technical->markForReview($revision);

        $revision =
            $technical->approve($revision);

        $models = app(AnalysisModelService::class);

        $model =
            $models->createModel(
                $project,
                $db,
                'RSA-001',
                'Robot model',
                $approveProfile ? $profile : null
            );

        $models->addTechnicalRevision(
            $model,
            $revision,
            'COL-001'
        );

        $model = $models->markForReview($model);
        $model = $models->approve($model);

        $run =
            app(AnalysisRunService::class)
                ->prepareRun(
                    $model,
                    app(RobotStructuralAnalysisAdapter::class)
                );

        return [
            'owner' => $owner,
            'organization' => $organization,
            'project' => $project,
            'profile' => $profile,
            'requirement' => $requirement,
            'revision' => $revision,
            'run' => $run,
            'rules' => app(EngineeringVerificationRuleService::class),
        ];
    }

    private function resultSet(
        array $c,
        array $values
    ) {
        $rows = [];

        foreach ($values as $value) {
            $rows[] = [
                'external_key' => 'COL-001',
                'result_type' => 'displacement',
                'component' => 'UZ',
                'combination_code' => 'SLS-01',
                'value' => $value,
                'unit' => 'm',
            ];
        }

        return app(AnalysisResultImportService::class)
            ->importSucceededRun(
                $c['run'],
                $rows
            );
    }

    private function rule(array $c)
    {
        return $c['rules']->createRule(
            $c['project'],
            $c['profile'],
            $c['requirement'],
            'DISP-001',
            'Maximum vertical displacement',
            'displacement',
            EngineeringVerificationOperator::MaxAbsLessOrEqual,
            0.020,
            'm',
            'UZ',
            EngineeringFindingSeverity::High,
            [
                'source' => 'approved_project_normative_profile',
            ],
            $c['owner']
        );
    }

    private function approvedRule(array $c)
    {
        $rule = $this->rule($c);

        $rule = $c['rules']->markForReview(
            $rule
        );

        return $c['rules']->approve(
            $rule,
            $c['owner']
        );
    }
}
