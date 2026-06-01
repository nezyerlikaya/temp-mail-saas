<?php

return [
    /*
    |--------------------------------------------------------------------------
    | First-Party Product Intelligence
    |--------------------------------------------------------------------------
    |
    | Feedback stays first-party and privacy-conscious. Reports aggregate
    | counts only and never expose feedback messages, email addresses, or
    | mailbox contents.
    |
    */

    'feedback' => [
        'message_max_length' => env('PRODUCT_FEEDBACK_MESSAGE_MAX_LENGTH', 4000),
        'metadata_limit' => env('PRODUCT_FEEDBACK_METADATA_LIMIT', 20),
    ],

    'trends' => [
        'minimum_count' => env('PRODUCT_INTELLIGENCE_TREND_MINIMUM', 2),
        'recurring_issue_minimum' => env('PRODUCT_INTELLIGENCE_RECURRING_ISSUE_MINIMUM', 2),
        'feature_request_minimum' => env('PRODUCT_INTELLIGENCE_FEATURE_REQUEST_MINIMUM', 1),
    ],

    'roadmap' => [
        'high_demand_minimum' => env('PRODUCT_INTELLIGENCE_HIGH_DEMAND_MINIMUM', 5),
        'medium_demand_minimum' => env('PRODUCT_INTELLIGENCE_MEDIUM_DEMAND_MINIMUM', 2),
    ],

    'privacy' => [
        'allow_external_feedback_saas' => env('PRODUCT_INTELLIGENCE_EXTERNAL_FEEDBACK_SAAS', false),
        'include_messages_in_reports' => env('PRODUCT_INTELLIGENCE_INCLUDE_MESSAGES_IN_REPORTS', false),
        'include_email_addresses' => env('PRODUCT_INTELLIGENCE_INCLUDE_EMAIL_ADDRESSES', false),
        'include_mailbox_contents' => env('PRODUCT_INTELLIGENCE_INCLUDE_MAILBOX_CONTENTS', false),
    ],
];
