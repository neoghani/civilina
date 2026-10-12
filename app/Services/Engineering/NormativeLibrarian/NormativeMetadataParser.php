<?php

namespace App\Services\Engineering\NormativeLibrarian;

use Illuminate\Support\Str;

class NormativeMetadataParser
{
    public function parse(string $filename, string $text): array
    {
        $text = $this->normalizeText($text);
        $search = $filename."\n".$text;

        $reference = $this->reference($search);
        [$month, $year] = $this->date($search);
        $category = $this->category($text);
        $title = $this->title($text, $category);
        $subtitle = $this->subtitle($text);
        $classificationIndex = $this->classificationIndex($search);
        $ics = $this->ics($search);
        $issuer = $this->issuer($reference, $search);

        [$discipline, $domain, $subdomain] = $this->technicalClassification(
            $reference,
            $category,
            $title,
            $subtitle,
            $search
        );

        $confidence = 0;
        if ($reference) $confidence += 30;
        if ($title) $confidence += 20;
        if ($year) $confidence += 10;
        if ($category) $confidence += 10;
        if ($issuer) $confidence += 10;
        if ($discipline && $domain) $confidence += 10;
        if ($classificationIndex) $confidence += 5;
        if ($ics) $confidence += 5;

        /*
         * IMPORTANT:
         * Ce service doit rester testable hors du container Laravel.
         * Les premiers tests Block 2K.3 chargeaient seulement vendor/autoload.php,
         * donc l'appel direct à config() provoquait :
         * Target class [config] does not exist.
         *
         * On prend les valeurs Laravel si le container/config est disponible,
         * sinon les valeurs sûres par défaut.
         */
        $auto = $this->configInt('civilina_normative_librarian.auto_classify_threshold', 80);
        $review = $this->configInt('civilina_normative_librarian.review_threshold', 55);

        $status = $confidence >= $auto
            ? 'AUTO_CLASSIFIED'
            : ($confidence >= $review ? 'TO_REVIEW' : 'UNCLASSIFIED');

        return [
            'reference_code' => $reference,
            'reference_normalized' => $reference
                ? preg_replace('/[^A-Z0-9]/', '', Str::upper(Str::ascii($reference)))
                : null,
            'issuer' => $issuer,
            'category_label' => $category,
            'title' => $title,
            'subtitle' => $subtitle,
            'publication_month' => $month,
            'publication_year' => $year,
            'classification_index' => $classificationIndex,
            'ics_codes' => $ics,
            'discipline' => $discipline,
            'domain' => $domain,
            'subdomain' => $subdomain,
            'confidence' => min(100, $confidence),
            'classification_status' => $status,
        ];
    }

    private function configInt(string $key, int $default): int
    {
        try {
            if (function_exists('app')) {
                $app = app();

                if ($app && method_exists($app, 'bound') && $app->bound('config')) {
                    $value = $app->make('config')->get($key, $default);

                    return is_numeric($value) ? (int) $value : $default;
                }
            }
        } catch (\Throwable) {
            // Test unitaire isolé : on garde la valeur par défaut.
        }

        return $default;
    }

