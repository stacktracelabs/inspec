<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Schema;

class TeamTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'id:integer' => 'Team identifier',
        'name:string' => 'Team name',
    ])]
    public function transform(array $team): array
    {
        return $team;
    }
}
