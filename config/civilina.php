<?php

return [
    'reasoning_runtime' => env('CIVILINA_REASONING_RUNTIME', 'client'),

    // E-mails of Civilina platform staff allowed to manage the shared
    // normative corpus (comma separated). Empty = nobody.
    'platform_staff' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CIVILINA_PLATFORM_STAFF', ''))
    ))),
];
