<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | Here you may configure your settings for cross-origin resource sharing
    | or "CORS". This determines what cross-origin operations may execute
    | in web browsers. You are free to adjust these settings as needed.
    |
    | To learn more: https://developer.mozilla.org/en-US/docs/Web/HTTP/CORS
    |
    */

    'paths' => ['api/*', 'sanctum/csrf-cookie'],

    'allowed_methods' => ['*'],

    'allowed_origins' => empty(env('ALLOWED_ORIGINS'))
        ? ['http://localhost:3000', 'http://localhost:5173']
        : collect(
            is_array($allowed = env('ALLOWED_ORIGINS'))
                ? $allowed
                : explode(',', (string) $allowed)
        )
            ->map(fn (string $origin) => trim($origin, " \t\n\r\0\x0B[]'\""))
            ->filter()
            ->unique()
            ->values()
            ->all(),

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
