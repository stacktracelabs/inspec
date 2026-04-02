<?php

namespace Workbench\App\Http\Controllers\Pagination\Overrides;

use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use StackTrace\Inspec\Route;
use Workbench\App\Transformers\UserTransformer;

class OverridePaginatedUsersController
{
    #[Route(
        tags: 'Users',
        summary: 'Override paginated users',
        paginatedResponse: UserTransformer::class,
        paginator: new LengthAwarePaginator(
            name: 'OverridePagePaginator',
            object: [
                'total:integer' => 'Total result count',
                'page:integer' => 'Resolved page number',
            ],
            metaKey: 'page_state',
            meta: [
                'filters' => [
                    'applied:boolean' => 'Whether filters are applied',
                ],
            ],
            defaultPerPage: 25,
        ),
    )]
    public function __invoke(): array
    {
        return [];
    }
}
