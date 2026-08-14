<?php

namespace App\Domain\CivilinaAI\Questions;

use App\Domain\CivilinaAI\Registries\RegistryData;

final class QuestionSelector
{
    public function select(array $dimensions, array $knownAnswers = []): ?array
    {
        return $this->rank($dimensions, $knownAnswers)[0] ?? null;
    }

    public function rank(array $dimensions, array $knownAnswers = []): array
    {
        $issues = $this->openIssues($dimensions);
        $ranked = [];
        foreach (RegistryData::get('questions') as $question) {
            $decisionValue = $this->score($question, $issues, $knownAnswers);
            if ($decisionValue === null) {
                continue;
            }
            $question['decisionValue'] = $decisionValue;
            $ranked[] = $question;
        }
        usort($ranked, fn (array $left, array $right): int => $right['decisionValue'] <=> $left['decisionValue'] ?: strcmp($left['id'], $right['id']));

        return array_slice($ranked, 0, $this->questionBudget($dimensions));
    }

    public function openIssues(array $dimensions): array
    {
        $issues = array_map(fn (array $item): string => $item['id'], $dimensions['uncertainties']);
        if ($dimensions['primaryDomain'] === null) {
            $issues[] = 'DOMAIN_UNKNOWN';
        }
        if ($dimensions['symptoms'] === [] && in_array($dimensions['interactionMode'], ['diagnose', 'troubleshoot'], true)) {
            $issues[] = 'SYMPTOM_UNKNOWN';
        }
        if (! in_array('decision', $dimensions['controlDimensions'], true)) {
            $issues[] = 'DECISION_UNKNOWN';
        }
        if (in_array($dimensions['interactionMode'], ['review', 'critique', 'audit'], true) && ! $dimensions['hasDocumentReference']) {
            $issues[] = 'DOCUMENT_REQUIRED';
        }
        if ($dimensions['interactionMode'] === 'calculate' && ! $dimensions['hasNumbers']) {
            $issues[] = 'CALCULATION_DATA_REQUIRED';
        }
        if (in_array($dimensions['interactionMode'], ['create', 'design'], true)) {
            array_push($issues, 'CREATION_AUDIENCE_REQUIRED', 'CREATION_FORMAT_REQUIRED');
        }
        if (in_array('wbs', $dimensions['concepts'], true) && array_intersect($dimensions['scope']['entities'], ['zone', 'ouvrage']) === []) {
            $issues[] = 'WBS_ALIGNMENT_REQUIRED';
        }
        if (in_array('progressControl', $dimensions['concepts'], true)
            && in_array('progress', $dimensions['controlDimensions'], true)
            && ! array_filter($dimensions['facts'], fn (string $fact): bool => (bool) preg_match('/planned|executed|accepted|valued|forecast/', $fact))) {
            $issues[] = 'PROGRESS_BASIS_REQUIRED';
        }
        if (in_array('evm', $dimensions['concepts'], true) && ! $dimensions['hasNumbers']) {
            $issues[] = 'EVM_DATA_REQUIRED';
        }
        if ($dimensions['contractInterpretation']) {
            $issues[] = 'CONTRACT_BASIS_REQUIRED';
        }
        if ($dimensions['safetyCritical']) {
            $issues[] = 'SAFETY_CONTROL_REQUIRED';
        }
        if ($dimensions['peopleGuardrail']) {
            $issues[] = 'PEOPLE_CRITERIA_REQUIRED';
        }
        if (in_array('dataGovernance', $dimensions['concepts'], true)) {
            $issues[] = 'DATA_LINEAGE_REQUIRED';
        }

        return array_values(array_unique($issues));
    }

    private function score(array $question, array $issues, array $knownAnswers): ?int
    {
        $overlap = count(array_intersect($question['resolves'], $issues));
        if ($overlap === 0) {
            return null;
        }

        return $overlap * 8
            + $question['uncertaintyReduction'] * 3
            + $question['causalDiscrimination'] * 2
            + $question['decisionImpact'] * 3
            + $question['riskImportance'] * 2
            + $question['artifactReadiness']
            - $question['userBurden'] * 2
            - (isset($knownAnswers[$question['id']]) ? 20 : 0);
    }

    private function questionBudget(array $dimensions): int
    {
        $complexity = count($dimensions['relatedDomains']) + count($dimensions['uncertainties']) + count($dimensions['controlDimensions']);

        return $complexity >= 10 ? 7 : ($complexity >= 6 ? 5 : 3);
    }
}
