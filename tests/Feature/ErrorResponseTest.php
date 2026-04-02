<?php

use StackTrace\Inspec\Api;
use StackTrace\Inspec\GeneratorException;
use StackTrace\Inspec\Response;

class CustomValidationResponse extends Response
{
    protected static function defaultName(): string
    {
        return 'CustomValidationResponse';
    }

    protected static function defaultDescription(): string
    {
        return 'Custom validation response';
    }

    protected static function defaultContentType(): string
    {
        return 'application/json';
    }

    protected static function defaultBody(): ?array
    {
        return [
            'message:string' => 'Custom validation message',
            'code:string' => 'Validation error code',
        ];
    }
}

class CustomTooManyRequestsResponse extends Response
{
    protected static function defaultName(): string
    {
        return 'CustomTooManyRequestsResponse';
    }

    protected static function defaultDescription(): string
    {
        return 'Custom too many requests response';
    }

    protected static function defaultContentType(): string
    {
        return 'application/json';
    }

    protected static function defaultBody(): ?array
    {
        return [
            'message:string' => 'Custom too many requests message',
            'retry_after:integer' => 'Seconds until the next allowed request',
        ];
    }
}

function operationResponses(array $document, string $path, string $method): array
{
    return $document['paths'][$path][$method]['responses'] ?? [];
}

test('it infers validation responses only when request data is documented', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
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
            '/api/error-responses/plain',
            tags: 'Errors',
            summary: 'Show plain status',
            response: [
                'status:string' => 'Request status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $requestResponses = operationResponses($document, '/error-responses/request', 'post');
    $plainResponses = operationResponses($document, '/error-responses/plain', 'get');

    expect($requestResponses[422]['$ref'])->toBe('#/components/responses/ValidationErrorResponse')
        ->and($plainResponses[422] ?? null)->toBeNull();
});

test('it infers too many requests responses only for throttled routes', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/error-responses/plain',
            tags: 'Errors',
            summary: 'Show plain status',
            response: [
                'status:string' => 'Request status',
            ],
        )
        ->get(
            '/api/error-responses/throttled',
            tags: 'Errors',
            summary: 'Show throttled status',
            response: [
                'status:string' => 'Request status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $plainResponses = operationResponses($document, '/error-responses/plain', 'get');
    $throttledResponses = operationResponses($document, '/error-responses/throttled', 'get');

    expect($plainResponses[429] ?? null)->toBeNull()
        ->and($throttledResponses[429]['$ref'])->toBe('#/components/responses/TooManyRequestsResponse');
});

test('it lets route additional responses disable inferred validation and too many requests responses', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->post(
            '/api/error-responses/throttled-request',
            tags: 'Errors',
            summary: 'Submit throttled request',
            request: [
                'email!:string' => 'Email address',
            ],
            response: [
                'status:string' => 'Submission status',
            ],
            additionalResponses: [
                422 => null,
                429 => null,
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/throttled-request', 'post');

    expect($responses[422] ?? null)->toBeNull()
        ->and($responses[429] ?? null)->toBeNull();
});

test('it accepts null route additional responses for arbitrary status codes', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/error-responses/plain',
            tags: 'Errors',
            summary: 'Show plain status',
            response: [
                'status:string' => 'Request status',
            ],
            additionalResponses: [
                404 => null,
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/plain', 'get');

    expect($responses[404] ?? null)->toBeNull();
});

test('it allows description only overrides for inferred responses', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
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
            additionalResponses: [
                422 => 'Validation failed',
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/request', 'post');

    expect($responses[422])->toBe([
        'description' => 'Validation failed',
    ])
        ->and($document['components']['responses']['ValidationErrorResponse'] ?? null)->toBeNull();
});

test('it allows route level response shape class overrides', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
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
            additionalResponses: [
                422 => CustomValidationResponse::class,
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/request', 'post');

    expect($responses[422]['$ref'])->toBe('#/components/responses/CustomValidationResponse')
        ->and($document['components']['responses']['CustomValidationResponse']['description'])->toBe('Custom validation response')
        ->and($document['components']['responses']['CustomValidationResponse']['content']['application/json']['schema']['properties'])->toHaveKeys([
            'message',
            'code',
        ]);
});

test('it can opt out of api level validation error defaults', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withoutValidationErrorResponse()
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
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/request', 'post');

    expect($responses[422] ?? null)->toBeNull();
});

test('it can opt out of api level too many requests defaults', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withoutTooManyRequestsResponse()
        ->get(
            '/api/error-responses/throttled',
            tags: 'Errors',
            summary: 'Show throttled status',
            response: [
                'status:string' => 'Request status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/throttled', 'get');

    expect($responses[429] ?? null)->toBeNull();
});

test('it lets api level dedicated methods override built in inferred response shapes', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withValidationErrorResponse(new CustomValidationResponse())
        ->withTooManyRequestsResponse(new CustomTooManyRequestsResponse())
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
            '/api/error-responses/throttled',
            tags: 'Errors',
            summary: 'Show throttled status',
            response: [
                'status:string' => 'Request status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $requestResponses = operationResponses($document, '/error-responses/request', 'post');
    $throttledResponses = operationResponses($document, '/error-responses/throttled', 'get');

    expect($requestResponses[422]['$ref'])->toBe('#/components/responses/CustomValidationResponse')
        ->and($throttledResponses[429]['$ref'])->toBe('#/components/responses/CustomTooManyRequestsResponse');
});

test('it lets generic api error response methods override supported inferred codes', function () {
    $document = (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withErrorResponse(422, new CustomValidationResponse(name: 'GenericValidationResponse'))
        ->withErrorResponse(429, new CustomTooManyRequestsResponse(name: 'GenericTooManyRequestsResponse'))
        ->post(
            '/api/error-responses/throttled-request',
            tags: 'Errors',
            summary: 'Submit throttled request',
            request: [
                'email!:string' => 'Email address',
            ],
            response: [
                'status:string' => 'Submission status',
            ],
        )
        ->toOpenAPI()
        ->build();

    $responses = operationResponses($document, '/error-responses/throttled-request', 'post');

    expect($responses[422]['$ref'])->toBe('#/components/responses/GenericValidationResponse')
        ->and($responses[429]['$ref'])->toBe('#/components/responses/GenericTooManyRequestsResponse');
});

test('it fails when unsupported generic error response codes are configured', function () {
    expect(fn () => (new Api())->withErrorResponse(404, new CustomValidationResponse()))
        ->toThrow(GeneratorException::class, 'The error response [404] is not supported. Supported error responses are [422, 429].');
});

test('it fails when response shape component names collide with different definitions', function () {
    expect(fn () => (new Api())
        ->name('errors')
        ->prefix('api')
        ->withoutBroadcasting()
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
            additionalResponses: [
                422 => new CustomValidationResponse(name: 'SharedErrorResponse'),
            ],
        )
        ->get(
            '/api/error-responses/throttled',
            tags: 'Errors',
            summary: 'Show throttled status',
            response: [
                'status:string' => 'Request status',
            ],
            additionalResponses: [
                429 => new CustomTooManyRequestsResponse(name: 'SharedErrorResponse'),
            ],
        )
        ->toOpenAPI()
        ->build())
        ->toThrow(GeneratorException::class, 'The response component [SharedErrorResponse] is already registered with a different definition.');
});
