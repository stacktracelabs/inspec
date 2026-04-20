<?php

namespace Workbench\App\Http\Controllers\Api;

use StackTrace\Inspec\Route;

class ReusedRouteController
{
    #[Route(
        tags: 'Utilities',
        summary: 'Document reused invokable route',
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

    #[Route(
        tags: 'Utilities',
        summary: 'Document reused method route',
        response: [
            'status:string' => 'Fixture status value',
        ],
    )]
    public function show(): array
    {
        return [
            'status' => 'ok',
        ];
    }
}
