<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Inbound Mail Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for future inbound adapters such as webhook, IMAP, SMTP pipe, or
    | provider-specific integrations. This step does not receive email.
    |
    */

    'provider' => env('INBOUND_MAIL_PROVIDER', 'null'),
    'driver' => env('INBOUND_MAIL_DRIVER', env('INBOUND_MAIL_PROVIDER', 'null')),
    'queue' => [
        'enabled' => env('INBOUND_QUEUE_ENABLED', false),
        'connection' => env('INBOUND_QUEUE_CONNECTION', env('QUEUE_CONNECTION', 'sync')),
        'name' => env('INBOUND_QUEUE_NAME', 'inbound-mail'),
    ],
    'drivers' => [],
];
