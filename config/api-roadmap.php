<?php

return [
    'api_review' => [
        'endpoint_discoverability_ready' => env('API_ROADMAP_DISCOVERABILITY_READY', true),
        'consistency_ready' => env('API_ROADMAP_CONSISTENCY_READY', true),
        'naming_conventions_ready' => env('API_ROADMAP_NAMING_READY', true),
        'response_standards_ready' => env('API_ROADMAP_RESPONSE_STANDARDS_READY', true),
    ],
    'lifecycle' => [
        'versioning_ready' => env('API_ROADMAP_VERSIONING_READY', true),
        'deprecation_ready' => env('API_ROADMAP_DEPRECATION_READY', true),
        'compatibility_strategy_ready' => env('API_ROADMAP_COMPATIBILITY_READY', true),
        'long_term_support_ready' => env('API_ROADMAP_LTS_READY', true),
    ],
    'onboarding' => [
        'flow_ready' => env('API_ROADMAP_ONBOARDING_FLOW_READY', true),
        'authentication_understanding_ready' => env('API_ROADMAP_AUTH_UNDERSTANDING_READY', true),
        'integration_readiness_ready' => env('API_ROADMAP_INTEGRATION_READY', true),
        'documentation_discoverability_ready' => env('API_ROADMAP_DOC_DISCOVERABILITY_READY', true),
    ],
    'documentation' => [
        'coverage_ready' => env('API_ROADMAP_DOC_COVERAGE_READY', true),
        'examples_ready' => env('API_ROADMAP_EXAMPLES_READY', true),
        'errors_ready' => env('API_ROADMAP_ERRORS_READY', true),
        'integration_guidance_ready' => env('API_ROADMAP_GUIDANCE_READY', true),
    ],
    'dx' => [
        'quick_win_limit' => env('API_ROADMAP_QUICK_WIN_LIMIT', 3),
        'onboarding_limit' => env('API_ROADMAP_ONBOARDING_LIMIT', 4),
        'documentation_limit' => env('API_ROADMAP_DOCUMENTATION_LIMIT', 4),
    ],
    'roadmap' => [
        'phase_one_limit' => env('API_ROADMAP_PHASE_ONE_LIMIT', 4),
        'candidates' => [
            [
                'key' => 'api_getting_started_guide',
                'title' => 'Plan API getting-started guidance',
                'category' => 'onboarding',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'api_error_reference',
                'title' => 'Define API error reference improvements',
                'category' => 'documentation',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'api_authentication_examples',
                'title' => 'Plan authentication usage examples',
                'category' => 'onboarding',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'api_versioning_guidance',
                'title' => 'Clarify API versioning and compatibility guidance',
                'category' => 'lifecycle',
                'priority' => 'medium',
                'impact' => 'high',
                'complexity' => 'medium',
                'risk' => 'low',
            ],
            [
                'key' => 'api_integration_checklist',
                'title' => 'Plan developer integration checklist',
                'category' => 'documentation',
                'priority' => 'medium',
                'impact' => 'medium',
                'complexity' => 'medium',
                'risk' => 'medium',
            ],
        ],
    ],
];
