<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Strategic Platform Governance
    |--------------------------------------------------------------------------
    |
    | Governance readiness is internal and aggregate-only. No external board,
    | ERP, executive dashboard, or compliance platform integrations are used.
    |
    */

    'governance' => [
        'platform_ready' => env('GOVERNANCE_PLATFORM_READY', true),
        'policy_ready' => env('GOVERNANCE_POLICY_READY', true),
        'operational_controls_ready' => env('GOVERNANCE_OPERATIONAL_CONTROLS_READY', true),
        'maturity_ready' => env('GOVERNANCE_MATURITY_READY', true),
    ],

    'strategic_operations' => [
        'planning_ready' => env('GOVERNANCE_OPERATIONS_PLANNING_READY', true),
        'maintenance_ready' => env('GOVERNANCE_MAINTENANCE_READY', true),
        'incident_readiness' => env('GOVERNANCE_INCIDENT_READINESS', true),
        'scalability_readiness' => env('GOVERNANCE_SCALABILITY_READINESS', true),
    ],

    'risk' => [
        'operational_risk' => env('GOVERNANCE_OPERATIONAL_RISK', 'low'),
        'dependency_risk' => env('GOVERNANCE_DEPENDENCY_RISK', 'low'),
        'governance_risk' => env('GOVERNANCE_GOVERNANCE_RISK', 'low'),
        'sustainability_risk' => env('GOVERNANCE_SUSTAINABILITY_RISK', 'low'),
        'block_on_critical' => env('GOVERNANCE_BLOCK_ON_CRITICAL_RISK', true),
        'warn_on_high' => env('GOVERNANCE_WARN_ON_HIGH_RISK', true),
    ],

    'maturity' => [
        'process' => env('GOVERNANCE_PROCESS_MATURITY', 'stable'),
        'testing' => env('GOVERNANCE_TESTING_MATURITY', 'stable'),
        'monitoring' => env('GOVERNANCE_MONITORING_MATURITY', 'stable'),
        'documentation' => env('GOVERNANCE_DOCUMENTATION_MATURITY', 'stable'),
    ],

    'certification' => [
        'governance' => env('GOVERNANCE_CERTIFY_GOVERNANCE', true),
        'maturity' => env('GOVERNANCE_CERTIFY_MATURITY', true),
        'risk' => env('GOVERNANCE_CERTIFY_RISK', true),
        'sustainability' => env('GOVERNANCE_CERTIFY_SUSTAINABILITY', true),
    ],
];
