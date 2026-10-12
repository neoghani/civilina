<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Str;

class NormativeQaRegistryMatcherV3
{
    public const VERSION = 'CIVILINA_QA_NORMATIVE_V3';

    private const FAMILY_SIGNALS = [
        'CONCRETE' => ['beton', 'concrete', 'ciment', 'slump', 'eprouvette', 'coffrage', 'ferraillage'],
        'STEEL' => ['acier', 'steel', 'charpente', 'metallique', 'boulon', 'serrage'],
        'WELDING' => ['soudage', 'soudure', 'welding', 'weld', 'joint soude'],
        'EARTHWORKS' => ['terrassement', 'earthwork', 'remblai', 'soil', 'compactage', 'proctor', 'troxler'],
        'ROADS' => ['route', 'road', 'chaussee', 'pavement', 'enrobe', 'deflexion'],
        'MASONRY' => ['maconnerie', 'masonry', 'brique', 'bloc'],
        'WATERPROOFING' => ['etancheite', 'waterproofing', 'membrane'],
        'PLASTER_FACADE' => ['platrerie', 'plaster', 'facade', 'enduit'],
        'PLUMBING' => ['plomberie', 'plumbing', 'sanitaire', 'tuyauterie'],
        'ELECTRICAL' => ['electricite', 'electrical', 'electrique', 'cablage'],
        'HVAC' => ['hvac', 'climatisation', 'ventilation', 'aeraulique'],
        'QUALITY' => ['qualite', 'quality', 'audit', 'management system'],
        'METROLOGY_TESTING' => ['metrologie', 'essai', 'testing', 'etalonnage', 'mesurage'],
    ];

    private const WORK_TYPE_FAMILIES = [
        'reinforced_concrete' => 'CONCRETE',
        'concrete' => 'CONCRETE',
        'steel_structure' => 'STEEL',
        'welding' => 'WELDING',
        'earthworks' => 'EARTHWORKS',
        'road' => 'ROADS',
        'masonry' => 'MASONRY',
        'waterproofing' => 'WATERPROOFING',
        'plaster_facade' => 'PLASTER_FACADE',
        'plumbing' => 'PLUMBING',
        'electrical' => 'ELECTRICAL',
        'hvac' => 'HVAC',
        'quality' => 'QUALITY',
        'metrology_testing' => 'METROLOGY_TESTING',
    ];

    public function __construct(
        private readonly NormativeClassificationPreparationService $classifier
    ) {
    }

    public function match(object|array $inspection, object|array $registry): array
    {
        $inspection = (object) $inspection;
        $registry = (object) $registry;
        $inspectionText = $this->inspectionText($inspection);
        $requirementText = $this->normalize(implode(' ', array_filter([
            $registry->reference_code ?? null,
            $registry->section_code ?? null,
            $registry->heading ?? null,
            $registry->requirement_text ?? null,
        ])));

        $inspectionFamily = $this->inspectionFamily($inspection, $inspectionText);
        $registryFamily = $this->registryFamily($registry, $requirementText);

        if ($inspectionFamily['key'] === null || $registryFamily['key'] === null) {
            return $this->noMatch(
                'Domaine métier insuffisamment établi ; aucun lien normatif créé.',
                $inspectionFamily,
                $registryFamily,
                $registry
            );
        }

        if ($inspectionFamily['key'] !== $registryFamily['key']) {
            return $this->noMatch(
                'Domaines incompatibles : '.$inspectionFamily['label'].' ≠ '.$registryFamily['label'].'.',
                $inspectionFamily,
                $registryFamily,
                $registry
            );
        }

        $signals = self::FAMILY_SIGNALS[$inspectionFamily['key']] ?? [];
        $sharedSignals = array_values(array_filter(
            $signals,
            fn (string $signal): bool => str_contains($inspectionText, $signal)
                && str_contains($requirementText, $signal)
        ));
        $referenceMatch = $this->referenceMatch($inspection, $registry);
        $score = 50
            + min(30, count($sharedSignals) * 10)
            + ($referenceMatch ? 10 : 0)
            + (in_array($registry->requirement_type ?? null, ['REQUIREMENT', 'TOLERANCE'], true) ? 5 : 0)
            + ($registryFamily['authority'] === 'HUMAN_CLASSIFIED' ? 5 : 0);
        $score = min(100, $score);

        $evidence = [
            'domain' => $inspectionFamily['label'],
            'domain_authority' => $registryFamily['authority'],
            'shared_signals' => $sharedSignals,
            'reference_match' => $referenceMatch,
        ];

        if ($registryFamily['authority'] === 'HUMAN_CLASSIFIED' && $score >= 70 && $sharedSignals !== []) {
            return $this->result(
                'MATCH',
                $score,
                'Domaine validé et signaux métier concordants : '.implode(', ', $sharedSignals).'.',
                $inspectionFamily['label'],
                $evidence,
                $registry
            );
        }

        if ($score >= 65 && $sharedSignals !== []) {
            return $this->result(
                'PROPOSED',
                $score,
                'Lien plausible dans le même domaine, soumis à validation humaine.',
                $inspectionFamily['label'],
                $evidence,
                $registry,
                'HUMAN_REVIEW_REQUIRED'
            );
        }

        return $this->noMatch(
            'Preuves métier insuffisantes dans le domaine compatible.',
            $inspectionFamily,
            $registryFamily,
            $registry,
            $score,
            $evidence
        );
    }

