<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Generated OpenAPI files are written into this directory. Each
    | documentation class must define its own API name, which becomes the
    | generated filename: <name>.yaml.
    |
    */
    'output' => 'openapi',

    /*
    |--------------------------------------------------------------------------
    | Documentation Classes
    |--------------------------------------------------------------------------
    |
    | Each documentation class receives a fresh StackTrace\Inspec\Api
    | instance and is responsible for describing exactly one OpenAPI spec.
    |
    */
    'docs' => [
        // App\OpenApi\PublicApiDocumentation::class,
    ],
];
