<?php

namespace Workbench\App\Schemas;

use StackTrace\Inspec\SchemaObject;

class Price extends SchemaObject
{
    public function __construct()
    {
        parent::__construct('Price', [
            'amount:number' => 'Price amount',
            'currency:string' => 'Price currency',
        ]);
    }
}
