<?php

namespace App\Services\Engineering\Normative;

use Illuminate\Support\Str;

class NormativeReferenceNormalizer
{
    /**
     * @return array{code:?string, normalized:?string, family:?string, body:?string, year:?int}
     */
    public function detect(string $name, ?string $text = null): array
    {
        $haystack = $this->cleanup($name.' '.mb_substr((string) $text, 0, 6000));

        /*
         * Les archives normatives contiennent plusieurs Ã©critures historiques :
         * NF EN 459-1 / NF EN459-1 / NFEN459-1
         * EN 206-1 / EN206-1
         * NF 12390-3 / NF12390-3
         *
         * L'espace entre prÃ©fixe et numÃ©ro est donc optionnel Ã  la dÃ©tection.
         * canonicalize() produit ensuite une rÃ©fÃ©rence Civilina stable.
         */
        $patterns = [
            // RÃ©fÃ©rentiels majeurs avant les anciens codes de classement.
            '/\bGTR(?:\s*(?:19|20)\d{2})?\b/ui',
            '/\bCFTR\s*\d{1,3}\b/ui',

            '/\bNF\s*EN\s*\d{2,6}(?:-\d+){0,3}\b/ui',
            '/\bNF\s*ISO\s*\d{2,6}(?:-\d+){0,3}\b/ui',
            '/\bEN\s*\d{2,6}(?:-\d+){0,3}\b/ui',
            '/\bISO\s*\d{2,6}(?:-\d+){0,3}\b/ui',
            '/\bNF\s*\d{2,6}(?:-\d+){0,3}\b/ui',

            '/\bXP\s*P\s*\d{1,2}[-\s]*\d{3}(?:-\d+)?\b/ui',
            '/\bP\s*\d{1,2}[-\s]*\d{3}(?:-\d+)?\b/ui',
            '/\bX\s*\d{2}[-\s]*\d{3}(?:-\d+)?\b/ui',
            '/\bA\s*\d{2}[-\s]*\d{3}(?:-\d+)?\b/ui',
            '/\bE\s*\d{2}[-\s]*\d{3}(?:-\d+)?\b/ui',
            '/\bDTU\s*\d+(?:\.\d+)?\b/ui',
            '/\bDT\s*\d{3,5}\b/ui',
        ];

        $code = null;

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $haystack, $match)) {
                $code = $this->canonicalize($match[0]);
                break;
            }
        }

        $year = null;
        if (preg_match('/\b((?:19|20)\d{2})\b/u', $haystack, $yearMatch)) {
            $year = (int) $yearMatch[1];
        }

        $normalized = $code ? $this->normalizeCode($code) : null;
        $family = $normalized;

        if ($family) {
            // NF EN xxxx et EN xxxx sont reliÃ©es Ã  la mÃªme famille,
            // tout en restant deux rÃ©fÃ©rences/adoptions distinctes.
            $family = preg_replace('/^NFEN/', 'EN', $family);
            $family = preg_replace('/^NFISO/', 'ISO', $family);

            // L'annÃ©e appartient Ã  l'Ã©dition, pas Ã  l'identitÃ© de famille.
            $family = preg_replace('/(?:19|20)\d{2}$/', '', $family);
        }

        $body = null;
        if ($code) {
            $upper = Str::upper($code);

            foreach ([
                'NF EN',
                'NF ISO',
                'EN',
                'ISO',
                'NF',
                'XP P',
                'P',
                'X',
                'A',
                'E',
                'DTU',
                'DT',
                'GTR',
                'CFTR',
            ] as $candidate) {
                if (str_starts_with($upper, $candidate)) {
                    $body = $candidate;
                    break;
                }
            }
        }

        return [
            'code' => $code,
            'normalized' => $normalized,
            'family' => $family,
            'body' => $body,
            'year' => $year,
        ];
    }

    public function normalizeCode(string $code): string
    {
        $code = Str::upper(Str::ascii($code));

        return preg_replace('/[^A-Z0-9]/', '', $code) ?: '';
    }

    private function canonicalize(string $code): string
    {
        $code = Str::upper(Str::ascii(trim($code)));
        $code = preg_replace('/\s+/', ' ', $code);

        $code = preg_replace('/^NF\s*EN\s*/', 'NF EN ', $code);
        $code = preg_replace('/^NF\s*ISO\s*/', 'NF ISO ', $code);
        $code = preg_replace('/^XP\s*P\s*/', 'XP P ', $code);

        $code = preg_replace('/^EN\s*/', 'EN ', $code);
        $code = preg_replace('/^ISO\s*/', 'ISO ', $code);
        $code = preg_replace('/^NF\s*/', 'NF ', $code);
        $code = preg_replace('/^DTU\s*/', 'DTU ', $code);
        $code = preg_replace('/^DT\s*/', 'DT ', $code);
        $code = preg_replace('/^CFTR\s*/', 'CFTR ', $code);
        $code = preg_replace('/^GTR\s*/', 'GTR ', $code);

        $code = preg_replace('/^P\s*(\d{1,2})[\s-]*(\d{3}(?:-\d+)?)/', 'P $1-$2', $code);
        $code = preg_replace('/^X\s*(\d{2})[\s-]*(\d{3}(?:-\d+)?)/', 'X $1-$2', $code);
        $code = preg_replace('/^A\s*(\d{2})[\s-]*(\d{3}(?:-\d+)?)/', 'A $1-$2', $code);
        $code = preg_replace('/^E\s*(\d{2})[\s-]*(\d{3}(?:-\d+)?)/', 'E $1-$2', $code);
        $code = preg_replace('/^XP P\s*(\d{1,2})[\s-]*(\d{3}(?:-\d+)?)/', 'XP P $1-$2', $code);

        $code = preg_replace('/(?<=\d)\s*-\s*(?=\d)/', '-', $code);
        $code = preg_replace('/\s+/', ' ', $code);

        return trim($code);
    }

    private function cleanup(string $value): string
    {
        $value = str_replace(['_', '\\', '/', '...', '..'], ' ', $value);
        $value = preg_replace('/\s+/', ' ', $value);

        return trim($value);
    }
}