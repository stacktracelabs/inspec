<?php


namespace Workbench\App\OpenApi;


use StackTrace\Inspec\Api;
use StackTrace\Inspec\Documentation;

class DuplicatePublicDocumentation extends Documentation
{
    public function build(Api $api): void
    {
        $api
            ->name('public')
            ->title('Duplicate Public API')
            ->post(
                '/webhooks',
                tags: 'Webhooks',
                summary: 'Duplicate public webhook endpoint',
                response: [
                    'status:string' => 'Delivery status',
                ],
            );
    }
}
