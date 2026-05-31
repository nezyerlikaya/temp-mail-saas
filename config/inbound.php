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

    'driver' => env('INBOUND_MAIL_DRIVER', 'null'),
    'drivers' => [],
];
