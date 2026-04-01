<?php


namespace StackTrace\Inspec;


use Attribute;

#[Attribute]
class Schema
{
    public function __construct(
        public array  $object = [],
        public string $name = ''
    ) {}
}
