<?php


namespace StackTrace\Inspec\Paginators;

use StackTrace\Inspec\Paginator;

class CursorPaginator extends Paginator
{
    protected static function defaultName(): string
    {
        return 'CursorPaginator';
    }

    protected static function defaultObject(): array
    {
        return [
            'current?:string' => 'Currently applied cursor',
            'prev?:string' => 'The previous cursor',
            'next?:string' => 'The next cursor',
            'count:integer' => 'Total number of results on current page',
        ];
    }

    protected static function defaultQuery(?int $defaultPerPage): array
    {
        return [
            'limit:integer' => 'Number of results to return. Defaults to '.($defaultPerPage ?? 15),
            'cursor:string' => 'The pagination cursor value',
        ];
    }

    protected static function defaultMetaKey(): string
    {
        return 'cursor';
    }

    protected function buildResponseBody(array $items, array $metaProperties): ?array
    {
        return [
            'type' => 'object',
            'properties' => [
                'data' => [
                    'type' => 'array',
                    'items' => $items,
                ],
                'meta' => [
                    'type' => 'object',
                    'properties' => $metaProperties,
                ],
            ],
        ];
    }
}
