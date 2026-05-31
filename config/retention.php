<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Retention Placeholder
    |--------------------------------------------------------------------------
    |
    | Reserved for future mailbox, email, abuse, and audit retention policies.
    | Values are intentionally conservative and inactive until implemented.
    |
    */

    'enabled' => false,
    'default_ttl_minutes' => env('TEMPMAIL_DEFAULT_TTL_MINUTES', 60),
];
