<?php


namespace StackTrace\Inspec\Operations\Broadcasting;


use StackTrace\Inspec\Operation;

class AuthenticateWebsocketUserOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            tags: 'Broadcasting',
            summary: 'Authenticate Websocket user',
            request: [
                'socket_id:string' => 'The socket identifier',
            ],
            response: [
                'auth:string' => 'Auth token',
                'user_data:string' => 'Double-encoded JSON containing authenticated user information.',
            ],
        );
    }
}
