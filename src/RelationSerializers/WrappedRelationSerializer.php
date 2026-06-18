<?php


namespace StackTrace\Inspec\RelationSerializers;


use StackTrace\Inspec\GeneratorException;
use StackTrace\Inspec\RelationSerializer;

class WrappedRelationSerializer implements RelationSerializer
{
    public readonly string $key;

    public function __construct(string $key = 'data')
    {
        $this->key = trim($key);

        if ($this->key === '') {
            throw GeneratorException::withMessage('The relation wrapper key cannot be empty.');
        }
    }

    public function serializeItem(array $schema): array
    {
        return [
            'type' => 'object',
            'properties' => [
                $this->key => $schema,
            ],
        ];
    }

    public function serializeCollection(array $items): array
    {
        return $this->serializeItem([
            'type' => 'array',
            'items' => $items,
        ]);
    }
}
