<?php


namespace StackTrace\Inspec\Responses;


use StackTrace\Inspec\Response;

class ValidationErrorResponse extends Response
{
    protected static function defaultName(): string
    {
        return 'ValidationErrorResponse';
    }

    protected static function defaultDescription(): string
    {
        return 'Validation error response';
    }

    protected static function defaultContentType(): string
    {
        return 'application/json';
    }

    protected static function defaultBody(): ?array
    {
        return [
            '@example' => [
                'message' => 'The phone number has already been taken.',
                'errors' => [
                    'phone_number' => [
                        'The phone number has already been taken.',
                    ],
                ],
            ],
            'message:string' => 'General error message',
            'errors' => [
                '@description' => 'Validation errors keyed by field name',
            ],
        ];
    }
}
