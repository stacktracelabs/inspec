<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class BroadcastingDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('broadcasting')
            ->title('Broadcasting API')
            ->prefix('api')
            ->post(
                '/api/webhooks/prefixed',
                tags: 'Webhooks',
                summary: 'Receive prefixed webhooks',
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
