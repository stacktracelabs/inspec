<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class MissingNamedRouteDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('missing-named-webhook')
            ->route(
                'webhooks.missing',
                tags: 'Webhooks',
                summary: 'Missing named webhook route',
                response: [
                    'status:string' => 'Webhook response status',
                ],
            );
    }
}
