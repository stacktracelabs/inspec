<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class MissingWebhookDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('missing-webhook')
            ->post(
                '/missing-webhook',
                tags: 'Webhooks',
                summary: 'Missing webhook route',
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
