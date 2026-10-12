<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Str;

class NormativeClassificationPreparationService
{
    public const FAMILIES = [
        'CONCRETE' => 'Béton',
        'STEEL' => 'Acier / construction métallique',
        'WELDING' => 'Soudage',
        'EARTHWORKS' => 'Terrassement',
        'ROADS' => 'Routes',
        'MASONRY' => 'Maçonnerie',
        'WATERPROOFING' => 'Étanchéité',
        'PLASTER_FACADE' => 'Plâtrerie / façade',
        'PLUMBING' => 'Plomberie',
        'ELECTRICAL' => 'Électricité',
        'HVAC' => 'HVAC',
        'QUALITY' => 'Qualité',
        'METROLOGY_TESTING' => 'Métrologie / essais',
    ];

    private const SIGNALS = [
        'CONCRETE' => ['beton', 'concrete', 'ciment'],
        'STEEL' => ['acier', 'steel', 'construction metallique'],
        'WELDING' => ['soudage', 'soudure', 'welding'],
        'EARTHWORKS' => ['terrassement', 'earthwork', 'remblai'],
        'ROADS' => ['route', 'road', 'chaussee'],
        'MASONRY' => ['maconnerie', 'masonry'],
        'WATERPROOFING' => ['etancheite', 'waterproofing'],
        'PLASTER_FACADE' => ['platrerie', 'facade', 'plaster'],
        'PLUMBING' => ['plomberie', 'plumbing', 'sanitaire'],
        'ELECTRICAL' => ['electricite', 'electrical', 'electrique'],
        'HVAC' => ['hvac', 'climatisation', 'ventilation'],
        'QUALITY' => ['qualite', 'quality', 'management system'],
        'METROLOGY_TESTING' => ['metrologie', 'essai', 'testing', 'mesurage'],
    ];

    public function prepare(array $localData): array
    {
        $existing = $this->existingFamily((string) ($localData['existing_classification'] ?? ''));
        if ($existing !== null) {
            return [
                'status' => 'CLASSIFIED',
                'family' => self::FAMILIES[$existing],
                'suggested_family' => null,
                'evidence' => [['field' => 'existing_classification', 'value' => $localData['existing_classification']]],
                'reason' => 'Classification locale existante conservee.',
            ];
        }

        $fields = [
            'reference' => $localData['reference'] ?? null,
            'title' => $localData['title'] ?? null,
            'ics' => $localData['ics'] ?? null,
            'path' => $localData['path'] ?? null,
            'metadata' => $localData['metadata'] ?? null,
            'content' => $localData['content'] ?? null,
        ];

        $matches = [];
        foreach (self::SIGNALS as $family => $signals) {
            foreach ($fields as $field => $value) {
                $normalized = $this->normalize(is_array($value) ? json_encode($value) : (string) $value);
                foreach ($signals as $signal) {
                    if ($normalized !== '' && str_contains($normalized, $signal)) {
                        $matches[$family][$field] = $signal;
                        break;
                    }
                }
            }
        }

        if ($matches === []) {
            return [
                'status' => 'UNCLASSIFIED',
                'family' => null,
                'suggested_family' => null,
                'evidence' => [],
                'reason' => 'Aucune preuve locale suffisante.',
            ];
        }

        uasort($matches, fn (array $a, array $b) => count($b) <=> count($a));
        $families = array_keys($matches);
        $best = $families[0];
        $bestScore = count($matches[$best]);
        $secondScore = isset($families[1]) ? count($matches[$families[1]]) : 0;

        $suggestion = $bestScore > $secondScore ? self::FAMILIES[$best] : null;
        $evidence = [];
        foreach ($matches[$best] as $field => $signal) {
            $evidence[] = ['field' => $field, 'signal' => $signal];
        }

        return [
            'status' => 'TO_REVIEW',
            'family' => null,
            'suggested_family' => $suggestion,
            'evidence' => $evidence,
            'reason' => $suggestion
                ? 'Suggestion locale uniquement ; validation humaine requise.'
                : 'Signaux locaux ambigus ; aucune famille forcee.',
        ];
    }

    private function existingFamily(string $value): ?string
    {
        $normalized = $this->normalize($value);
        foreach (self::FAMILIES as $key => $label) {
            if ($normalized === $this->normalize($label)) {
                return $key;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        return mb_strtolower(trim(Str::ascii($value)));
    }
}
