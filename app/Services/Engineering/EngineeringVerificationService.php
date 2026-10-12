<?php

namespace App\Services\Engineering;

use App\Enums\EngineeringCheckOutcome;
use App\Enums\EngineeringCheckStatus;
use App\Enums\EngineeringFindingSeverity;
use App\Enums\RequirementValidationStatus;
use App\Models\AnalysisResultSet;
use App\Models\EngineeringCheck;
use App\Models\EngineeringFinding;
use App\Models\NormativeRequirement;
use App\Models\TechnicalObjectRevision;
use App\Models\User;
use DomainException;

class EngineeringVerificationService
{
    public function createCheck(
        AnalysisResultSet $resultSet,
        TechnicalObjectRevision $revision,
        string $checkCode,
        string $checkType,
        string $title,
        EngineeringCheckOutcome $outcome,
        array $criterion,
        array $observed,
        ?float $utilizationRatio = null,
        array $calculationTrace = [],
        ?NormativeRequirement $requirement = null,
        ?string $recommendation = null,
        ?User $createdBy = null
    ): EngineeringCheck {
        $analysisModelId =
            $resultSet
                ->analysisRun()
                ->value('analysis_model_id');

        $belongsToAnalysisModel =
            $revision
                ->technicalObject()
                ->value('project_id') ===
            $resultSet
                ->analysisRun
                ->analysisModel
                ->project_id;

        if (! $belongsToAnalysisModel) {
            throw new DomainException(
                'Engineering check target must belong to the analysis project.'
            );
        }

        if ($requirement) {
            if (
                $requirement->validation_status !==
                RequirementValidationStatus::Validated
            ) {
                throw new DomainException(
                    'Engineering check can only cite a validated normative requirement.'
                );
            }
        }

        if (
            $utilizationRatio !== null &&
            $utilizationRatio < 0
        ) {
            throw new DomainException(
                'Utilization ratio cannot be negative.'
            );
        }

        return EngineeringCheck::query()->create([
            'analysis_result_set_id' => $resultSet->getKey(),
            'technical_object_revision_id' => $revision->getKey(),
            'normative_requirement_id' => $requirement?->getKey(),
            'check_code' => $this->requiredText($checkCode),
            'check_type' => $this->requiredText($checkType),
            'title' => $this->requiredText($title),
            'status' => EngineeringCheckStatus::Draft,
            'outcome' => $outcome,
            'criterion' => $criterion,
            'observed' => $observed,
            'utilization_ratio' => $utilizationRatio,
            'calculation_trace' => $calculationTrace,
            'recommendation' => $this->optionalText($recommendation),
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    public function addFinding(
        EngineeringCheck $check,
        EngineeringFindingSeverity $severity,
        string $code,
        string $title,
        string $description,
        ?string $recommendedAction = null
    ): EngineeringFinding {
        return EngineeringFinding::query()->create([
            'engineering_check_id' => $check->getKey(),
            'severity' => $severity,
            'code' => $this->requiredText($code),
            'title' => $this->requiredText($title),
            'description' => $this->requiredText($description),
            'recommended_action' =>
                $this->optionalText($recommendedAction),
            'status' => 'open',
        ]);
    }

    public function markForReview(
        EngineeringCheck $check
    ): EngineeringCheck {
        if (
            $check->status !==
            EngineeringCheckStatus::Draft
        ) {
            throw new DomainException(
                'Only a draft engineering check can move to review.'
            );
        }

        $check->status =
            EngineeringCheckStatus::Review;

        $check->save();

        return $check->refresh();
    }

    public function accept(
        EngineeringCheck $check,
        User $reviewer
    ): EngineeringCheck {
        if (
            $check->status !==
            EngineeringCheckStatus::Review
        ) {
            throw new DomainException(
                'Only an engineering check in review can be accepted.'
            );
        }

        $check->status =
            EngineeringCheckStatus::Accepted;

        $check->reviewed_by_user_id =
            $reviewer->getKey();

        $check->reviewed_at = now();
        $check->save();

        return $check->refresh();
    }

    private function requiredText(
        string $value
    ): string {
        $value = trim($value);

        if ($value === '') {
            throw new DomainException(
                'A non-empty value is required.'
            );
        }

        return $value;
    }

    private function optionalText(
        ?string $value
    ): ?string {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
