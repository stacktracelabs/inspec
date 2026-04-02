<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Generated OpenAPI files are written into this directory by default.
    | Each configured API uses its configuration key as the output filename
    | unless a custom output path is defined for that API.
    |
    */
    'output' => 'openapi',

    /*
    |--------------------------------------------------------------------------
    | APIs
    |--------------------------------------------------------------------------
    |
    | Configure one or more separate APIs. Each API generates its own
    | OpenAPI file, allowing applications to publish independent specs for
    | different controller trees.
    |
    */
    'apis' => [
        'api' => [
            'title' => config('app.name', 'Laravel'),
            'description' => '',
            'version' => '1.0.0',
            'servers' => [
                'Local' => config('app.url', 'http://localhost'),
            ],
            'paths' => [
                app_path('Http/Controllers/Api'),
            ],
            // 'output' => 'openapi/public-api.yaml',
        ],
    ],
];
