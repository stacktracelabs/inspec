<?php


namespace StackTrace\Inspec;


interface RelationSerializer
{
    public function serializeItem(array $schema): array;

    public function serializeCollection(array $items): array;
}
