<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Schema;

class RoleTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'id:integer' => 'Role identifier',
        'slug:string' => 'Role slug',
    ])]
    public function transform(array $role): array
    {
        return $role;
    }
}
