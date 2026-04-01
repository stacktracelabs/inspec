<?php


namespace StackTrace\Inspec;


class SchemaObject
{
    public function __construct(
        public readonly string $name,
        public readonly array $attributes
    ) { }
}
