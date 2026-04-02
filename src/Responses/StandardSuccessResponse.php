<?php


namespace StackTrace\Inspec\Responses;


use StackTrace\Inspec\SuccessResponse;

class StandardSuccessResponse extends SuccessResponse
{
    protected static function defaultDescription(): string
    {
        return 'Successful response';
    }

    protected static function defaultContentType(): string
    {
        return 'application/json';
    }

    protected function buildBody(array $schema): ?array
    {
        return $schema;
    }
}
