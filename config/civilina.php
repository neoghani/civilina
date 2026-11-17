<?php

return [
    'reasoning_runtime' => env('CIVILINA_REASONING_RUNTIME', 'client'),

    // E-mails of Civilina platform staff allowed to manage the shared
    // normative corpus (comma separated). Empty = nobody.
    'platform_staff' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('CIVILINA_PLATFORM_STAFF', ''))
    ))),

    // Requests per minute and per account (per IP before login).
    'rate_limits' => [
        'app' => (int) env('CIVILINA_RATE_LIMIT_APP', 300),
        'heavy' => (int) env('CIVILINA_RATE_LIMIT_HEAVY', 30),
    ],
];
