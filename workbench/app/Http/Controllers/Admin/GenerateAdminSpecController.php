<?php

namespace Workbench\App\Http\Controllers\Admin;

use StackTrace\Inspec\Route;

class GenerateAdminSpecController
{
    #[Route(
        tags: 'Admin',
        summary: 'Generate admin spec fixture',
        response: [
            'status:string' => 'Fixture status value',
            'scope:string' => 'Fixture scope value',
        ],
    )]
    public function __invoke(): array
    {
        return [
            'status' => 'ok',
            'scope' => 'admin',
        ];
    }
}
