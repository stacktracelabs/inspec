<?php

namespace Workbench\App\Http\Controllers\Pagination\Overrides;

use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Route;
use Workbench\App\Transformers\UserTransformer;

class OverrideCursorUsersController
{
    #[Route(
        tags: 'Users',
        summary: 'Override cursor users',
        cursorPaginatedResponse: UserTransformer::class,
        cursorPaginator: new CursorPaginator(
            name: 'OverrideCursorPaginator',
            object: [
                'next?:string' => 'The next cursor value',
                'has_more:boolean' => 'Whether more results are available',
            ],
            metaKey: 'cursor_state',
            meta: [
                'trace_id:string' => 'Pagination trace identifier',
            ],
            defaultPerPage: 100,
        ),
    )]
    public function __invoke(): array
    {
        return [];
    }
}
