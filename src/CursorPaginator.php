<?php


namespace StackTrace\Inspec;


class CursorPaginator extends PaginationDefinition
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
}
