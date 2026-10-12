<?php

return [
    'library_path' => env(
        'CIVILINA_NORMATIVE_LIBRARY_PATH',
        storage_path('app/private/engineering/standards_inbox')
    ),
    'scan_extensions' => ['pdf', 'docx', 'txt', 'md'],
    'cover_pages' => 4,
    'auto_classify_threshold' => 80,
    'review_threshold' => 55,
];
