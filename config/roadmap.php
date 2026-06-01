<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Long-Term Maintenance Roadmap
    |--------------------------------------------------------------------------
    |
    | These settings describe review criteria and future candidates only.
    | They do not enable customer-facing features or infrastructure changes.
    |
    */

    'debt_review' => [
        'areas' => [
            [
                'key' => 'readiness_service_consolidation',
                'area' => 'platform',
                'summary' => 'Review repeated readiness result formatting for a future shared helper.',
                'severity' => 'medium',
                'priority' => 'v1.1',
                'risk' => 'low',
            ],
            [
                'key' => 'content_route_activation',
                'area' => 'growth',
                'summary' => 'Review public content route activation before editorial expansion.',
                'severity' => 'low',
                'priority' => 'v1.2',
                'risk' => 'low',
            ],
            [
                'key' => 'aggregate_analytics_storage',
                'area' => 'analytics',
                'summary' => 'Design aggregate-only product analytics storage before collecting conversion metrics.',
                'severity' => 'medium',
                'priority' => 'v1.1',
                'risk' => 'medium',
            ],
        ],
        'block_on_critical' => env('ROADMAP_BLOCK_ON_CRITICAL_DEBT', true),
        'warn_on_high' => env('ROADMAP_WARN_ON_HIGH_DEBT', true),
    ],

    'architecture' => [
        'module_boundaries_documented' => env('ROADMAP_MODULE_BOUNDARIES_DOCUMENTED', true),
        'service_responsibilities_reviewed' => env('ROADMAP_SERVICE_RESPONSIBILITIES_REVIEWED', true),
        'dependency_structure_reviewed' => env('ROADMAP_DEPENDENCY_STRUCTURE_REVIEWED', true),
        'maintainability_reviewed' => env('ROADMAP_ARCHITECTURE_MAINTAINABILITY_REVIEWED', true),
    ],

    'scalability' => [
        'queue_reviewed' => env('ROADMAP_QUEUE_SCALABILITY_REVIEWED', true),
        'provider_reviewed' => env('ROADMAP_PROVIDER_SCALABILITY_REVIEWED', true),
        'domain_reviewed' => env('ROADMAP_DOMAIN_SCALABILITY_REVIEWED', true),
        'operations_reviewed' => env('ROADMAP_OPERATIONS_SCALABILITY_REVIEWED', true),
        'billing_reviewed' => env('ROADMAP_BILLING_SCALABILITY_REVIEWED', true),
    ],

    'maintainability' => [
        'code_organization_reviewed' => env('ROADMAP_CODE_ORGANIZATION_REVIEWED', true),
        'testing_coverage_reviewed' => env('ROADMAP_TESTING_COVERAGE_REVIEWED', true),
        'documentation_coverage_reviewed' => env('ROADMAP_DOCUMENTATION_COVERAGE_REVIEWED', true),
        'operational_readiness_reviewed' => env('ROADMAP_OPERATIONAL_READINESS_REVIEWED', true),
    ],

    'prioritization' => [
        'v1.1' => [
            ['category' => 'platform', 'candidate' => 'Readiness service consolidation review', 'priority' => 'high'],
            ['category' => 'analytics', 'candidate' => 'Aggregate-only conversion metrics design', 'priority' => 'high'],
            ['category' => 'operations', 'candidate' => 'Operational runbook maintenance cadence', 'priority' => 'medium'],
        ],
        'v1.2' => [
            ['category' => 'growth', 'candidate' => 'Editorial content route planning', 'priority' => 'medium'],
            ['category' => 'billing', 'candidate' => 'Billing provider activation planning', 'priority' => 'medium'],
        ],
        'future' => [
            ['category' => 'enterprise', 'candidate' => 'Enterprise controls review', 'priority' => 'low'],
            ['category' => 'inbox', 'candidate' => 'Persistent inbox ownership review', 'priority' => 'low'],
        ],
    ],
];