    private function inspectionFamily(object $inspection, string $text): array
    {
        $workType = $this->normalize((string) ($inspection->work_type ?? ''));
        if ($workType === 'earthworks_road') {
            $key = $this->containsAny($text, self::FAMILY_SIGNALS['ROADS']) ? 'ROADS' : 'EARTHWORKS';

            return $this->family($key, 'EXPLICIT_WORK_TYPE');
        }
        if (isset(self::WORK_TYPE_FAMILIES[$workType])) {
            return $this->family(self::WORK_TYPE_FAMILIES[$workType], 'EXPLICIT_WORK_TYPE');
        }

        $prepared = $this->classifier->prepare([
            'title' => $inspection->title ?? null,
            'metadata' => [
                'record_type' => $inspection->record_type ?? null,
                'register_type' => $inspection->register_type ?? null,
            ],
            'content' => $text,
        ]);

        return $this->familyFromLabel($prepared['suggested_family'] ?? null, 'LOCAL_SUGGESTION');
    }

    private function registryFamily(object $registry, string $text): array
    {
        if (($registry->classification_status ?? null) === 'CLASSIFIED') {
            $family = $this->familyFromLabel($registry->classification_family ?? null, 'HUMAN_CLASSIFIED');
            if ($family['key'] !== null) {
                return $family;
            }
        }

        if (($registry->classification_status ?? null) === 'TO_REVIEW') {
            $family = $this->familyFromLabel($registry->suggested_family ?? null, 'LOCAL_SUGGESTION');
            if ($family['key'] !== null) {
                return $family;
            }
        }

        $prepared = $this->classifier->prepare([
            'reference' => $registry->reference_code ?? null,
            'title' => $registry->heading ?? null,
            'content' => $text,
        ]);

        return $this->familyFromLabel($prepared['suggested_family'] ?? null, 'LOCAL_SUGGESTION');
    }

    private function inspectionText(object $inspection): string
    {
        return $this->normalize(implode(' ', array_filter([
            $inspection->work_type ?? null,
            $inspection->title ?? null,
            $inspection->record_type ?? null,
            $inspection->register_type ?? null,
            $inspection->checkpoint_type ?? null,
            $inspection->scope_label ?? null,
            $inspection->acceptance_criteria ?? null,
            $inspection->requirement_source ?? null,
            $inspection->notes ?? null,
        ])));
    }

    private function referenceMatch(object $inspection, object $registry): bool
    {
        $source = $this->normalize((string) ($inspection->requirement_source ?? ''));
        $reference = $this->normalize((string) ($registry->reference_code ?? ''));

        return $source !== '' && $reference !== '' && str_contains($source, $reference);
    }

    private function noMatch(
        string $reason,
        array $inspectionFamily,
        array $registryFamily,
        object $registry,
        float $score = 0,
        array $evidence = []
    ): array {
        return $this->result(
            'NO_MATCH',
            $score,
            $reason,
            $inspectionFamily['label'] ?? null,
            $evidence + [
                'inspection_family' => $inspectionFamily,
                'registry_family' => $registryFamily,
            ],
            $registry
        );
    }

    private function result(
        string $status,
        float $score,
        string $justification,
        ?string $family,
        array $evidence,
        object $registry,
        string $reviewStatus = 'NOT_REQUIRED'
    ): array {
        return [
            'result_status' => $status,
            'score' => round($score, 2),
            'business_family' => $family,
            'justification' => $justification,
            'evidence' => $evidence,
            'provenance' => [
                'registry_requirement_id' => $registry->id ?? null,
                'publication_state' => $registry->publication_state ?? null,
                'source_atomic_statement_id' => $registry->source_atomic_statement_id ?? null,
                'source_hash' => $registry->source_hash ?? null,
                'provenance_hash' => $registry->provenance_hash ?? null,
                'reference_code' => $registry->reference_code ?? null,
                'section_code' => $registry->section_code ?? null,
                'matcher_version' => self::VERSION,
            ],
            'review_status' => $reviewStatus,
        ];
    }

    private function family(string $key, string $authority): array
    {
        return [
            'key' => $key,
            'label' => NormativeClassificationPreparationService::FAMILIES[$key] ?? $key,
            'authority' => $authority,
        ];
    }

    private function familyFromLabel(?string $label, string $authority): array
    {
        $normalized = $this->normalize((string) $label);
        foreach (NormativeClassificationPreparationService::FAMILIES as $key => $knownLabel) {
            if ($normalized !== '' && $normalized === $this->normalize($knownLabel)) {
                return $this->family($key, $authority);
            }
        }

        return ['key' => null, 'label' => null, 'authority' => null];
    }

    private function containsAny(string $text, array $signals): bool
    {
        foreach ($signals as $signal) {
            if (str_contains($text, $signal)) {
                return true;
            }
        }

        return false;
    }

    private function normalize(string $value): string
    {
        return preg_replace('/\s+/u', ' ', mb_strtolower(trim(Str::ascii($value)))) ?: '';
    }
}
