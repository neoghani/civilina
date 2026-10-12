<?php

return [
    /*
     * 2K.5.0 = extraction structurelle prudente.
     * Aucune exigence n'est considérée comme validée automatiquement.
     */
    'enabled' => env('CIVILINA_NORMATIVE_DEEP_ENABLED', true),

    'pdftotext' => env('CIVILINA_DEEP_PDFTOTEXT', 'pdftotext'),

    'max_chars' => (int) env('CIVILINA_DEEP_MAX_CHARS', 2500000),

    /*
     * Mots indicateurs d'exigence.
     * Le résultat reste un CANDIDATE_REQUIREMENT à valider humainement.
     */
    'requirement_markers' => [
        'doit', 'doivent', 'devra', 'devront', 'il convient de',
        'shall', 'must', 'should',
        'est requis', 'sont requis', 'ne doit pas', 'ne doivent pas',
    ],

    'unit_patterns' => [
        'mm','cm','m','m2','m²','m3','m³','mpa','n/mm2','n/mm²',
        'kn','n','%','°c','c','h','heure','heures','jour','jours',
        'min','max','kg','kg/m3','kg/m³',
    ],

    'report_path' => env(
        'CIVILINA_DEEP_REPORT_PATH',
        'D:\\Civilina_Normes_Classees\\_CIVILINA_DEEP'
    ),
];
