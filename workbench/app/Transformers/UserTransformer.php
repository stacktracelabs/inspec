<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Schema;

class UserTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'id:integer' => 'User identifier',
        'name:string' => 'User display name',
        'email:string' => 'User email address',
    ])]
    public function transform(array $user): array
    {
        return $user;
    }
}
