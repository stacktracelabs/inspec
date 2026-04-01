<?php


namespace StackTrace\Inspec;


use Attribute;

#[Attribute]
class ExpandCollection
{
    public function __construct(
        public string $transformer
    ){ }
}
