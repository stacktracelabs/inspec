<?php

return [
    'title' => config('app.name', 'Laravel'),

    'description' => '',

    'version' => '1.0.0',

    'servers' => [
        'Local' => config('app.url', 'http://localhost'),
    ],

    'paths' => [
        app_path('Http/Controllers/Api'),
    ],

    'output' => 'openapi.yaml',
];
