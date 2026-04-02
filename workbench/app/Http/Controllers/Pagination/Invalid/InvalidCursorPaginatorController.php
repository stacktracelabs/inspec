<?php

namespace Workbench\App\Http\Controllers\Pagination\Invalid;

use StackTrace\Inspec\Paginators\CursorPaginator;
use StackTrace\Inspec\Route;

class InvalidCursorPaginatorController
{
    #[Route(
        tags: 'Users',
        summary: 'Invalid cursor paginator override',
        cursorPaginator: new CursorPaginator(),
    )]
    public function __invoke(): array
    {
        return [];
    }
}
