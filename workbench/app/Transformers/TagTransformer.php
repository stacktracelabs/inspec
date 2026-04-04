<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Schema;

class TagTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'id:integer' => 'Tag identifier',
        'label:string' => 'Tag label',
    ])]
    public function transform(array $tag): array
    {
        return $tag;
    }
}
