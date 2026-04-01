<?php


namespace StackTrace\Inspec;


class GeneratorException extends \RuntimeException
{
    public static function withMessage(string $message): static
    {
        return new static($message);
    }
}
