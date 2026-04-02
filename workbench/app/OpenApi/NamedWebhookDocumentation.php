<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class NamedWebhookDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('named-webhooks')
            ->title('Named Webhook API')
            ->route(
                'webhooks.named',
                tags: 'Webhooks',
                summary: 'Named webhook route',
                response: [
                    'status:string' => 'Webhook response status',
                ],
            );
    }
}
