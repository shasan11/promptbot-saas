<?php

return [
    'tenant_base_domain' => env('TENANT_BASE_DOMAIN', parse_url((string) env('APP_URL', 'http://localhost'), PHP_URL_HOST) ?: 'localhost'),
    'db_provisioning_mode' => env('TENANT_DB_PROVISIONING_MODE', 'manual'),
    'allow_database_deletion' => env('ALLOW_TENANT_DATABASE_DELETION', false),

    'cpanel' => [
        'host' => env('CPANEL_HOST'),
        'username' => env('CPANEL_USERNAME'),
        'api_token' => env('CPANEL_API_TOKEN'),
        'database_prefix' => env('CPANEL_DATABASE_PREFIX'),
        'database_user' => env('CPANEL_DATABASE_USER'),
        'verify_ssl' => env('CPANEL_VERIFY_SSL', true),
    ],

    'locks' => [
        'provisioning_ttl' => (int) env('TENANT_PROVISIONING_LOCK_TTL', 600),
    ],
];
