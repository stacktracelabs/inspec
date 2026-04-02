<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class AmbiguousWebhookDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('ambiguous-webhooks')
            ->post(
                '/webhooks/ambiguous',
                tags: 'Webhooks',
                summary: 'Ambiguous webhook route',
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
