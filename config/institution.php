<?php

$allowsSyntheticDefaults = in_array(env('APP_ENV', 'production'), ['local', 'testing'], true)
    || filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOL);

return [
    'organization' => [
        'code' => env('INSTITUTION_ORGANIZATION_CODE', $allowsSyntheticDefaults ? 'org-demonstracao-ficticia' : null),
        'name' => env('INSTITUTION_ORGANIZATION_NAME', $allowsSyntheticDefaults ? 'Organização de Demonstração Fictícia' : null),
    ],

    'unit' => [
        'code' => env('INSTITUTION_UNIT_CODE', $allowsSyntheticDefaults ? 'unidade-demonstracao-ficticia' : null),
        'name' => env('INSTITUTION_UNIT_NAME', $allowsSyntheticDefaults ? 'Unidade de Demonstração Fictícia' : null),
    ],

    'production_confirmed' => filter_var(env('INSTITUTION_CONTEXT_PRODUCTION_CONFIRMED', false), FILTER_VALIDATE_BOOL),
    'synthetic_legacy_demo' => filter_var(env('VERCEL', false), FILTER_VALIDATE_BOOL),
];
