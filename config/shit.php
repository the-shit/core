<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Component Paths
    |--------------------------------------------------------------------------
    |
    | These paths tell THE SHIT where to look for components. They are
    | checked in order, with local taking priority over user, and user
    | taking priority over system.
    |
    */
    'paths' => [
        'components' => [
            'local' => env('SHIT_LOCAL_COMPONENTS', base_path('💩-components')),
            'user' => env('SHIT_USER_COMPONENTS', $_SERVER['HOME'] . '/.shit/components'),
            'system' => env('SHIT_SYSTEM_COMPONENTS', '/usr/local/share/shit/components'),
        ],
        'sites' => env('SHIT_SITES_PATH', $_SERVER['HOME'] . '/Sites'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Locations
    |--------------------------------------------------------------------------
    |
    | Where to install/scaffold components by default when no --global flag
    | is provided. Options: 'local', 'user', 'system'
    |
    */
    'defaults' => [
        'component_install' => env('SHIT_DEFAULT_INSTALL', 'local'),
        'component_scaffold' => env('SHIT_DEFAULT_SCAFFOLD', 'local'),
    ],
];