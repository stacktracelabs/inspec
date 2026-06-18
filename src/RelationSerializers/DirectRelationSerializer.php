<?php


namespace StackTrace\Inspec\RelationSerializers;


use StackTrace\Inspec\RelationSerializer;

class DirectRelationSerializer implements RelationSerializer
{
    public function serializeItem(array $schema): array
    {
        return $schema;
    }

    public function serializeCollection(array $items): array
    {
        return [
            'type' => 'array',
            'items' => $items,
        ];
    }
}
