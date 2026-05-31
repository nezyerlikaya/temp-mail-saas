<?php

return [
    'login' => [
        'max_attempts' => env('AUTH_LOGIN_MAX_ATTEMPTS', 5),
        'decay_seconds' => env('AUTH_LOGIN_DECAY_SECONDS', 60),
    ],

    'registration' => [
        'honeypot_field' => 'website',
        'minimum_submit_seconds' => env('AUTH_REGISTRATION_MINIMUM_SUBMIT_SECONDS', 2),
    ],
];
