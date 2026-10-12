<?php

return [
    'enabled' => env('CIVILINA_NORMATIVE_OCR_ENABLED', true),

    'tesseract' => env('CIVILINA_OCR_TESSERACT', 'tesseract'),
    'pdftoppm' => env('CIVILINA_OCR_PDFTOPPM', 'pdftoppm'),

    /*
     * Le Block 2K.4 vise d'abord la récupération de référence/métadonnées.
     * Deux pages suffisent généralement pour la couverture normative.
     * Le futur moteur d'exigences fera un OCR documentaire plus profond.
     */
    'default_max_pages' => (int) env('CIVILINA_OCR_MAX_PAGES', 2),
    'dpi' => (int) env('CIVILINA_OCR_DPI', 220),
    'minimum_chars' => (int) env('CIVILINA_OCR_MIN_CHARS', 120),

    'preferred_languages' => [
        'fra+eng',
        'fra',
        'eng',
    ],

    'render_timeout_seconds' => 180,
    'ocr_timeout_seconds' => 120,

    'temp_path' => storage_path('app/private/engineering/ocr_tmp'),
    'report_path' => env(
        'CIVILINA_OCR_REPORT_PATH',
        'D:\\Civilina_Normes_Classees\\_CIVILINA_OCR\\runs'
    ),
];
