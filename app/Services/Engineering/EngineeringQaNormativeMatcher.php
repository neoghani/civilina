<?php

namespace App\Services\Engineering;

class EngineeringQaNormativeMatcher
{
    /**
     * Lexique de recherche métier.
     *
     * Il ne contient aucune valeur d'acceptation : il sert uniquement
     * à retrouver les clauses potentiellement pertinentes.
     */
    private const WORK_TYPE_TERMS = [
        'reinforced_concrete' => [
            'béton', 'beton', 'concrete', 'armature', 'reinforcement', 'rebar',
            'ferraillage', 'coffrage', 'formwork', 'enrobage', 'cover',
            'ancrage', 'anchorage', 'recouvrement', 'lap', 'consistance',
            'slump', 'éprouvette', 'eprouvette', 'specimen', 'compression',
            'résistance', 'resistance', 'curing', 'cure',
        ],
        'concrete' => [
            'béton', 'beton', 'concrete', 'consistance', 'slump',
            'éprouvette', 'eprouvette', 'specimen', 'compression',
            'résistance', 'resistance', 'cure', 'curing',
        ],
        'steel_structure' => [
            'acier', 'steel', 'charpente', 'structure métallique', 'metallic',
            'poteau', 'column', 'poutre', 'beam', 'solive', 'joist',
            'lierne', 'contreventement', 'bracing', 'boulon', 'bolt',
            'serrage', 'tightening', 'couple', 'torque', 'précontrainte',
            'preload', 'certificat matière', 'material certificate',
            'traçabilité', 'traceability', 'montage', 'erection',
        ],
        'mixed_structure' => [
            'béton', 'concrete', 'acier', 'steel', 'ancrage', 'anchor',
            'platine', 'base plate', 'handover', 'interface', 'tolérance',
            'tolerance', 'axe', 'level', 'niveau',
        ],
        'earthworks' => [
            'terrassement', 'earthworks', 'sol', 'soil', 'remblai', 'fill',
            'compactage', 'compaction', 'densité', 'density', 'proctor',
            'teneur en eau', 'moisture', 'portance', 'bearing', 'plaque',
            'plate load', 'troxler', 'granulométrie', 'gradation',
        ],
        'road' => [
            'route', 'road', 'chaussée', 'pavement', 'compactage', 'compaction',
            'densité', 'density', 'plaque', 'plate load', 'troxler',
            'déflexion', 'deflection', 'portance', 'bearing', 'couche',
            'layer', 'granulométrie', 'gradation', 'épaisseur', 'thickness',
        ],
    ];

    private const TITLE_HINTS = [
        'ferraillage' => ['armature', 'reinforcement', 'rebar', 'enrobage', 'cover', 'ancrage', 'anchorage', 'recouvrement', 'lap'],
        'coffrage' => ['formwork', 'coffrage', 'dimension', 'tolérance', 'tolerance'],
        'béton frais' => ['fresh concrete', 'béton frais', 'consistance', 'slump', 'température', 'temperature', 'sampling', 'prélèvement'],
        'beton frais' => ['fresh concrete', 'béton frais', 'consistance', 'slump', 'température', 'temperature', 'sampling', 'prélèvement'],
        '7 jours' => ['7 day', '7 days', 'compression', 'compressive strength', 'specimen', 'éprouvette'],
        '28 jours' => ['28 day', '28 days', 'compression', 'compressive strength', 'specimen', 'éprouvette'],
        'réception' => ['reception', 'receiving', 'inspection', 'identification', 'traceability', 'traçabilité'],
        'serrage' => ['tightening', 'torque', 'couple', 'bolt', 'boulon', 'preload', 'précontrainte'],
        'montage' => ['erection', 'montage', 'alignment', 'alignement', 'verticality', 'verticalité', 'geometry', 'géométrie'],
        'troxler' => ['nuclear density', 'troxler', 'density', 'densité', 'moisture', 'teneur en eau'],
        'plaque' => ['plate load', 'plaque', 'bearing', 'portance', 'deformation', 'déformation'],
        'déflexion' => ['deflection', 'déflexion', 'pavement', 'chaussée'],
        'deflexion' => ['deflection', 'déflexion', 'pavement', 'chaussée'],
    ];

    public function keywords(
        string $workType,
        string $title,
        ?string $criteria = null,
        ?string $source = null
    ): array {
        $terms = self::WORK_TYPE_TERMS[$workType] ?? [];

        $normalizedTitle = $this->normalize($title);
        foreach (self::TITLE_HINTS as $needle => $hints) {
            if (str_contains($normalizedTitle, $this->normalize($needle))) {
                $terms = array_merge($hints, $terms);
            }
        }

        $terms = array_merge($this->significantTokens($title), $terms);

        if ($criteria) {
            $terms = array_merge($terms, $this->significantTokens($criteria));
        }
        if ($source) {
            $terms = array_merge($terms, $this->significantTokens($source));
        }

        $unique = [];
        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }
            $key = $this->normalize($term);
            if (mb_strlen($key) < 3 || isset($unique[$key])) {
                continue;
            }
            $unique[$key] = $term;
        }

        return array_slice(array_values($unique), 0, 40);
    }

    public function score(string $text, array $keywords): array
    {
        $haystack = $this->normalize($text);
        $matched = [];
        $points = 0.0;

        foreach ($keywords as $index => $keyword) {
            $needle = $this->normalize((string) $keyword);
            if ($needle === '' || ! str_contains($haystack, $needle)) {
                continue;
            }

            $matched[] = $keyword;

            // Les premiers termes sont les plus spécifiques au contrôle.
            $weight = $index < 6 ? 12.0 : ($index < 15 ? 6.0 : 2.5);
            if (str_contains($needle, ' ')) {
                $weight += 3.0;
            }
            $points += $weight;
        }

        $score = min(100.0, round($points, 2));

        return [
            'score' => $score,
            'matched' => array_values(array_unique($matched)),
            'reason' => $matched === []
                ? 'Aucun terme métier significatif retrouvé.'
                : 'Termes retrouvés : '.implode(', ', array_slice(array_values(array_unique($matched)), 0, 10)),
        ];
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

        return preg_replace('/\s+/u', ' ', trim($value)) ?: '';
    }

    private function significantTokens(string $value): array
    {
        $normalized = $this->normalize($value);
        $tokens = preg_split('/[^\pL\pN\-\/]+/u', $normalized) ?: [];

        $stop = [
            'avec','dans','pour','sans','entre','apres','avant','selon','controle',
            'inspection','essai','test','the','and','with','from','into','quality',
            'projet','project','travaux','work','works','civilina',
        ];

        return array_values(array_filter(array_unique($tokens), static function (string $token) use ($stop): bool {
            return mb_strlen($token) >= 4 && ! in_array($token, $stop, true);
        }));
    }
}
