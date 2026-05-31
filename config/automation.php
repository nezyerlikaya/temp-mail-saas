<?php

return [
    'enabled' => env('AUTOMATION_ENABLED', true),

    'limits' => [
        'max_rules_per_trigger' => 50,
        'max_executions_per_run' => 100,
    ],

    'execution_retention' => [
        'days' => 90,
    ],

    'scoring' => [
        'thresholds' => [
            'low' => 25,
            'medium' => 50,
            'high' => 75,
            'critical' => 90,
        ],
        'types' => [
            'abuse_risk',
            'domain_health',
            'queue_health',
            'engagement',
            'retention',
        ],
    ],

    'intelligence' => [
        'enabled' => true,
        'external_ai_enabled' => false,
        'recommendations_enabled' => false,
        'store_raw_payloads' => false,
    ],

    'schedule' => [
        'automation_evaluation_enabled' => env('AUTOMATION_SCHEDULE_ENABLED', false),
        'automation_evaluation_frequency' => env('AUTOMATION_SCHEDULE_FREQUENCY', 'hourly'),
        'intelligence_recalculation_enabled' => env('INTELLIGENCE_SCHEDULE_ENABLED', false),
        'intelligence_recalculation_frequency' => env('INTELLIGENCE_SCHEDULE_FREQUENCY', 'hourly'),
    ],
];
