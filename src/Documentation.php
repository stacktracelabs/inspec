<?php


namespace StackTrace\Inspec;


abstract class Documentation
{
    public abstract function build(Api $api): void;
}
