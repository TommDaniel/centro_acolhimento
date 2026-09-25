<?php

return [
    'guard' => 'web',
    'middleware' => ['web'],
    'auth_middleware' => 'auth',
    'username' => 'email',
    'email' => 'email',
    'views' => false,
    'home' => '/dashboard',
    'prefix' => '',
    'domain' => null,
    'lowercase_usernames' => true,
    'limiters' => [
        'login' => null,
        'passkeys' => null,
    ],
    'restricted_session_ttl_seconds' => env('MFA_RESTRICTED_TTL_SECONDS', 300),

    'verified_session_idle_ttl_seconds' => env('MFA_VERIFIED_IDLE_TTL_SECONDS', 900),

    'verified_session_absolute_ttl_seconds' => env('MFA_VERIFIED_ABSOLUTE_TTL_SECONDS', 28800),
    'features' => [],
];
