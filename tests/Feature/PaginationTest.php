<?php

use StackTrace\Inspec\Api;
use StackTrace\Inspec\GeneratorException;
use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use Workbench\App\Transformers\UserTransformer;

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

test('it applies api wide paginator definitions', function () {
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
    $pageResponse = $document['paths']['/paginated-users']['get']['responses']['200']['content']['application/json']['schema'];
    $cursorResponse = $document['paths']['/cursor-users']['get']['responses']['200']['content']['application/json']['schema'];

    expect($pageParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 50')
        ->and($cursorParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 100')
        ->and($pageResponse['properties']['meta']['properties']['page_info']['$ref'])->toBe('#/components/schemas/CustomPagePaginator')
        ->and($pageResponse['properties']['meta']['properties']['filters']['type'])->toBe('object')
        ->and($cursorResponse['properties']['meta']['properties']['cursor_info']['$ref'])->toBe('#/components/schemas/CustomCursorPaginator')
        ->and($cursorResponse['properties']['meta']['properties']['trace_id']['type'])->toBe('string')
        ->and($document['components']['schemas']['CustomPagePaginator']['properties'])->toHaveKeys(['total', 'last_page'])
        ->and($document['components']['schemas']['CustomCursorPaginator']['properties'])->toHaveKeys(['next', 'has_more']);
});

test('it lets route overrides replace api wide paginator definitions', function () {
    $document = (new Api())
        ->name('pagination-overrides')
        ->prefix('api')
        ->withoutBroadcasting()
        ->withPagination(
            new LengthAwarePaginator(
                name: 'ApiWidePaginator',
                metaKey: 'api_page',
                defaultPerPage: 40,
            )
        )
        ->withCursorPagination(
            new CursorPaginator(
                name: 'ApiWideCursorPaginator',
                metaKey: 'api_cursor',
                defaultPerPage: 60,
            )
        )
        ->controllers(\Orchestra\Testbench\workbench_path('app/Http/Controllers/Pagination/Overrides'))
        ->get(
            '/api/paginated-users',
            tags: 'Users',
            summary: 'Default page users',
            paginatedResponse: UserTransformer::class,
        )
        ->get(
            '/api/cursor-users',
            tags: 'Users',
            summary: 'Default cursor users',
            cursorPaginatedResponse: UserTransformer::class,
        )
        ->toOpenAPI()
        ->build();

    $defaultPageParameters = operationParameters($document, '/paginated-users');
    $defaultCursorParameters = operationParameters($document, '/cursor-users');
    $overridePageParameters = operationParameters($document, '/override-page-users');
    $overrideCursorParameters = operationParameters($document, '/override-cursor-users');
    $defaultPageMeta = $document['paths']['/paginated-users']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'];
    $defaultCursorMeta = $document['paths']['/cursor-users']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'];
    $overridePageMeta = $document['paths']['/override-page-users']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'];
    $overrideCursorMeta = $document['paths']['/override-cursor-users']['get']['responses']['200']['content']['application/json']['schema']['properties']['meta']['properties'];

    expect($defaultPageParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 40')
        ->and($defaultCursorParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 60')
        ->and($defaultPageMeta['api_page']['$ref'])->toBe('#/components/schemas/ApiWidePaginator')
        ->and($defaultCursorMeta['api_cursor']['$ref'])->toBe('#/components/schemas/ApiWideCursorPaginator')
        ->and($overridePageParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 25')
        ->and($overrideCursorParameters['query:limit']['description'])->toBe('Number of results to return. Defaults to 100')
        ->and($overridePageMeta['page_state']['$ref'])->toBe('#/components/schemas/OverridePagePaginator')
        ->and($overridePageMeta['filters']['type'])->toBe('object')
        ->and($overrideCursorMeta['cursor_state']['$ref'])->toBe('#/components/schemas/OverrideCursorPaginator')
        ->and($overrideCursorMeta['trace_id']['type'])->toBe('string');
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

test('it fails when paginator is used without paginatedResponse', function () {
    expect(fn () => (new Api())
        ->name('invalid-pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/invalid-page-users',
            tags: 'Users',
            summary: 'Invalid paginator',
            paginator: new LengthAwarePaginator(),
        )
        ->toOpenAPI()
        ->build())
        ->toThrow(GeneratorException::class, 'The [paginator] override requires [paginatedResponse].');
});

test('it fails when cursorPaginator is used without cursorPaginatedResponse', function () {
    expect(fn () => (new Api())
        ->name('invalid-cursor-pagination')
        ->prefix('api')
        ->withoutBroadcasting()
        ->get(
            '/api/invalid-cursor-users',
            tags: 'Users',
            summary: 'Invalid cursor paginator',
            cursorPaginator: new CursorPaginator(),
        )
        ->toOpenAPI()
        ->build())
        ->toThrow(GeneratorException::class, 'The [cursorPaginator] override requires [cursorPaginatedResponse].');
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
            paginatedResponse: UserTransformer::class,
            paginator: new LengthAwarePaginator(
                name: 'SharedPaginator',
                object: [
                    'count:integer' => 'Different paginator shape',
                ],
            ),
        )
        ->toOpenAPI()
        ->build())
        ->toThrow(GeneratorException::class, 'The pagination schema [SharedPaginator] is already registered with a different definition.');
});
