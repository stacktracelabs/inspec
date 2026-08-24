<?php

use Illuminate\Support\Facades\Route as LaravelRoute;
use StackTrace\Inspec\Api;
use StackTrace\Inspec\GeneratorException;
use StackTrace\Inspec\Operation;

test('it documents manually defined routes from an explicit operation', function () {
    $document = (new Api())
        ->name('operation-webhooks')
        ->withoutBroadcasting()
        ->post(
            '/webhooks',
            operation: (new Operation(tags: 'Webhooks'))
                ->summary('Receive webhooks via operation')
                ->headers([
                    'X-Webhook-Signature!:string' => 'Webhook signature',
                ])
                ->request([
                    'event:string' => 'Webhook event name',
                ])
                ->response([
                    'status:string' => 'Delivery status',
                ]),
        )
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/webhooks']['post']['summary'])->toBe('Receive webhooks via operation')
        ->and($document['paths']['/webhooks']['post']['parameters'])->toBe([
            [
                'in' => 'header',
                'name' => 'X-Webhook-Signature',
                'schema' => [
                    'type' => 'string',
                ],
                'description' => 'Webhook signature',
                'required' => true,
            ],
        ])
        ->and(array_keys($document['paths']['/webhooks']['post']['requestBody']['content']['application/json']['schema']['properties']))->toContain('event')
        ->and(array_keys($document['paths']['/webhooks']['post']['responses']['200']['content']['application/json']['schema']['properties']))->toContain('status')
        ->and($document['paths']['/webhooks']['post']['security'])->toBe([
            [
                'bearerAuth' => [],
            ],
        ]);
});

test('it propagates request headers from manual api helper arguments', function () {
    $document = (new Api())
        ->name('manual-request-headers')
        ->withoutBroadcasting()
        ->post(
            '/webhooks',
            summary: 'Receive webhook with headers',
            response: [
                'status:string' => 'Delivery status',
            ],
            headers: [
                'X-Delivery-Mode!:string|enum:sync,async' => 'Delivery mode',
            ],
        )
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/webhooks']['post']['parameters'])->toBe([
        [
            'in' => 'header',
            'name' => 'X-Delivery-Mode',
            'schema' => [
                'type' => 'string',
                'enum' => ['sync', 'async'],
            ],
            'description' => 'Delivery mode',
            'required' => true,
        ],
    ]);
});

test('it infers bearer auth from configured sanctum guards', function () {
    LaravelRoute::post('mobile-webhooks', fn () => [
        'status' => 'accepted',
    ])->middleware('auth:mobile');

    $document = (new Api())
        ->name('mobile-webhooks')
        ->withoutBroadcasting()
        ->withSanctumGuards('mobile')
        ->post(
            '/mobile-webhooks',
            summary: 'Receive mobile webhook',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/mobile-webhooks']['post']['security'])->toBe([
        [
            'bearerAuth' => [],
        ],
    ])->and($document['components']['securitySchemes']['bearerAuth'])->toBe([
        'type' => 'http',
        'scheme' => 'bearer',
    ]);
});

test('it only registers bearer auth when an included route matches a configured sanctum guard', function () {
    $document = (new Api())
        ->name('unmatched-mobile-guard')
        ->withoutBroadcasting()
        ->withSanctumGuards('mobile')
        ->post(
            '/webhooks',
            summary: 'Receive standard webhook',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/webhooks']['post']['security'] ?? null)->toBeNull()
        ->and($document['components']['securitySchemes']['bearerAuth'] ?? null)->toBeNull();
});

test('it fails when operation is combined with named operation metadata arguments', function () {
    expect(fn () => (new Api())->post(
        '/webhooks',
        tags: 'Webhooks',
        operation: new Operation(summary: 'Receive webhooks via operation'),
    ))->toThrow(GeneratorException::class, 'The [operation] argument cannot be combined with other operation metadata arguments.');
});

test('it lets broadcasting auto-discovery customize operations via callback', function () {
    $document = (new Api())
        ->name('broadcasting-operation-customization')
        ->prefix('api')
        ->post(
            '/api/webhooks/prefixed',
            tags: 'Webhooks',
            summary: 'Receive prefixed webhooks',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->withBroadcasting(function (Operation $operation, \Illuminate\Routing\Route $route) {
            return $operation
                ->tags('Realtime')
                ->summary($route->uri() === 'api/broadcasting/auth' ? 'Custom broadcasting auth' : 'Custom broadcasting user auth')
                ->response([
                    'result:string' => 'Broadcasting result',
                ]);
        })
        ->toOpenAPI()
        ->build();

    expect($document['paths']['/broadcasting/auth']['post']['summary'])->toBe('Custom broadcasting auth')
        ->and($document['paths']['/broadcasting/user-auth']['post']['summary'])->toBe('Custom broadcasting user auth')
        ->and($document['paths']['/broadcasting/auth']['post']['tags'])->toBe(['Realtime'])
        ->and(array_keys($document['paths']['/broadcasting/auth']['post']['responses']['200']['content']['application/json']['schema']['properties']))->toContain('result');
});

test('it lets broadcasting auto-discovery skip routes via callback', function () {
    $document = (new Api())
        ->name('broadcasting-operation-skip')
        ->prefix('api')
        ->post(
            '/api/webhooks/prefixed',
            tags: 'Webhooks',
            summary: 'Receive prefixed webhooks',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->withBroadcasting(function (Operation $operation, \Illuminate\Routing\Route $route) {
            if ($route->uri() === 'api/broadcasting/user-auth') {
                return null;
            }

            return $operation;
        })
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toContain('/broadcasting/auth', '/webhooks/prefixed')
        ->and(array_keys($document['paths']))->not->toContain('/broadcasting/user-auth');
});
