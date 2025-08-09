<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Conduit Configuration
    |--------------------------------------------------------------------------
    |
    | Configuration for THE SHIT's Human-AI collaboration features.
    |
    */

    /*
    |--------------------------------------------------------------------------
    | User Agent Detection
    |--------------------------------------------------------------------------
    |
    | Identifies who is using the CLI (human, claude, ai, ci).
    | This affects output formatting and interaction modes.
    |
    */
    'user_agent' => env('CONDUIT_USER_AGENT', 'human'),

    /*
    |--------------------------------------------------------------------------
    | Component Configuration
    |--------------------------------------------------------------------------
    |
    | Default settings for component scaffolding and management.
    |
    */
    'components' => [
        'github_username' => env('CONDUIT_GITHUB_USERNAME', 'S-H-I-T'),
        'namespace' => env('CONDUIT_NAMESPACE', 'App'),
        'author_name' => env('CONDUIT_AUTHOR_NAME', 'Your Name'),
        'author_email' => env('CONDUIT_AUTHOR_EMAIL', 'you@example.com'),
        'directory' => env('CONDUIT_COMPONENTS_DIR', '💩-components'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Detection Settings
    |--------------------------------------------------------------------------
    |
    | Configuration for smart detection features.
    |
    */
    'detection' => [
        'os_name' => env('CONDUIT_OS_NAME', PHP_OS),
    ],
];
