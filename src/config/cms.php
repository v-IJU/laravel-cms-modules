<?php

return [

    /*
     * Main configurations
     */
    'path' => 'cms',

    /*
     * Installation
     */
    'installed'        => false,
    'tenancy_enabled'  => false,
    'install_mode'     => 'normal', // normal or tenancy

    /*
     * Modules configuration
     */
    'module' => [
        'path'          => 'local',
        'configuration' => 'module.json',
        'core_path'     => 'core',
        'local_path'    => 'local',
    ],

    /*
     * Theme
     */
    'theme' => [
        'active'    => 'theme1',
        'fall_back' => 'theme1',
    ],

    /*
     * Skin
     */
    'skin' => [
        'path' => public_path('skin'),
    ],

    /*
     * Tenancy (only used when tenancy_enabled = true)
     */
    'tenancy' => [
        'central_domains' => [
            env('APP_DOMAIN', 'localhost'),
            '127.0.0.1',
            'localhost',
        ],
        'tenant_db_prefix' => 'tenant_',
    ],

];
