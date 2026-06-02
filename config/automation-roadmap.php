<?php

return [
    'automation_review' => [
        'rule_coverage_ready' => env('AUTOMATION_ROADMAP_RULE_COVERAGE_READY', true),
        'safety_controls_ready' => env('AUTOMATION_ROADMAP_SAFETY_CONTROLS_READY', true),
        'maintainability_ready' => env('AUTOMATION_ROADMAP_MAINTAINABILITY_READY', true),
        'scalability_ready' => env('AUTOMATION_ROADMAP_SCALABILITY_READY', true),
    ],
    'intelligence_review' => [
        'scoring_ready' => env('AUTOMATION_ROADMAP_SCORING_READY', true),
        'aggregation_ready' => env('AUTOMATION_ROADMAP_AGGREGATION_READY', true),
        'trend_analysis_ready' => env('AUTOMATION_ROADMAP_TRENDS_READY', true),
        'operational_insight_ready' => env('AUTOMATION_ROADMAP_INSIGHTS_READY', true),
    ],
    'lifecycle_review' => [
        'rule_creation_ready' => env('AUTOMATION_ROADMAP_RULE_CREATION_READY', true),
        'execution_ready' => env('AUTOMATION_ROADMAP_EXECUTION_READY', true),
        'audit_ready' => env('AUTOMATION_ROADMAP_AUDIT_READY', true),
        'operations_ready' => env('AUTOMATION_ROADMAP_OPERATIONS_READY', true),
    ],
    'operational_intelligence' => [
        'abuse_ready' => env('AUTOMATION_ROADMAP_ABUSE_READY', true),
        'domain_ready' => env('AUTOMATION_ROADMAP_DOMAIN_READY', true),
        'provider_ready' => env('AUTOMATION_ROADMAP_PROVIDER_READY', true),
        'billing_ready' => env('AUTOMATION_ROADMAP_BILLING_READY', true),
        'governance_ready' => env('AUTOMATION_ROADMAP_GOVERNANCE_READY', true),
    ],
    'prioritization' => [
        'quick_win_limit' => env('AUTOMATION_ROADMAP_QUICK_WIN_LIMIT', 3),
        'high_impact_limit' => env('AUTOMATION_ROADMAP_HIGH_IMPACT_LIMIT', 4),
        'low_risk_limit' => env('AUTOMATION_ROADMAP_LOW_RISK_LIMIT', 4),
    ],
    'roadmap' => [
        'phase_one_limit' => env('AUTOMATION_ROADMAP_PHASE_ONE_LIMIT', 4),
        'candidates' => [
            [
                'key' => 'automation_rule_catalog',
                'title' => 'Plan automation rule coverage catalog',
                'category' => 'automation',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'automation_audit_guidance',
                'title' => 'Clarify automation audit guidance',
                'category' => 'lifecycle',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'intelligence_trend_review',
                'title' => 'Plan intelligence trend review improvements',
                'category' => 'intelligence',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'medium',
                'risk' => 'low',
            ],
            [
                'key' => 'operational_insight_checklist',
                'title' => 'Define operational insight checklist',
                'category' => 'operations',
                'priority' => 'medium',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'automation_scalability_review',
                'title' => 'Plan automation scalability review',
                'category' => 'automation',
                'priority' => 'medium',
                'impact' => 'medium',
                'complexity' => 'medium',
                'risk' => 'medium',
            ],
        ],
    ],
];
