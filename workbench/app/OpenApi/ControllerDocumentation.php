<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class ControllerDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('public')
            ->title('Public API')
            ->description('Generated from documentation class')
            ->version('2026.04.02')
            ->servers([
                'Production' => 'https://api.example.com',
                'Local' => 'http://localhost:8000',
            ])
            ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'));
    }
}
