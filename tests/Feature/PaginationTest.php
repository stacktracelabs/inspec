<?php

use StackTrace\Inspec\Api;
use StackTrace\Inspec\GeneratorException;
use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use Workbench\App\Transformers\UserTransformer;

class ApiWidePagePaginator extends LengthAwarePaginator
{
    protected static function defaultResponseDescription(): string
    {
        return 'API-wide page response';
    }

    protected function buildResponseBody(array $items, array $metaProperties): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'items' => $items,
                ],
                'page' => [
                    'type' => 'object',
                    'description' => 'Wrapped '.$this->name,
                    'properties' => $metaProperties,
                ],
            ],
        ];
    }
}

class ApiWideCursorPaginator extends CursorPaginator
{
    protected static function defaultResponseDescription(): string
    {
        return 'API-wide cursor response';
    }

    protected function buildResponseBody(array $items, array $metaProperties): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'results' => [
                    'type' => 'array',
                    'items' => $items,
                ],
                'cursor_state' => [
                    'type' => 'object',
                    'description' => 'Wrapped '.$this->name,
                    'properties' => $metaProperties,
                ],
            ],
        ];
    }
}

function operationParameters(array $document, string $path): array
{
    return collect($document['paths'][$path]['get']['parameters'] ?? [])
        ->keyBy(fn (array $parameter) => $parameter['in'].':'.$parameter['name'])
        ->all();
}

