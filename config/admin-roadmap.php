<?php

return [
    'admin_workflow' => [
        'daily_tasks_ready' => env('ADMIN_ROADMAP_DAILY_TASKS_READY', true),
        'navigation_ready' => env('ADMIN_ROADMAP_NAVIGATION_READY', true),
        'discoverability_ready' => env('ADMIN_ROADMAP_DISCOVERABILITY_READY', true),
        'friction_review_ready' => env('ADMIN_ROADMAP_FRICTION_REVIEW_READY', true),
    ],
    'operations_workflow' => [
        'incident_ready' => env('ADMIN_ROADMAP_INCIDENT_READY', true),
        'monitoring_ready' => env('ADMIN_ROADMAP_MONITORING_READY', true),
        'provider_ready' => env('ADMIN_ROADMAP_PROVIDER_READY', true),
        'domain_ready' => env('ADMIN_ROADMAP_DOMAIN_READY', true),
        'billing_ready' => env('ADMIN_ROADMAP_BILLING_READY', true),
    ],
    'dashboard_usability' => [
        'information_density_ready' => env('ADMIN_ROADMAP_INFORMATION_DENSITY_READY', true),
        'kpi_visibility_ready' => env('ADMIN_ROADMAP_KPI_VISIBILITY_READY', true),
        'operational_awareness_ready' => env('ADMIN_ROADMAP_OPERATIONAL_AWARENESS_READY', true),
        'quick_action_ready' => env('ADMIN_ROADMAP_QUICK_ACTION_READY', true),
    ],
    'accessibility' => [
        'keyboard_navigation_ready' => env('ADMIN_ROADMAP_KEYBOARD_READY', true),
        'focus_management_ready' => env('ADMIN_ROADMAP_FOCUS_READY', true),
        'screen_reader_ready' => env('ADMIN_ROADMAP_SCREEN_READER_READY', true),
        'responsive_admin_ready' => env('ADMIN_ROADMAP_RESPONSIVE_READY', true),
    ],
    'ux' => [
        'quick_win_limit' => env('ADMIN_ROADMAP_QUICK_WIN_LIMIT', 3),
        'high_impact_limit' => env('ADMIN_ROADMAP_HIGH_IMPACT_LIMIT', 4),
        'bottleneck_limit' => env('ADMIN_ROADMAP_BOTTLENECK_LIMIT', 4),
    ],
    'roadmap' => [
        'phase_one_limit' => env('ADMIN_ROADMAP_PHASE_ONE_LIMIT', 4),
        'candidates' => [
            [
                'key' => 'admin_navigation_shortcuts',
                'title' => 'Plan admin navigation shortcuts',
                'category' => 'admin-workflow',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'operations_triage_path',
                'title' => 'Clarify operations triage path',
                'category' => 'operations-workflow',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'medium',
                'risk' => 'low',
            ],
            [
                'key' => 'dashboard_kpi_hierarchy',
                'title' => 'Define dashboard KPI hierarchy',
                'category' => 'dashboard',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'admin_accessibility_pass',
                'title' => 'Run focused admin accessibility pass',
                'category' => 'accessibility',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'admin_quick_actions_planning',
                'title' => 'Plan admin quick-action readiness',
                'category' => 'dashboard',
                'priority' => 'medium',
                'impact' => 'medium',
                'complexity' => 'medium',
                'risk' => 'medium',
            ],
        ],
    ],
];
