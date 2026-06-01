<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First-Party Support Intelligence
    |--------------------------------------------------------------------------
    |
    | Support requests remain first-party. Reports aggregate lifecycle signals
    | only and never expose ticket contents, request bodies, or personal data.
    |
    */

    'support' => [
        'message_max_length' => env('SUPPORT_MESSAGE_MAX_LENGTH', 4000),
        'metadata_limit' => env('SUPPORT_METADATA_LIMIT', 20),
    ],

    'analytics' => [
        'recurring_theme_minimum' => env('SUPPORT_RECURRING_THEME_MINIMUM', 2),
        'onboarding_issue_minimum' => env('SUPPORT_ONBOARDING_ISSUE_MINIMUM', 1),
    ],

    'customer_health' => [
        'attention_score' => env('SUPPORT_HEALTH_ATTENTION_SCORE', 2),
        'risk_score' => env('SUPPORT_HEALTH_RISK_SCORE', 5),
        'high_priority_weight' => env('SUPPORT_HEALTH_HIGH_PRIORITY_WEIGHT', 2),
        'critical_priority_weight' => env('SUPPORT_HEALTH_CRITICAL_PRIORITY_WEIGHT', 4),
        'feedback_issue_weight' => env('SUPPORT_HEALTH_FEEDBACK_ISSUE_WEIGHT', 1),
        'billing_issue_weight' => env('SUPPORT_HEALTH_BILLING_ISSUE_WEIGHT', 2),
        'abuse_issue_weight' => env('SUPPORT_HEALTH_ABUSE_ISSUE_WEIGHT', 2),
        'operational_risk_weight' => env('SUPPORT_HEALTH_OPERATIONAL_RISK_WEIGHT', 1),
    ],

    'privacy' => [
        'allow_external_helpdesk' => env('SUPPORT_ALLOW_EXTERNAL_HELPDESK', false),
        'include_messages_in_reports' => env('SUPPORT_INCLUDE_MESSAGES_IN_REPORTS', false),
        'include_email_addresses' => env('SUPPORT_INCLUDE_EMAIL_ADDRESSES', false),
        'include_request_bodies' => env('SUPPORT_INCLUDE_REQUEST_BODIES', false),
    ],
];
