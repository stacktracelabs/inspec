<?php

namespace Workbench\App\Http\Controllers\Pagination\Invalid;

use StackTrace\Inspec\Paginators\LengthAwarePaginator;
use StackTrace\Inspec\Route;

class InvalidPaginatorController
{
    #[Route(
        tags: 'Users',
        summary: 'Invalid paginator override',
        paginator: new LengthAwarePaginator(),
    )]
    public function __invoke(): array
    {
        return [];
    }
}
