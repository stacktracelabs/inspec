<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class FilteredRoutesDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('filtered-routes')
            ->title('Filtered Routes API')
            ->prefix('api')
            ->withoutBroadcasting()
            ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
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
            )
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
