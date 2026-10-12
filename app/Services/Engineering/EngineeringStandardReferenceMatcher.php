<?php

namespace App\Services\Engineering;

class EngineeringStandardReferenceMatcher
{
    public function detectReference(string $name): ?string
    {
        $patterns = [
            '/\b(NF\s*EN|EN|ISO|NF|ASTM|AWS|API|BS|DIN|DTR|GTR)\s*[-_:]?\s*[A-Z0-9][A-Z0-9.\-\/ ]{1,30}/iu',
            '/\b[A-Z]{2,8}\s*[-_]?\s*\d{2,6}(?:[-_:\/]\d{1,6})?\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $name, $match)) {
                return trim(preg_replace('/\s+/', ' ', $match[0]) ?: $match[0]);
            }
        }

        return null;
    }

    /**
     * @param array<int, array{id:string,label:string,values:array<int,string>}> $catalog
     */
    public function match(string $filenameBase, ?string $reference, array $catalog): ?array
    {
        $referenceNorm = $reference ? $this->normalize($reference) : '';
        $filenameNorm = $this->normalize($filenameBase);

        $best = null;
        $bestScore = 0;

        foreach ($catalog as $document) {
            $score = 0;

            foreach ($document['values'] as $value) {
                $valueNorm = $this->normalize($value);
                if ($valueNorm === '') {
                    continue;
                }

                if ($referenceNorm !== '') {
                    if ($referenceNorm === $valueNorm) {
                        $score = max($score, 100);
                    } elseif (
                        mb_strlen($referenceNorm) >= 5
                        && mb_strlen($valueNorm) >= 5
                        && (str_contains($referenceNorm, $valueNorm) || str_contains($valueNorm, $referenceNorm))
                    ) {
                        $score = max($score, 88);
                    }
                }

                if (
                    mb_strlen($valueNorm) >= 8
                    && (str_contains($filenameNorm, $valueNorm) || str_contains($valueNorm, $filenameNorm))
                ) {
                    $score = max($score, 72);
                }

                $shared = $this->sharedSignificantTokens($filenameNorm, $valueNorm);
                if ($shared >= 3) {
                    $score = max($score, min(68, 35 + ($shared * 8)));
                }
            }

            if ($score > $bestScore) {
                $bestScore = $score;
                $best = [
                    'id' => $document['id'],
                    'label' => $document['label'],
                    'score' => $score,
                ];
            }
        }

        return $bestScore >= 60 ? $best : null;
    }

    public function normalize(string $value): string
    {
        $value = mb_strtolower($value);
        $value = strtr($value, [
            'à'=>'a','á'=>'a','â'=>'a','ä'=>'a','ã'=>'a',
            'ç'=>'c',
            'è'=>'e','é'=>'e','ê'=>'e','ë'=>'e',
            'ì'=>'i','í'=>'i','î'=>'i','ï'=>'i',
            'ñ'=>'n',
            'ò'=>'o','ó'=>'o','ô'=>'o','ö'=>'o','õ'=>'o',
            'ù'=>'u','ú'=>'u','û'=>'u','ü'=>'u',
            'ý'=>'y','ÿ'=>'y',
            'œ'=>'oe','æ'=>'ae',
        ]);

        $value = preg_replace('/[^a-z0-9]+/u', ' ', $value) ?: '';

        return preg_replace('/\s+/u', ' ', trim($value)) ?: '';
    }

    private function sharedSignificantTokens(string $left, string $right): int
    {
        return count(array_intersect($this->tokens($left), $this->tokens($right)));
    }

    private function tokens(string $value): array
    {
        $stop = [
            'pour','avec','dans','sans','sur','des','les','une','the','and','for',
            'norme','standard','guide','aide','choix','document','edition','partie',
        ];

        $tokens = preg_split('/\s+/', $value) ?: [];

        return array_values(array_unique(array_filter(
            $tokens,
            static fn (string $token): bool => mb_strlen($token) >= 4 && ! in_array($token, $stop, true)
        )));
    }
}
