<?php

namespace App\Domain\CivilinaAI\Facts;

use App\Domain\CivilinaAI\DTO\DiagnosticInput;
use App\Domain\CivilinaAI\Privacy\PrivacyClassifier;
use App\Domain\CivilinaAI\Registries\RegistryData;

final readonly class FactExtractor
{
    private const MODE_PRIORITY = ['decide', 'calculate', 'critique', 'audit', 'review', 'create', 'design', 'compare', 'prioritize', 'plan', 'troubleshoot', 'diagnose', 'explain'];
    private const PERSONA_PRIORITY = ['executive', 'contractManager', 'hseManager', 'qualityManager', 'procurementManager', 'engineeringManager', 'siteManager', 'projectControls', 'pmo', 'projectManager', 'isoManager', 'digitalManager', 'peopleManager'];
    private const SCOPE_PRIORITY = ['person', 'team', 'activity', 'workPackage', 'zone', 'ouvrage', 'wbs', 'project', 'program', 'portfolio', 'department', 'process', 'enterprise'];
    private const EVIDENCE_PACKS = [
        'wbs' => ['structure/codes', 'levels', 'scope representation'],
        'planning' => ['activities', 'logic', 'dates', 'constraints', 'calendars', 'baseline/current'],
        'costControl' => ['budget', 'commitments', 'actual', 'forecast'],
        'procurement' => ['requirement dates', 'PO/delivery dates', 'lead times'],
        'quality' => ['ITP', 'NCR', 'inspection records'],
        'contractsClaims' => ['event', 'contract basis', 'notices', 'records', 'time/cost impact'],
        'dataGovernance' => ['KPI definition', 'data owner', 'source lineage', 'reconciliation evidence'],
    ];

    public function __construct(private PrivacyClassifier $privacy)
    {
    }

    public function extract(array $recognition, DiagnosticInput $input): array
    {
        $categories = $recognition['byCategory'];
        $interactionMode = $this->firstByPriority($categories['interactionMode'] ?? [], self::MODE_PRIORITY, 'diagnose');
        $persona = $this->firstByPriority($categories['persona'] ?? [], self::PERSONA_PRIORITY, 'unknown');
        $scopeSignals = $categories['scope'] ?? [];
        $scopeLevel = $this->firstByPriority($scopeSignals, self::SCOPE_PRIORITY, $scopeSignals[0] ?? 'enterprise');
        $businessEntities = RegistryData::get('businessEntities');
        $entitySignals = $this->unique(array_merge(
            $categories['entity'] ?? [],
            array_values(array_filter($scopeSignals, fn (string $id): bool => in_array($id, $businessEntities, true)))
        ));
        $concepts = $recognition['concepts'];
        $conceptMap = RegistryData::get('conceptMap');
        $domainScores = [];
        $capabilityScores = [];
        $controlDimensions = [];
        $mappedEntities = [];

        foreach ($concepts as $concept) {
            $map = $conceptMap[$concept] ?? null;
            if ($map === null) {
                continue;
            }
            $this->addScores($domainScores, $map['domains'] ?? [], 2);
            $this->addScores($capabilityScores, $map['capabilities'] ?? [], 2);
            array_push($controlDimensions, ...($map['dimensions'] ?? []));
            array_push($mappedEntities, ...($map['entities'] ?? []));
        }
        if ($persona === 'executive') {
            $this->addScores($domainScores, ['executiveControl'], 4);
        }
        if (array_intersect($entitySignals, ['project', 'wbs', 'workPackage', 'activity', 'ouvrage', 'zone']) !== []) {
            $this->addScores($domainScores, ['projects'], 2);
        }
        if (in_array('department', $scopeSignals, true)) {
            $this->addScores($domainScores, ['organisation'], 1);
        }

        $domainRanking = $this->rankScores($domainScores, 1);
        $primaryDomain = $domainRanking[0] ?? null;
        $relatedDomains = array_slice(array_values(array_filter($domainRanking, fn (string $id): bool => $id !== $primaryDomain && $domainScores[$id] >= 2)), 0, 4);
        $capabilities = array_slice($this->rankScores($capabilityScores, 2), 0, 8);
        $controlDimensions = array_values(array_filter($this->unique($controlDimensions), fn (string $id): bool => in_array($id, RegistryData::get('controlDimensions'), true)));
        $entities = array_values(array_filter($this->unique(array_merge($entitySignals, $mappedEntities)), fn (string $id): bool => in_array($id, $businessEntities, true)));

        $symptoms = $this->unique($categories['symptom'] ?? []);
        $suspectedCauses = array_map(fn (string $id): string => "concept:{$id}", $recognition['suspectedConcepts']);
        $confirmedCauses = array_map(fn (string $id): string => "concept:{$id}", $recognition['confirmedConcepts']);
        $causeClarification = $input->answers['causeClarification'] ?? null;
        if ($causeClarification && $causeClarification !== 'unknown') {
            $confirmedCauses[] = "answer:{$causeClarification}";
        }
        $causeUnknown = $recognition['unknownCause'] || (in_array('delay', $symptoms, true) && $confirmedCauses === []);

        $facts = $this->unique(array_merge(
            array_map(fn (string $id): string => "concept:{$id}", $concepts),
            array_map(fn (string $id): string => "entity:{$id}", $entities),
            array_map(fn (string $id): string => "symptom:{$id}", $symptoms),
        ));
        $evidence = [];
        foreach ($recognition['matches'] as $match) {
            if (in_array($match['category'], ['concept', 'entity', 'symptom', 'persona', 'scope'], true)) {
                $evidence[] = [
                    'source' => $match['id'],
                    'concept' => $match['value'],
                    'reason' => 'Signal explicite reconnu : '.$match['terms'][0].'.',
                    'strength' => 'direct',
                ];
            }
        }

        $uncertainties = [];
        if ($primaryDomain === null) {
            $uncertainties[] = ['id' => 'DOMAIN_UNKNOWN', 'blocking' => true, 'message' => 'Le domaine principal reste à préciser.'];
        }
        if ($causeUnknown) {
            $uncertainties[] = ['id' => 'CAUSE_UNKNOWN', 'blocking' => true, 'message' => 'Le symptôme est identifié, mais sa cause n’est pas suffisamment établie.'];
        }
        if ($recognition['suspectedConcepts'] !== []) {
            $uncertainties[] = ['id' => 'CAUSE_SUSPECTED_NOT_CONFIRMED', 'blocking' => true, 'message' => 'Une cause est suspectée mais pas confirmée par les éléments disponibles.'];
        }

        $peopleSignal = in_array('people', $concepts, true) || $this->privacy->detectsPeopleSignal($input->freeText);

        return [
            'interactionMode' => $interactionMode,
            'persona' => $persona,
            'decisionHorizon' => ($categories['horizon'] ?? [])[0] ?? 'unknown',
            'scope' => ['level' => $scopeLevel, 'entities' => $entities],
            'concepts' => $concepts,
            'domainScores' => $domainScores,
            'capabilityScores' => $capabilityScores,
            'primaryDomain' => $primaryDomain,
            'relatedDomains' => $relatedDomains,
            'capabilities' => $capabilities,
            'controlDimensions' => $controlDimensions,
            'facts' => $facts,
            'symptoms' => $symptoms,
            'suspectedCauses' => $this->unique($suspectedCauses),
            'confirmedCauses' => $this->unique($confirmedCauses),
            'impacts' => array_values(array_filter($controlDimensions, fn (string $id): bool => in_array($id, ['time', 'cost', 'progress', 'quality', 'risk', 'safety', 'decision'], true))),
            'evidence' => $evidence,
            'uncertainties' => $uncertainties,
            'sensitiveIntent' => $this->privacy->detectsSensitiveIntent($input->freeText),
            'peopleGuardrail' => $this->privacy->detectsPeopleDecision($input->freeText) || ($peopleSignal && in_array($interactionMode, ['decide', 'compare'], true)),
            'safetyCritical' => in_array('safetyCritical', $categories['flag'] ?? [], true),
            'externalVerificationRequired' => in_array('externalVerification', $categories['flag'] ?? [], true),
            'contractInterpretation' => in_array('contractInterpretation', $categories['flag'] ?? [], true),
            'hasNumbers' => $recognition['hasNumbers'],
            'hasDocumentReference' => $recognition['hasDocumentReference'],
        ];
    }

    public function evidencePackFor(array $dimensions): ?array
    {
        foreach (['contractsClaims', 'quality', 'procurement', 'planning', 'wbs', 'costControl', 'dataGovernance'] as $key) {
            if (in_array($key, $dimensions['concepts'], true)) {
                return ['key' => $key, 'required' => self::EVIDENCE_PACKS[$key]];
            }
        }

        return null;
    }

    private function unique(array $values): array
    {
        return array_values(array_unique($values));
    }

    private function firstByPriority(array $values, array $priorities, string $fallback): string
    {
        foreach ($priorities as $priority) {
            if (in_array($priority, $values, true)) {
                return $priority;
            }
        }

        return $fallback;
    }

    private function addScores(array &$scores, array $ids, int $weight): void
    {
        foreach ($ids as $id) {
            $scores[$id] = ($scores[$id] ?? 0) + $weight;
        }
    }

    private function rankScores(array $scores, int $threshold): array
    {
        $ids = array_values(array_filter(array_keys($scores), fn (string $id): bool => $scores[$id] >= $threshold));
        usort($ids, fn (string $left, string $right): int => $scores[$right] <=> $scores[$left] ?: strcmp($left, $right));

        return $ids;
    }
}
