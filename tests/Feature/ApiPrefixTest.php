<?php

use StackTrace\Inspec\Api;

test('it strips an explicit prefix from controller paths and preserves literal server urls', function () {
    $document = (new Api())
        ->name('public')
        ->prefix('/api/')
        ->withoutBroadcasting()
        ->servers([
            'Production' => 'https://api.example.com',
            'Local' => 'http://localhost:8000/api',
        ])
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    expect($document['servers'])->toBe([
        [
            'url' => 'https://api.example.com',
            'description' => 'Production',
        ],
        [
            'url' => 'http://localhost:8000/api',
            'description' => 'Local',
        ],
    ])
        ->and(array_keys($document['paths']))->toContain('/spec-test')
        ->and(array_keys($document['paths']))->not->toContain('/api/spec-test');
});

test('it keeps route prefixes when no api prefix is configured', function () {
    $document = (new Api())
        ->name('public')
        ->withoutBroadcasting()
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toContain('/api/spec-test')
        ->and(array_keys($document['paths']))->not->toContain('/spec-test');
});

test('it applies api path filters to canonical generated paths', function () {
    $document = (new Api())
        ->name('public')
        ->prefix('api')
        ->withoutBroadcasting()
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->filterPath('^/spec-test$')
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toBe(['/spec-test']);
});

test('it strips the configured prefix from manually documented routes while resolving the real laravel uri', function () {
    $document = (new Api())
        ->name('prefixed-webhooks')
        ->prefix('api')
        ->withoutBroadcasting()
        ->post(
            '/api/webhooks/prefixed',
            tags: 'Webhooks',
            summary: 'Receive prefixed webhooks',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/webhooks/prefixed']['post']['summary'])->toBe('Receive prefixed webhooks')
        ->and($document['paths']['/webhooks/prefixed']['post']['security'])->toBe([
            [
                'bearerAuth' => [],
            ],
        ]);
});

test('it strips prefixes only on full path segments', function () {
    $document = (new Api())
        ->name('segment-safe')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/apiary/example',
            tags: 'Utilities',
            summary: 'Segment-safe prefix example',
            response: [
                'status:string' => 'Example status',
            ],
        )
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toBe(['/apiary/example']);
});
