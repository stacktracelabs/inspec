<?php

namespace Workbench\App\Transformers;

use League\Fractal\TransformerAbstract;
use StackTrace\Inspec\Schema;
use Workbench\App\Schemas\Price;

class ReferencePropertiesTransformer extends TransformerAbstract
{
    #[Schema(object: [
        'nullable_schema?:'.Price::class => 'Nullable schema class reference',
        'schema:'.Price::class => 'Non-nullable schema class reference',
        'nullable_transformer?:'.UserTransformer::class => 'Nullable transformer reference',
        'transformer:'.UserTransformer::class => 'Non-nullable transformer reference',
        'nullable_schema_value?' => new Price(),
        'schema_value' => new Price(),
        'nullable_transformer_value?' => UserTransformer::class,
        'transformer_value' => UserTransformer::class,
    ])]
    public function transform(array $resource): array
    {
        return $resource;
    }
}
