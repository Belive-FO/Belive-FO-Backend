<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Module Configuration
    |--------------------------------------------------------------------------
    |
    | This file contains configuration for the modular monolith structure.
    | Each module should be self-contained and communicate through interfaces.
    |
    */

    'modules' => [
        'attendance' => [
            'enabled' => true,
            'namespace' => 'App\Modules\Attendance',
        ],
        'leave' => [
            'enabled' => true,
            'namespace' => 'App\Modules\Leave',
        ],
        'claims' => [
            'enabled' => true,
            'namespace' => 'App\Modules\Claims',
        ],
    ],
];


