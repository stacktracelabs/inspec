<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class WithoutSanctumDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('without-sanctum')
            ->title('Without Sanctum API')
            ->withoutSanctum()
            ->withoutBroadcasting()
            ->post(
                '/webhooks',
                tags: 'Webhooks',
                summary: 'Receive webhooks without sanctum docs',
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
