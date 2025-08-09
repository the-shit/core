<?php

return [
    /*
    |--------------------------------------------------------------------------
    | GitHub Authentication Configuration
    |--------------------------------------------------------------------------
    |
    | Configure how THE SHIT authenticates with GitHub API.
    | Supports multiple authentication methods:
    | - Personal Access Token (default)
    | - GitHub App
    | - OAuth App
    |
    */

    'auth_method' => env('GITHUB_AUTH_METHOD', 'token'), // token, app, oauth

    /*
    |--------------------------------------------------------------------------
    | Personal Access Token Authentication
    |--------------------------------------------------------------------------
    |
    | The simplest authentication method. Create a token at:
    | https://github.com/settings/tokens
    |
    */
    'token' => env('GITHUB_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | GitHub App Authentication
    |--------------------------------------------------------------------------
    |
    | For advanced use cases with higher rate limits.
    |
    */
    'app' => [
        'id' => env('GITHUB_APP_ID'),
        'private_key' => env('GITHUB_APP_PRIVATE_KEY'),
        'installation_id' => env('GITHUB_APP_INSTALLATION_ID'),
    ],

    /*
    |--------------------------------------------------------------------------
    | OAuth App Authentication
    |--------------------------------------------------------------------------
    |
    | For user-specific authentication flows.
    |
    */
    'oauth' => [
        'client_id' => env('GITHUB_CLIENT_ID'),
        'client_secret' => env('GITHUB_CLIENT_SECRET'),
    ],

    /*
    |--------------------------------------------------------------------------
    | API Configuration
    |--------------------------------------------------------------------------
    */
    'api' => [
        'base_url' => env('GITHUB_API_URL', 'https://api.github.com'),
        'version' => env('GITHUB_API_VERSION', 'v3'),
        'timeout' => env('GITHUB_API_TIMEOUT', 30),
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limit handling behavior.
    |
    */
    'rate_limit' => [
        'auto_retry' => env('GITHUB_AUTO_RETRY', true),
        'max_retries' => env('GITHUB_MAX_RETRIES', 3),
    ],
];
