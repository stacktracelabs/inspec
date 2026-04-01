<?php


namespace StackTrace\Inspec;


use Attribute;

#[Attribute]
class ExpandItem
{
    public function __construct(
        public string|array $transformer
    ){ }
}
