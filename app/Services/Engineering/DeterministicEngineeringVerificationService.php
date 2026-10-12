<?php

namespace App\Services\Engineering;

use App\Enums\EngineeringCheckOutcome;
use App\Enums\EngineeringFindingSeverity;
use App\Enums\EngineeringVerificationOperator;
use App\Enums\EngineeringVerificationRuleStatus;
use App\Models\AnalysisResult;
use App\Models\AnalysisResultSet;
use App\Models\EngineeringCheck;
use App\Models\EngineeringVerificationRule;
use App\Models\TechnicalObjectRevision;
use DomainException;

class DeterministicEngineeringVerificationService
{
    public function evaluate(
        AnalysisResultSet $resultSet,
        EngineeringVerificationRule $rule,
        TechnicalObjectRevision $revision
    ): EngineeringCheck {
        if ($rule->status !== EngineeringVerificationRuleStatus::Approved) {
            throw new DomainException(
                'Only an approved verification rule can be executed.'
            );
        }

        $analysisProjectId =
            (int) $resultSet
                ->analysisRun
                ->analysisModel
                ->project_id;

        if (
            (int) $rule->project_id !== $analysisProjectId ||
            (int) $revision->technicalObject()->value('project_id') !==
                $analysisProjectId
        ) {
            throw new DomainException(
                'Verification rule, result set and technical revision must belong to the same project.'
            );
        }

        $query =
            AnalysisResult::query()
                ->where(
                    'analysis_result_set_id',
                    $resultSet->getKey()
                )
                ->where(
                    'technical_object_revision_id',
                    $revision->getKey()
                )
                ->where(
                    'result_type',
                    $rule->result_type
                );

        if ($rule->component !== null) {
            $query->where('component', $rule->component);
        }

        $results = $query->orderBy('id')->get();

        if ($results->isEmpty()) {
            throw new DomainException(
                'No solver result matches the approved verification rule.'
            );
        }

        [$governing, $observed, $utilization, $passed] =
            $this->evaluateRows(
                $results->all(),
                $rule
            );

        $verification =
            app(EngineeringVerificationService::class);

        $check =
            $verification->createCheck(
                $resultSet,
                $revision,
                'AUTO-'.$rule->code,
                'normative_rule',
                $rule->title,
                $passed
                    ? EngineeringCheckOutcome::Pass
                    : EngineeringCheckOutcome::Fail,
                [
                    'operator' => $rule->operator->value,
                    'limit_value' => (float) $rule->limit_value,
                    'unit' => $rule->limit_unit,
                    'normative_requirement_id' =>
                        $rule->normative_requirement_id,
                ],
                [
                    'governing_result_id' => $governing->getKey(),
                    'value' => $observed,
                    'unit' => $governing->unit,
                    'load_case_code' => $governing->load_case_code,
                    'combination_code' => $governing->combination_code,
                ],
                $utilization,
                [
                    'engine' => 'civilina_deterministic_verification_v1',
                    'rule_id' => $rule->getKey(),
                    'rule_code' => $rule->code,
                    'result_ids' => $results->pluck('id')->all(),
                    'operator' => $rule->operator->value,
                ],
                $rule->normativeRequirement,
                $passed
                    ? 'Criterion satisfied.'
                    : 'Criterion exceeded; engineering review required.'
            );

        if (! $passed) {
            $verification->addFinding(
                $check,
                $rule->failure_severity,
                'AUTO-'.$rule->code,
                $rule->title,
                'Deterministic verification failed against the approved project rule.',
                'Review the technical definition, loads, assumptions and governing result.'
            );
        }

        return $check->refresh();
    }

    private function evaluateRows(
        array $rows,
        EngineeringVerificationRule $rule
    ): array {
        $limit = (float) $rule->limit_value;

        if ($rule->operator === EngineeringVerificationOperator::MaxLessOrEqual) {
            $governing = collect($rows)->sortByDesc(
                fn ($row) => (float) $row->value
            )->first();

            $observed = (float) $governing->value;
            $passed = $observed <= $limit;
            $utilization = $limit == 0.0 ? null : $observed / $limit;

            return [$governing, $observed, $utilization, $passed];
        }

        if ($rule->operator === EngineeringVerificationOperator::MaxAbsLessOrEqual) {
            $governing = collect($rows)->sortByDesc(
                fn ($row) => abs((float) $row->value)
            )->first();

            $observed = abs((float) $governing->value);
            $passed = $observed <= $limit;
            $utilization = $limit == 0.0 ? null : $observed / $limit;

            return [$governing, $observed, $utilization, $passed];
        }

        $governing = collect($rows)->sortBy(
            fn ($row) => (float) $row->value
        )->first();

        $observed = (float) $governing->value;
        $passed = $observed >= $limit;
        $utilization = $observed == 0.0 ? null : $limit / $observed;

        return [$governing, $observed, $utilization, $passed];
    }
}
