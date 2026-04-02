<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class WithoutBroadcastingDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('without-broadcasting')
            ->title('Without Broadcasting API')
            ->prefix('api')
            ->withoutBroadcasting()
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
