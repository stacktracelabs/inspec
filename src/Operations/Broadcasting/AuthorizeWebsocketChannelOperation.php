<?php


namespace StackTrace\Inspec\Operations\Broadcasting;


use StackTrace\Inspec\Operation;

class AuthorizeWebsocketChannelOperation extends Operation
{
    public function __construct()
    {
        parent::__construct(
            tags: 'Broadcasting',
            summary: 'Authorize Websocket channel',
            request: [
                'socket_id:string' => 'The socket identifier',
                'channel_name:string' => 'The channel name',
            ],
            response: [
                'auth:string' => 'Auth token',
                'channel_data:string' => 'Double-encoded JSON containing channel information.',
            ],
        );
    }
}
