<?php

return [
    'core' => [
        'minPhpVersion' => '8.3.0',
    ],

    'requirements' => array_values(array_filter([
        'openssl',
        'pdo',
        'pdo_mysql',
        'mbstring',
        'tokenizer',
        'xml',
        'ctype',
        'json',
        'fileinfo',
        'curl',
        extension_loaded('bcmath') ? 'bcmath' : null,
    ])),

    'permissions' => [
        'storage/' => '775',
        'storage/app/' => '775',
        'storage/framework/' => '775',
        'storage/logs/' => '775',
        'bootstrap/cache/' => '775',
        '.env' => '664',
    ],

    'license' => [
        'enabled' => env('INSTALLER_LICENSE_VALIDATION_ENABLED', false),
        'endpoint' => env('INSTALLER_LICENSE_VALIDATION_ENDPOINT'),
    ],
];
