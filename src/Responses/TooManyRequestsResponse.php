<?php


namespace StackTrace\Inspec\Responses;


use StackTrace\Inspec\Response;

class TooManyRequestsResponse extends Response
{
    protected static function defaultName(): string
    {
        return 'TooManyRequestsResponse';
    }

    protected static function defaultDescription(): string
    {
        return 'Too many requests';
    }

    protected static function defaultContentType(): string
    {
        return 'application/json';
    }

    protected static function defaultBody(): ?array
    {
        return [
            '@example' => [
                'message' => 'Too many requests.',
            ],
            'message:string' => 'General error message',
        ];
    }
}
