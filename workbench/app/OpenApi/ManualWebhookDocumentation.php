<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class ManualWebhookDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('webhooks')
            ->title('Webhook API')
            ->post(
                '/webhooks',
                tags: 'Webhooks',
                summary: 'Receive webhooks',
                request: [
                    'event:string' => 'Webhook event name',
                ],
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
