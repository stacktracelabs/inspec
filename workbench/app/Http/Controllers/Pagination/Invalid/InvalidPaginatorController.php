<?php

namespace Workbench\App\Http\Controllers\Pagination\Invalid;

use StackTrace\Inspec\PagePaginator;
use StackTrace\Inspec\Route;

class InvalidPaginatorController
{
    #[Route(
        tags: 'Users',
        summary: 'Invalid paginator override',
        paginator: new PagePaginator(),
    )]
    public function __invoke(): array
    {
        return [];
    }
}
