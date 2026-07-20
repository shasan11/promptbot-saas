<?php

$localOrigins = [
    'http://localhost',
    'http://localhost:8000',
    'http://localhost:5173',
    'http://127.0.0.1:8000',
    'http://127.0.0.1:5173',
];

$origins = env('CORS_ALLOWED_ORIGINS')
    ? explode(',', env('CORS_ALLOWED_ORIGINS'))
    : (env('APP_ENV') === 'local' ? $localOrigins : [env('APP_URL', 'http://localhost')]);

$originPatterns = env('CORS_ALLOWED_ORIGINS_PATTERNS')
    ? explode(',', env('CORS_ALLOWED_ORIGINS_PATTERNS'))
    : [];

return [
    'paths' => ['*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => array_values(array_filter(array_map('trim', $origins))),

    'allowed_origins_patterns' => array_values(array_filter(array_map('trim', $originPatterns))),

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => true,
];
