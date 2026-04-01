<?php

namespace Workbench\App\Http\Controllers\Api;

use StackTrace\Inspec\Route;

class GenerateSpecController
{
    #[Route(
        tags: 'Utilities',
        summary: 'Generate spec fixture',
        response: [
            'status:string' => 'Fixture status value',
        ],
    )]
    public function __invoke(): array
    {
        return [
            'status' => 'ok',
        ];
    }
}
