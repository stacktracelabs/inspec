<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class NamelessDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'));
    }
}
