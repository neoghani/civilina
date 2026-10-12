<?php

return [
    'inbox' => env(
        'CIVILINA_NORMATIVE_INBOX',
        storage_path('app/private/engineering/standards_inbox')
    ),

    'raw_text_dir' => env(
        'CIVILINA_NORMATIVE_TEXT_DIR',
        storage_path('app/private/engineering/normative_text')
    ),

    'max_source_bytes' => (int) env('CIVILINA_NORMATIVE_MAX_SOURCE_BYTES', 25 * 1024 * 1024),

    'match_limit' => (int) env('CIVILINA_NORMATIVE_MATCH_LIMIT', 6),

    'min_match_score' => (float) env('CIVILINA_NORMATIVE_MIN_MATCH_SCORE', 28),

    /*
     * L'agent ne doit jamais inventer une norme, une section ou une tolérance.
     * Ces familles servent uniquement à filtrer/reclasser la bibliothèque.
     */
    'domain_keywords' => [
        'concrete' => [
            'beton', 'béton', 'ciment', 'coffrage', 'ferraillage', 'armature',
            'eprouvette', 'éprouvette', 'resistance', 'résistance', 'affaissement',
            'consistance', 'granulat', 'adjuvant', 'enrobage',
        ],
        'steel' => [
            'acier', 'charpente', 'metallique', 'métallique', 'boulon', 'serrage',
            'soudage', 'soudure', 'poteau', 'poutre', 'solive', 'lierne',
            'contreventement', 'platine', 'couple',
        ],
        'earthworks_road' => [
            'terrassement', 'remblai', 'cdf', 'sol', 'route', 'chaussée',
            'chaussee', 'compactage', 'compacite', 'compacité', 'troxler',
            'plaque', 'portance', 'deflexion', 'déflexion', 'proctor',
            'granulometrie', 'granulométrie', 'teneur en eau',
        ],
        'metrology' => [
            'metrologie', 'métrologie', 'etalonnage', 'étalonnage',
            'verification', 'vérification', 'thermometre', 'thermomètre',
        ],
    ],
];
