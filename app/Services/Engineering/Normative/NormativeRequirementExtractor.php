<?php

namespace App\Services\Engineering\Normative;

class NormativeRequirementExtractor
{
    /**
     * Extrait uniquement des exigences réellement présentes dans le texte.
     * Il ne complète jamais une valeur manquante.
     *
     * @return array<int, array<string,mixed>>
     */
    public function extract(string $body): array
    {
        $paragraphs = preg_split('/(?<=[\.\;\:])\s+|\R+/u', $body) ?: [];
        $results = [];

        foreach ($paragraphs as $paragraph) {
            $text = trim(preg_replace('/\s+/u', ' ', $paragraph));
            if (mb_strlen($text) < 12 || mb_strlen($text) > 1800) {
                continue;
            }

            if (! $this->looksLikeRequirement($text)) {
                continue;
            }

            $parsed = $this->parseNumericRequirement($text);
            $parsed['requirement_type'] = $this->detectType($text);
            $parsed['subject'] = $this->detectSubject($text);
            $parsed['requirement_text'] = $text;
            $parsed['source_context'] = $text;
            $parsed['extraction_confidence'] = $this->confidence($text, $parsed);
            $parsed['keywords'] = $this->keywords($text);

            $results[] = $parsed;
        }

        return $results;
    }

    private function looksLikeRequirement(string $text): bool
    {
        $lower = mb_strtolower($text);

        $verbs = [
            'doit', 'doivent', 'devra', 'devront', 'shall', 'must',
            'ne doit pas', 'est exig', 'sont exig', 'minimum', 'maximum',
            'au moins', 'au plus', 'ne dépasse', 'ne depasse',
            'tolérance', 'tolerance', 'écart', 'ecart', 'compris entre',
            'conforme', 'acceptation', 'fréquence', 'frequence',
        ];

        foreach ($verbs as $verb) {
            if (str_contains($lower, $verb)) {
                return true;
            }
        }

        return (bool) preg_match('/(?:±|≤|>=|<=|≥|<|>)\s*\d+(?:[.,]\d+)?\s*(?:%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m|jours?|h)\b/ui', $text);
    }

    private function detectType(string $text): string
    {
        $lower = mb_strtolower($text);

        if (str_contains($text, '±') || str_contains($lower, 'tolérance') || str_contains($lower, 'tolerance') || str_contains($lower, 'écart') || str_contains($lower, 'ecart')) {
            return 'tolerance';
        }
        if (preg_match('/\b(minimum|maximum|au moins|au plus|ne doit pas dépasser|ne doit pas depasser)\b/ui', $text)) {
            return 'limit';
        }
        if (preg_match('/\b(fr[ée]quence|tous les|toutes les|par lot|par couche|par jour|7 jours|28 jours)\b/ui', $text)) {
            return 'frequency';
        }
        if (preg_match('/\b(conforme|acceptation|accepté|accepte|rejet)\b/ui', $text)) {
            return 'acceptance';
        }

        return 'requirement';
    }

    private function detectSubject(string $text): ?string
    {
        $subjects = [
            'compacité' => ['compacité', 'compactage'],
            'portance' => ['portance', 'plaque'],
            'déflexion' => ['déflexion', 'deflexion'],
            'résistance' => ['résistance', 'resistance', 'mpa'],
            'consistance' => ['consistance', 'affaissement', 'slump'],
            'température' => ['température', 'temperature'],
            'enrobage' => ['enrobage'],
            'serrage' => ['serrage', 'couple'],
            'géométrie' => ['alignement', 'verticalité', 'verticalite', 'niveau', 'pente'],
        ];

        $lower = mb_strtolower($text);
        foreach ($subjects as $subject => $terms) {
            foreach ($terms as $term) {
                if (str_contains($lower, $term)) {
                    return $subject;
                }
            }
        }

        return null;
    }

    /**
     * @return array<string,mixed>
     */
    private function parseNumericRequirement(string $text): array
    {
        $result = [
            'operator' => null,
            'min_value' => null,
            'max_value' => null,
            'nominal_value' => null,
            'tolerance_plus' => null,
            'tolerance_minus' => null,
            'unit' => null,
        ];

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*±\s*(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)?/ui', $text, $m)) {
            $result['nominal_value'] = $this->number($m[1]);
            $tol = $this->number($m[2]);
            $result['tolerance_plus'] = $tol;
            $result['tolerance_minus'] = $tol;
            $result['operator'] = '±';
            $result['unit'] = $m[3] ?? null;
            return $result;
        }

        if (preg_match('/(?:compris|comprise)\s+entre\s+(\d+(?:[.,]\d+)?)\s*(?:et|à)\s*(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)?/ui', $text, $m)) {
            $result['min_value'] = $this->number($m[1]);
            $result['max_value'] = $this->number($m[2]);
            $result['operator'] = 'between';
            $result['unit'] = $m[3] ?? null;
            return $result;
        }

        if (preg_match('/(≥|>=|≤|<=|>|<)\s*(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)?/ui', $text, $m)) {
            $result['operator'] = $m[1];
            $value = $this->number($m[2]);
            if (in_array($m[1], ['≥', '>=', '>'], true)) {
                $result['min_value'] = $value;
            } else {
                $result['max_value'] = $value;
            }
            $result['unit'] = $m[3] ?? null;
            return $result;
        }

        if (preg_match('/\b(minimum|au moins)\s+(?:de\s+)?(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)?/ui', $text, $m)) {
            $result['operator'] = '>=';
            $result['min_value'] = $this->number($m[2]);
            $result['unit'] = $m[3] ?? null;
            return $result;
        }

        if (preg_match('/\b(maximum|au plus|ne doit pas d[ée]passer)\s+(?:de\s+)?(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)?/ui', $text, $m)) {
            $result['operator'] = '<=';
            $result['max_value'] = $this->number($m[2]);
            $result['unit'] = $m[3] ?? null;
            return $result;
        }

        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(%|mm|cm|m|MPa|kPa|Pa|°C|C|N|kN|Nm|N\.m)\b/u', $text, $m)) {
            $result['nominal_value'] = $this->number($m[1]);
            $result['unit'] = $m[2];
        }

        return $result;
    }

    private function number(string $value): float
    {
        return (float) str_replace(',', '.', $value);
    }

    private function confidence(string $text, array $parsed): float
    {
        $score = 55.0;
        if ($parsed['operator']) {
            $score += 18;
        }
        if ($parsed['unit']) {
            $score += 12;
        }
        if (preg_match('/\b(doit|doivent|shall|must|minimum|maximum|tol[ée]rance|acceptation)\b/ui', $text)) {
            $score += 12;
        }
        return min(99.0, $score);
    }

    /**
     * @return array<int,string>
     */
    private function keywords(string $text): array
    {
        $ascii = mb_strtolower(\Illuminate\Support\Str::ascii($text));
        $tokens = preg_split('/[^a-z0-9]+/', $ascii) ?: [];
        $stop = ['avec','dans','pour','sans','doit','doivent','shall','must','entre','cette','ce','les','des','une','un','et','ou','de','du','la','le','est','sont'];
        $tokens = array_values(array_unique(array_filter($tokens, fn ($t) => strlen($t) >= 4 && ! in_array($t, $stop, true))));
        return array_slice($tokens, 0, 24);
    }
}
