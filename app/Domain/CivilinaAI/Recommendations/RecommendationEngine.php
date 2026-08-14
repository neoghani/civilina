<?php

namespace App\Domain\CivilinaAI\Recommendations;

use App\Domain\CivilinaAI\Registries\RegistryData;

final class RecommendationEngine
{
    public function rank(array $dimensions): array
    {
        $candidates = [];
        foreach (RegistryData::get('recommendations') as $id => $definition) {
            $evidence = array_values(array_filter($dimensions['evidence'], fn (array $item): bool => in_array($item['concept'], $definition['concepts'], true)));
            $conceptMatches = array_intersect($definition['concepts'], $dimensions['concepts']);
            $blocked = array_intersect($definition['blockedBy'] ?? [], array_map(fn (array $item): string => $item['id'], $dimensions['uncertainties'])) !== [];
            $score = count($conceptMatches) * 12 + count($evidence) * 4 + (in_array($definition['capability'], $dimensions['capabilities'], true) ? 3 : 0);
            if ($score <= 0 || $evidence === []) {
                continue;
            }
            $candidates[] = [
                'id' => $id,
                'label' => $definition['label'],
                'capability' => $definition['capability'],
                'score' => $score,
                'blocked' => $blocked,
                'evidence' => $evidence,
                'justification' => 'Recommandation reliée à un signal métier explicite.',
            ];
        }
        usort($candidates, fn (array $left, array $right): int => $right['score'] <=> $left['score'] ?: strcmp($left['id'], $right['id']));

        if ($dimensions['peopleGuardrail']) {
            $candidates = array_slice(array_values(array_filter($candidates, fn (array $item): bool => $item['id'] !== 'people-decision-framework')), 0, 2);

            return array_map(fn (array $item): array => $item + ['priority' => 'explore'], $candidates);
        }

        return array_map(function (array $item, int $index): array {
            $item['priority'] = $index < 3 ? 'immediate' : 'explore';

            return $item;
        }, array_slice($candidates, 0, 5), array_keys(array_slice($candidates, 0, 5)));
    }
}
