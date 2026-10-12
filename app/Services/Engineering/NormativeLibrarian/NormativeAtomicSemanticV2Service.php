<?php

namespace App\Services\Engineering\NormativeLibrarian;

class NormativeAtomicSemanticV2Service
{
    public const VERSION = 'ATOMIC_SEMANTIC_V2';

    public function classify(string $text): array
    {
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $lower = mb_strtolower($text);

        $strongRequirement = $this->firstMatch($lower, [
            '/\bne\s+doit\s+pas\b/u',
            '/\bne\s+doivent\s+pas\b/u',
            '/\bdoit\b/u',
            '/\bdoivent\b/u',
            '/\bdevra\b/u',
            '/\bdevront\b/u',
            '/\bshall\s+not\b/u',
            '/\bmust\s+not\b/u',
            '/\bshall\b/u',
            '/\bmust\b/u',
            '/\best\s+interdit(?:e|es|s)?\b/u',
            '/\bsont\s+interdit(?:e|es|s)?\b/u',
            '/\best\s+requis(?:e)?\b/u',
            '/\bsont\s+requis(?:es)?\b/u',
        ]);

        $recommendation = $this->firstMatch($lower, [
            '/\bil\s+convient\s+de\b/u',
            '/\bil\s+convient\s+que\b/u',
            '/\bshould\b/u',
        ]);

        $permission = $this->firstMatch($lower, [
            '/\bpeut\b/u',
            '/\bpeuvent\b/u',
            '/\bmay\b/u',
            '/\best\s+autorisé(?:e)?\b/u',
            '/\bsont\s+autorisé(?:es)?\b/u',
        ]);

        $procedure = $this->firstMatch($lower, [
            '/\best\s+effectué(?:e)?\b/u',
            '/\bsont\s+effectué(?:es)?\b/u',
            '/\best\s+réalisé(?:e)?\b/u',
            '/\bsont\s+réalisé(?:es)?\b/u',
            '/\best\s+mesuré(?:e)?\b/u',
            '/\bsont\s+mesuré(?:es)?\b/u',
            '/\best\s+déterminé(?:e)?\b/u',
            '/\bsont\s+déterminé(?:es)?\b/u',
        ]);

        $limit = $this->hasExplicitLimit($lower);
        $entities = $this->numericEntities($text);
        $numeric = count($entities) > 0;
        $formula = $this->looksLikeFormula($text);

        if ($formula && ! $strongRequirement && ! $limit) {
            $type = 'FORMULA_TABLE';
            $marker = null;
        } elseif ($numeric && $limit && $strongRequirement) {
            $type = 'TOLERANCE';
            $marker = $strongRequirement;
        } elseif ($strongRequirement) {
            $type = 'REQUIREMENT';
            $marker = $strongRequirement;
        } elseif ($recommendation) {
            $type = 'RECOMMENDATION';
            $marker = $recommendation;
        } elseif ($permission) {
            $type = 'PERMISSION';
            $marker = $permission;
        } elseif ($procedure) {
            $type = 'PROCEDURE_CANDIDATE';
            $marker = $procedure;
        } elseif ($numeric && $limit) {
            // Valeur avec limite mais sans verbe normatif explicite : revue obligatoire.
            $type = 'LIMIT_CANDIDATE';
            $marker = null;
        } elseif ($numeric) {
            $type = 'REFERENCE_VALUE';
            $marker = null;
        } elseif ($formula) {
            $type = 'FORMULA_TABLE';
            $marker = null;
        } else {
            $type = 'OTHER';
            $marker = null;
        }

        $confidence = 45.0;
        if ($strongRequirement) $confidence += 25.0;
        if ($recommendation || $permission || $procedure) $confidence += 15.0;
        if ($limit) $confidence += 10.0;
        if ($numeric) $confidence += 10.0;
        if ($formula) $confidence += 5.0;

        return [
            'type'=>$type,
            'marker'=>$marker,
            'has_explicit_limit'=>$limit,
            'has_numeric_unit'=>$numeric,
            'looks_like_formula'=>$formula,
            'numeric_entities'=>$entities,
            'confidence'=>min(100.0, $confidence),
        ];
    }

    public function split(string $text): array
    {
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        // OCR bullets / listes.
        $text = preg_replace('/\s*[•▪◦]\s*/u', "\n§§", $text);
        $text = preg_replace('/\s+-\s+(?=[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ])/u', "\n§§", $text);
        $text = preg_replace('/\n\s*(?=\d+°\s*)/u', "\n§§", $text);

        // Phrases et propositions fortes.
        $text = preg_replace('/;\s+(?=[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9])/u', ";\n§§", $text);
        $text = preg_replace('/(?<=[.!?])\s+(?=[A-ZÀÂÄÇÉÈÊËÎÏÔÖÙÛÜ0-9])/u', "\n§§", $text);

        $parts = preg_split('/(?:\n§§|\n{2,})/u', $text) ?: [];
        $out = [];

        foreach ($parts as $part) {
            $part = trim(preg_replace('/[ \t]+/u', ' ', $part));
            $part = trim(preg_replace('/\s+/u', ' ', $part));

            if (mb_strlen($part) < 8) {
                continue;
            }

            $out[] = $part;
        }

        return array_values(array_unique($out));
    }

    private function hasExplicitLimit(string $lower): bool
    {
        return preg_match(
            '/(?:±|≤|≥|<|>|\bminimum\b|\bmaximum\b|\bmin\.?\b|\bmax\.?\b|'
            .'\bau\s+moins\b|\bau\s+plus\b|\bau\s+minimum\b|\bau\s+maximum\b|'
            .'ne\s+(?:doit|doivent|soit|soient)\s+pas\s+dépass|'
            .'sans\s+dépass|inférieur(?:e)?|supérieur(?:e)?|'
            .'tolérance|écart\s+admissible|limite\s+admissible|'
            .'not\s+exceed|at\s+least|at\s+most|less\s+than|greater\s+than)/u',
            $lower
        ) === 1;
    }

    private function numericEntities(string $text): array
    {
        $pattern = '/(?P<op>±|≤|≥|<|>|max(?:imum)?|min(?:imum)?|au\s+moins|au\s+plus)?\s*'
            .'(?P<value>\d+(?:[.,]\d+)?)\s*'
            .'(?P<unit>mm|cm|m|m2|m²|m3|m³|mpa|n\/mm2|n\/mm²|kn|%|°c|kg|kg\/m3|kg\/m³|h|jour|jours)\b/ui';

        preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);

        return array_map(fn ($m) => [
            'operator'=>trim((string) ($m['op'] ?? '')) ?: null,
            'value'=>str_replace(',', '.', (string) $m['value']),
            'unit'=>(string) $m['unit'],
        ], $matches);
    }

    private function looksLikeFormula(string $text): bool
    {
        if (preg_match('/\b[A-Za-z]{1,5}\s*=\s*[^.;]{2,}/u', $text)) {
            return true;
        }

        preg_match_all('/\d+(?:[.,]\d+)?/u', $text, $numbers);

        return count($numbers[0]) >= 4
            && preg_match('/[=<>×*\/]/u', $text) === 1;
    }

    private function firstMatch(string $lower, array $patterns): ?string
    {
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $lower, $m)) {
                return trim((string) $m[0]);
            }
        }

        return null;
    }
}