test('it uses the built in page paginator definition', function () {
    $document = (new Api())
        ->name('pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'List users',
            paginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $parameters = operationParameters($document, '/paginated-users');
    $response = $document['paths']['/paginated-users']['get']['responses']['200']['content']['application/json']['schema'];

    expect(array_keys($document['paths']))->toBe(['/paginated-users'])
        ->and(array_keys($parameters))->toContain('query:limit', 'query:page')
        ->and($parameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 15')
        ->and($parameters['query:page']['description'])->toBe('The page number')
        ->and($response['properties']['meta']['properties']['pagination']['$ref'])->toBe('#/components/schemas/LengthAwarePaginator')
        ->and($document['components']['schemas']['LengthAwarePaginator']['properties'])->toHaveKeys([
            'total',
            'count',
            'per_page',
            'current_page',
            'total_pages',
            'links',
        ]);
});

test('it uses the built in cursor paginator definition', function () {
    $document = (new Api())
        ->name('cursor-pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/cursor-users',
            tags: 'Users',
            summary: 'List users by cursor',
            cursorPaginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $parameters = operationParameters($document, '/cursor-users');
    $response = $document['paths']['/cursor-users']['get']['responses']['200']['content']['application/json']['schema'];

    expect(array_keys($parameters))->toContain('query:limit', 'query:cursor')
        ->and($parameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 15')
        ->and($parameters['query:cursor']['description'])->toBe('The pagination cursor value')
        ->and($response['properties']['meta']['properties']['cursor']['$ref'])->toBe('#/components/schemas/CursorPaginator')
        ->and($document['components']['schemas']['CursorPaginator']['properties'])->toHaveKeys([
            'current',
            'prev',
            'next',
            'count',
        ]);
});

test('it applies api wide paginator definitions and emits inline responses', function () {
    $document = (new Api())
        ->name('custom-pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withPagination(
            (new LengthAwarePaginator())
                ->withSchema('CustomPagePaginator', [
                    'total:integer' => 'Total results',
                    'last_page:integer' => 'Last page number',
                ])
                ->withMetaKey('page_info')
                ->withMeta([
                    'filters' => [
                        'applied:boolean' => 'Whether filters are applied',
                    ],
                ])
                ->defaultPerPage(50)
                ->withResponseDescription('Custom page response')
                ->withResponseHeaders([
                    'x-total:integer' => 'Total results header',
                ])
        )
        ->withCursorPagination(
            (new CursorPaginator())
                ->withSchema('CustomCursorPaginator', [
                    'next?:string' => 'Next cursor',
                    'has_more:boolean' => 'Whether more results exist',
                ])
                ->withMetaKey('cursor_info')
                ->withMeta([
                    'trace_id:string' => 'Cursor pagination trace identifier',
                ])
                ->defaultPerPage(100)
                ->withResponseDescription('Custom cursor response')
                ->withResponseHeaders([
                    'x-next-cursor:string' => 'Next cursor header',
                ])
        )
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'List users',
            paginatedResponse: UserTransformer::class,
        )
        ->get(
            '/api/cursor-users',
            tags: 'Users',
            summary: 'List users by cursor',
            cursorPaginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $pageParameters = operationParameters($document, '/paginated-users');
    $cursorParameters = operationParameters($document, '/cursor-users');
    $pageResponse = $document['paths']['/paginated-users']['get']['responses']['200'];
    $cursorResponse = $document['paths']['/cursor-users']['get']['responses']['200'];
    $pageSchema = $pageResponse['content']['application/json']['schema'];
    $cursorSchema = $cursorResponse['content']['application/json']['schema'];

    expect($pageParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 50')
        ->and($cursorParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 100')
        ->and($pageResponse['description'])->toBe('Custom page response')
        ->and($cursorResponse['description'])->toBe('Custom cursor response')
        ->and(array_key_exists('$ref', $pageResponse))->toBeFalse()
        ->and(array_key_exists('$ref', $cursorResponse))->toBeFalse()
        ->and($pageResponse['headers']['x-total']['schema']['type'])->toBe('integer')
        ->and($cursorResponse['headers']['x-next-cursor']['schema']['type'])->toBe('string')
        ->and($pageSchema['properties']['meta']['properties']['page_info']['$ref'])->toBe('#/components/schemas/CustomPagePaginator')
        ->and($pageSchema['properties']['meta']['properties']['filters']['type'])->toBe('object')
        ->and($cursorSchema['properties']['meta']['properties']['cursor_info']['$ref'])->toBe('#/components/schemas/CustomCursorPaginator')
        ->and($cursorSchema['properties']['meta']['properties']['trace_id']['type'])->toBe('string')
        ->and($document['components']['schemas']['CustomPagePaginator']['properties'])->toHaveKeys(['total', 'last_page'])
        ->and($document['components']['schemas']['CustomCursorPaginator']['properties'])->toHaveKeys(['next', 'has_more']);
});

test('it lets api wide paginators customize the response envelope', function () {
    $document = (new Api())
        ->name('custom-paginator-responses')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withPagination(new ApiWidePagePaginator())
        ->withCursorPagination(new ApiWideCursorPaginator())
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'List users',
            paginatedResponse: UserTransformer::class,
        )
        ->get(
            '/api/cursor-users',
            tags: 'Users',
            summary: 'List users by cursor',
            cursorPaginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $pageResponse = $document['paths']['/paginated-users']['get']['responses']['200'];
    $cursorResponse = $document['paths']['/cursor-users']['get']['responses']['200'];

    expect($pageResponse['description'])->toBe('API-wide page response')
        ->and(array_keys($pageResponse['content']['application/json']['schema']['properties']))->toBe(['items', 'page'])
        ->and($pageResponse['content']['application/json']['schema']['properties']['page']['description'])->toBe('Wrapped LengthAwarePaginator')
        ->and($cursorResponse['description'])->toBe('API-wide cursor response')
        ->and(array_keys($cursorResponse['content']['application/json']['schema']['properties']))->toBe(['results', 'cursor_state'])
        ->and($cursorResponse['content']['application/json']['schema']['properties']['cursor_state']['description'])->toBe('Wrapped CursorPaginator');
});

test('it applies api path filters to canonical paginated paths', function () {
    $document = (new Api())
        ->name('filtered-pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->filterPath('^/paginated-users$')
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'List users',
            paginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    expect(array_keys($document['paths']))->toBe(['/paginated-users']);
});

test('it fails when paginator schema names collide with different definitions', function () {
    expect(fn () => (new Api())
        ->name('conflicting-paginators')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withPagination(new LengthAwarePaginator(
            name: 'SharedPaginator',
            object: [
                'total:integer' => 'Total results',
            ],
        ))
        ->withCursorPagination(new CursorPaginator(
            name: 'SharedPaginator',
            object: [
                'count:integer' => 'Different paginator shape',
            ],
        ))
        ->get(
            '/api/conflicting-paginator-one',
            tags: 'Users',
            summary: 'First paginator',
            paginatedResponse: UserTransformer::class,
        )
        ->get(
            '/api/conflicting-paginator-two',
            tags: 'Users',
            summary: 'Second paginator',
            cursorPaginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build())
        ->toThrow(GeneratorException::class, 'The pagination schema [SharedPaginator] is already registered with a different definition.');
});
