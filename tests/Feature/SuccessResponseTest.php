<?php

use StackTrace\Inspec\Api;
use StackTrace\Inspec\SuccessResponse;
use Workbench\App\Transformers\UserTransformer;

class WrappedSuccessResponse extends SuccessResponse
{
    protected static function defaultDescription(): string
    {
        return 'Wrapped success response';
    }

    protected static function defaultContentType(): string
    {
        return 'application/vnd.api+json';
    }

    protected function buildBody(array $schema): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'payload' => $schema,
                'meta' => [
                    'type' => 'object',
                    'properties' => [
                        'wrapped' => [
                            'type' => 'boolean',
                        ],
                    ],
                ],
            ],
        ];
    }
}

test('it preserves the built in standard success response', function () {
    $document = (new Api())
        ->name('success')
        ->withoutBroadcasting()
        ->post(
            '/webhooks',
            tags: 'Webhooks',
            summary: 'Receive webhooks',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $response = $document['paths']['/webhooks']['post']['responses']['200'];

    expect($response['description'])->toBe('Successful response')
        ->and(array_keys($response['content']))->toBe(['application/json'])
        ->and(array_keys($response['content']['application/json']['schema']['properties']))->toContain('status');
});

test('it applies an api wide success response wrapper to manual routes', function () {
    $document = (new Api())
        ->name('wrapped-success')
        ->withoutBroadcasting()
        ->withSuccessResponse(
            (new WrappedSuccessResponse())
                ->withHeaders([
                    'x-trace-id:string' => 'Trace header',
                ]),
        )
        ->post(
            '/webhooks',
            tags: 'Webhooks',
            summary: 'Receive webhooks',
            response: [
                'status:string' => 'Delivery status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $response = $document['paths']['/webhooks']['post']['responses']['200'];
    $schema = $response['content']['application/vnd.api+json']['schema'];

    expect($response['description'])->toBe('Wrapped success response')
        ->and($response['headers']['x-trace-id']['schema']['type'])->toBe('string')
        ->and(array_keys($schema['properties']))->toBe(['payload', 'meta'])
        ->and(array_keys($schema['properties']['payload']['properties']))->toContain('status')
        ->and($schema['properties']['meta']['properties']['wrapped']['type'])->toBe('boolean');
});

test('it applies an api wide success response wrapper to controller discovered routes', function () {
    $document = (new Api())
        ->name('controller-success')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withSuccessResponse(new WrappedSuccessResponse())
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Api'))
        ->toOpenAPI()
        ->build();

    $response = $document['paths']['/spec-test']['get']['responses']['200'];
    $schema = $response['content']['application/vnd.api+json']['schema'];

    expect($response['description'])->toBe('Wrapped success response')
        ->and(array_keys($schema['properties']))->toBe(['payload', 'meta'])
        ->and(array_keys($schema['properties']['payload']['properties']))->toContain('status');
});

test('it keeps paginated responses and inferred error responses unaffected', function () {
    $document = (new Api())
        ->name('mixed-success')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withSuccessResponse(new WrappedSuccessResponse())
        ->post(
            '/api/error-responses/request',
            tags: 'Errors',
            summary: 'Submit request',
            request: [
                'email!:string' => 'Email address',
            ],
            response: [
                'status:string' => 'Submission status',
            ],
        )
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'List users',
            paginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $normalResponse = $document['paths']['/error-responses/request']['post']['responses'];
    $paginatedResponse = $document['paths']['/paginated-users']['get']['responses']['200'];

    expect(array_keys($normalResponse['200']['content']['application/vnd.api+json']['schema']['properties']))->toBe(['payload', 'meta'])
        ->and($normalResponse['422']['$ref'])->toBe('#/components/responses/ValidationErrorResponse')
        ->and($paginatedResponse['description'])->toBe('Successful response')
        ->and(array_keys($paginatedResponse['content']['application/json']['schema']['properties']))->toBe(['data', 'meta'])
        ->and($paginatedResponse['content']['application/json']['schema']['properties']['payload'] ?? null)->toBeNull();
});

test('it resolves a transformer class as a response', function () {
    $document = (new Api())
        ->name('transformer-response')
        ->withoutBroadcasting()
        ->get(
            '/users/me',
            tags: 'Users',
            summary: 'Get current user',
            response: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $response = $document['paths']['/users/me']['get']['responses']['200'];
    $schema = $response['content']['application/json']['schema'];

    expect($schema['$ref'])->toBe('#/components/schemas/User')
        ->and($document['components']['schemas'])->toHaveKey('User')
        ->and(array_keys($document['components']['schemas']['User']['properties']))->toBe(['id', 'name', 'email']);
});

test('it allows using a fractal transformer as a property type in the dsl', function () {
    $document = (new Api())
        ->name('transformer-type')
        ->withoutBroadcasting()
        ->post(
            '/webhooks',
            tags: 'Webhooks',
            summary: 'Receive webhooks',
            response: [
                'user:' . UserTransformer::class => 'Owning user',
            ],
        )
        ->toOpenAPI()
        ->build();

    $properties = $document['paths']['/webhooks']['post']['responses']['200']['content']['application/json']['schema']['properties'];

    expect($properties)->toHaveKey('user')
        ->and($properties['user']['$ref'])->toBe('#/components/schemas/User')
        ->and($document['components']['schemas'])->toHaveKey('User');
});
