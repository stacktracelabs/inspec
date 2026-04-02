<?php


namespace StackTrace\Inspec\Paginators;

use StackTrace\Inspec\Paginator;

class LengthAwarePaginator extends Paginator
{
    protected static function defaultName(): string
    {
        return 'LengthAwarePaginator';
    }

    protected static function defaultObject(): array
    {
        return [
            'total:integer' => 'Total number of results',
            'count:integer' => 'Number of results on current page',
            'per_page:integer' => 'Number of results per single page',
            'current_page:integer' => 'Current page number (starting with 1 as first page)',
            'total_pages:integer' => 'Total number of pages',
            'links' => [
                'next?:string' => 'Link to the next page if available',
            ],
        ];
    }

    protected static function defaultQuery(?int $defaultPerPage): array
    {
        return [
            'limit:integer' => 'Number of results to return. Defaults to '.($defaultPerPage ?? 15),
            'page:integer' => 'The page number',
        ];
    }

    protected static function defaultMetaKey(): string
    {
        return 'pagination';
    }
}
