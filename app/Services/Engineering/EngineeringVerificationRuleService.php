<?php

namespace App\Services\Engineering;

use App\Enums\EngineeringFindingSeverity;
use App\Enums\EngineeringVerificationOperator;
use App\Enums\EngineeringVerificationRuleStatus;
use App\Enums\ProjectStandardProfileStatus;
use App\Enums\RequirementApplicability;
use App\Enums\RequirementValidationStatus;
use App\Models\EngineeringVerificationRule;
use App\Models\NormativeRequirement;
use App\Models\Project;
use App\Models\ProjectRequirementDecision;
use App\Models\ProjectStandardProfile;
use App\Models\User;
use DomainException;
use Illuminate\Support\Str;

class EngineeringVerificationRuleService
{
    public function createRule(
        Project $project,
        ProjectStandardProfile $profile,
        NormativeRequirement $requirement,
        string $code,
        string $title,
        string $resultType,
        EngineeringVerificationOperator $operator,
        float $limitValue,
        string $limitUnit,
        ?string $component = null,
        EngineeringFindingSeverity $failureSeverity = EngineeringFindingSeverity::High,
        array $rulePayload = [],
        ?User $createdBy = null
    ): EngineeringVerificationRule {
        if ((int) $profile->project_id !== (int) $project->getKey()) {
            throw new DomainException(
                'Verification rule profile must belong to the project.'
            );
        }

        if ($profile->status !== ProjectStandardProfileStatus::Approved) {
            throw new DomainException(
                'Verification rules require an approved project standard profile.'
            );
        }

        if (
            $requirement->validation_status !==
            RequirementValidationStatus::Validated
        ) {
            throw new DomainException(
                'Verification rules require a human-validated normative requirement.'
            );
        }

        $applicable =
            ProjectRequirementDecision::query()
                ->where(
                    'project_standard_profile_id',
                    $profile->getKey()
                )
                ->where(
                    'normative_requirement_id',
                    $requirement->getKey()
                )
                ->where(
                    'applicability',
                    RequirementApplicability::Applicable->value
                )
                ->exists();

        if (! $applicable) {
            throw new DomainException(
                'Normative requirement must be explicitly applicable to the project.'
            );
        }

        return EngineeringVerificationRule::query()->create([
            'project_id' => $project->getKey(),
            'project_standard_profile_id' => $profile->getKey(),
            'normative_requirement_id' => $requirement->getKey(),
            'code' => $this->code($code),
            'title' => $this->text($title),
            'result_type' => $this->text($resultType),
            'component' => $this->optionalText($component),
            'operator' => $operator,
            'limit_value' => $limitValue,
            'limit_unit' => $this->text($limitUnit),
            'failure_severity' => $failureSeverity,
            'status' => EngineeringVerificationRuleStatus::Draft,
            'rule_payload' => $rulePayload,
            'created_by_user_id' => $createdBy?->getKey(),
        ]);
    }

    public function markForReview(
        EngineeringVerificationRule $rule
    ): EngineeringVerificationRule {
        if ($rule->status !== EngineeringVerificationRuleStatus::Draft) {
            throw new DomainException(
                'Only a draft verification rule can move to review.'
            );
        }

        $rule->status = EngineeringVerificationRuleStatus::Review;
        $rule->save();

        return $rule->refresh();
    }

    public function approve(
        EngineeringVerificationRule $rule,
        User $reviewer
    ): EngineeringVerificationRule {
        if ($rule->status !== EngineeringVerificationRuleStatus::Review) {
            throw new DomainException(
                'Only a verification rule in review can be approved.'
            );
        }

        $rule->status = EngineeringVerificationRuleStatus::Approved;
        $rule->reviewed_by_user_id = $reviewer->getKey();
        $rule->reviewed_at = now();
        $rule->save();

        return $rule->refresh();
    }

    private function code(string $value): string
    {
        $value = Str::upper(trim($value));

        if ($value === '') {
            throw new DomainException('A non-empty code is required.');
        }

        return $value;
    }

    private function text(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            throw new DomainException('A non-empty value is required.');
        }

        return $value;
    }

    private function optionalText(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