    private function reference(string $value): ?string
    {
        $ascii = Str::upper(Str::ascii($value));

        $patterns = [
            '/\bNF\s*DTU\s*\d+(?:\.\d+)?(?:\s*P\d+(?:-\d+)*)?\b/u',
            '/\bDTU\s*\d+(?:\.\d+)?(?:\s*P\d+(?:-\d+)*)?\b/u',
            '/\bNF\s*EN\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*EN\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bEN\s*ISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bEN\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bISO\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bNF\s*P\s*\d{2}-\d{3}(?:-\d+){0,3}\b/u',
            '/\bNF\s*\d{2,6}(?:-\d+){0,4}\b/u',
            '/\bXP\s*P\s*\d{1,2}[-\s]*\d{3}(?:-\d+){0,3}\b/u',
            '/\bP\s*\d{1,2}[-\s]*\d{3}(?:-\d+){0,3}\b/u',
            '/\bGTR(?:\s*(?:19|20)\d{2})?\b/u',
            '/\bCFTR\s*\d{1,3}\b/u',
            '/\bDTR\s*[A-Z0-9.\-\/]+\b/u',
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $ascii, $m)) {
                return $this->canonicalReference($m[0]);
            }
        }

        return null;
    }

    private function canonicalReference(string $value): string
    {
        $value = Str::upper(Str::ascii(trim($value)));
        $value = preg_replace('/\s+/', ' ', $value);
        $value = preg_replace('/^NF\s*DTU\s*/', 'NF DTU ', $value);
        $value = preg_replace('/^DTU\s*/', 'DTU ', $value);
        $value = preg_replace('/^NF\s*EN\s*ISO\s*/', 'NF EN ISO ', $value);
        $value = preg_replace('/^NF\s*EN\s*/', 'NF EN ', $value);
        $value = preg_replace('/^NF\s*ISO\s*/', 'NF ISO ', $value);
        $value = preg_replace('/^EN\s*ISO\s*/', 'EN ISO ', $value);
        $value = preg_replace('/^EN\s*/', 'EN ', $value);
        $value = preg_replace('/^ISO\s*/', 'ISO ', $value);
        $value = preg_replace('/^NF\s*P\s*/', 'NF P ', $value);
        $value = preg_replace('/^NF\s*/', 'NF ', $value);
        $value = preg_replace('/^XP\s*P\s*/', 'XP P ', $value);
        $value = preg_replace('/^P\s*/', 'P ', $value);
        $value = preg_replace('/^GTR\s*/', 'GTR ', $value);
        $value = preg_replace('/^CFTR\s*/', 'CFTR ', $value);
        $value = preg_replace('/^DTR\s*/', 'DTR ', $value);
        $value = preg_replace('/\s*-\s*/', '-', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }

    private function date(string $value): array
    {
        $months = [
            'janvier' => 'Janvier',
            'fevrier' => 'Février',
            'mars' => 'Mars',
            'avril' => 'Avril',
            'mai' => 'Mai',
            'juin' => 'Juin',
            'juillet' => 'Juillet',
            'aout' => 'Août',
            'septembre' => 'Septembre',
            'octobre' => 'Octobre',
            'novembre' => 'Novembre',
            'decembre' => 'Décembre',
        ];

        $ascii = Str::lower(Str::ascii($value));

        if (preg_match(
            '/\b(janvier|fevrier|mars|avril|mai|juin|juillet|aout|septembre|octobre|novembre|decembre)\s+((?:19|20)\d{2})\b/u',
            $ascii,
            $m
        )) {
            return [$months[$m[1]] ?? ucfirst($m[1]), (int) $m[2]];
        }

        if (preg_match('/\b((?:19|20)\d{2})\b/u', $ascii, $m)) {
            return [null, (int) $m[1]];
        }

        return [null, null];
    }

    private function category(string $text): ?string
    {
        $known = [
            'Travaux de bâtiment',
            'Travaux publics',
            'Bâtiment et génie civil',
            'Routes et chaussées',
            'Management de la qualité',
            'Construction métallique',
            'Béton',
            'Soudage',
        ];

        $haystack = Str::lower(Str::ascii($text));

        foreach ($known as $candidate) {
            if (str_contains($haystack, Str::lower(Str::ascii($candidate)))) {
                return $candidate;
            }
        }

        return null;
    }

    private function title(string $text, ?string $category): ?string
    {
        $lines = $this->lines($text);
        $start = null;

        if ($category) {
            foreach ($lines as $i => $line) {
                if (Str::lower(Str::ascii($line)) === Str::lower(Str::ascii($category))) {
                    $start = $i + 1;
                    break;
                }
            }
        }

        if ($start === null) {
            foreach ($lines as $i => $line) {
                if (preg_match('/^(NF\s+DTU|DTU|NF\s+EN|NF\s+ISO|EN\s+|ISO\s+)/iu', $line)) {
                    $start = $i + 1;
                    break;
                }
            }
        }

        if ($start === null) {
            return null;
        }

        $parts = [];

        for ($i = $start; $i < min(count($lines), $start + 8); $i++) {
            $line = trim($lines[$i]);

            if ($line === '') continue;

            if (preg_match('/^(Partie|Part|Teil|E\s*:|D\s*:|Norme française|Indice de classement|ICS\s*:)/iu', $line)) {
                break;
            }

            if (preg_match(
                '/^(Octobre|Janvier|Février|Fevrier|Mars|Avril|Mai|Juin|Juillet|Août|Aout|Septembre|Novembre|Décembre|Decembre)\s+\d{4}$/iu',
                $line
            )) {
                continue;
            }

            if (mb_strlen($line) < 5 || mb_strlen($line) > 220) continue;

            $parts[] = $line;

            if (count($parts) >= 3) break;
        }

        $title = trim(implode(' ', $parts));

        return $title !== '' ? $title : null;
    }

    private function subtitle(string $text): ?string
    {
        foreach ($this->lines($text) as $line) {
            if (preg_match('/^Partie\s+[0-9A-Z.\-]+\s*:\s*(.+)$/iu', trim($line))) {
                return trim($line);
            }
        }

        return null;
    }

    private function classificationIndex(string $value): ?string
    {
        if (preg_match(
            '/Indice\s+de\s+classement\s*:\s*([A-Z]{1,4}\s*\d{1,3}(?:-\d+){1,5})/iu',
            $value,
            $m
        )) {
            return trim(preg_replace('/\s+/', ' ', $m[1]));
        }

        return null;
    }

    private function ics(string $value): array
    {
        if (! preg_match('/ICS\s*:\s*([0-9.\s;,]+)/iu', $value, $m)) {
            return [];
        }

        preg_match_all('/\d{2,3}\.\d{2,3}(?:\.\d{2,3})?/u', $m[1], $codes);

        return array_values(array_unique($codes[0] ?? []));
    }

    private function issuer(?string $reference, string $value): ?string
    {
        $upper = Str::upper(Str::ascii($value));

        if (str_contains($upper, 'AFNOR') || ($reference && str_starts_with($reference, 'NF '))) {
            return 'AFNOR';
        }

        if ($reference && preg_match('/(^|\s)ISO\s/u', $reference)) {
            return 'ISO';
        }

        if ($reference && preg_match('/(^|\s)EN\s/u', $reference)) {
            return 'CEN';
        }

        return null;
    }

    private function technicalClassification(
        ?string $reference,
        ?string $category,
        ?string $title,
        ?string $subtitle,
        string $fullText
    ): array {
        $text = Str::lower(Str::ascii(implode(' ', array_filter([
            $reference,
            $category,
            $title,
            $subtitle,
            $fullText,
        ]))));

        if (
            ($reference && preg_match('/^NF DTU 20\.1\b/i', $reference))
            || preg_match('/\b(maconnerie|parois et murs|petits elements)\b/u', $text)
        ) {
            return ['Bâtiment', 'CES', 'Maçonnerie'];
        }

        if (preg_match('/\b(etancheite|dtu 43|toiture terrasse)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Étanchéité'];
        }

        if (preg_match('/\b(platrerie|plaque de platre|knauf|cloison)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Plâtrerie / cloisons'];
        }

        if (preg_match('/\b(facade|bardage|enveloppe)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Façades / bardage'];
        }

        if (preg_match('/\b(carrelage|revetement|peinture|sol souple)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Revêtements / finitions'];
        }

        if (preg_match('/\b(plomberie|sanitaire|evacuation|alimentation eau)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Plomberie'];
        }

        if (preg_match('/\b(electricite|installation electrique|cable|courant)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'Électricité'];
        }

        if (preg_match('/\b(cvc|ventilation|climatisation|chauffage)\b/u', $text)) {
            return ['Bâtiment', 'CES', 'CVC'];
        }

        if (preg_match('/\b(beton|beton arme|ferraillage|armature|coffrage|enrobage|12350|12390|en 206)\b/u', $text)) {
            return ['Bâtiment / Génie civil', 'Gros œuvre', 'Béton / béton armé'];
        }

        if (preg_match('/\b(charpente|acier|assemblage boulonne|boulon|serrage|en 1090)\b/u', $text)) {
            return ['Bâtiment / Génie civil', 'Structure métallique', 'Charpente métallique'];
        }

        if (preg_match('/\b(soudage|soudure|welding|iso 9606|iso 15614)\b/u', $text)) {
            return ['Bâtiment / Génie civil', 'Structure métallique', 'Soudage'];
        }

        if (preg_match('/\b(terrassement|remblai|compactage|troxler|proctor|portance|gtr)\b/u', $text)) {
            return ['Infrastructure', 'Routes & terrassements', 'Terrassements / compactage'];
        }

        if (preg_match('/\b(chaussee|bitume|enrobe|deflexion|cftr)\b/u', $text)) {
            return ['Infrastructure', 'Routes & terrassements', 'Chaussées / enrobés'];
        }

        if (preg_match('/\b(iso 9001|iso 10005|management de la qualite)\b/u', $text)) {
            return ['Management', 'Qualité', 'Système de management'];
        }

        if (preg_match('/\b(metrologie|etalonnage|laboratoire|echantillonnage)\b/u', $text)) {
            return ['Essais & laboratoire', 'Contrôle / essais', 'Métrologie / laboratoire'];
        }

        return [null, null, null];
    }

    private function lines(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [];

        return array_values(array_filter(
            array_map(fn ($line) => trim(preg_replace('/\s+/', ' ', $line)), $lines),
            fn ($line) => $line !== ''
        ));
    }

    private function normalizeText(string $text): string
    {
        $text = str_replace("\0", '', $text);
        $text = preg_replace('/[ \t]+/u', ' ', $text);
        $text = preg_replace('/\R{3,}/u', "\n\n", $text);

        return trim((string) $text);
    }
}
