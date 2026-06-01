<?php

return [
    'prioritization' => [
        'priority_weight' => env('V11_PRIORITY_WEIGHT', 3),
        'impact_weight' => env('V11_IMPACT_WEIGHT', 3),
        'effort_weight' => env('V11_EFFORT_WEIGHT', 2),
        'risk_weight' => env('V11_RISK_WEIGHT', 2),
    ],
    'risk' => [
        'block_critical' => env('V11_BLOCK_CRITICAL_RISK', true),
        'warn_high' => env('V11_WARN_HIGH_RISK', true),
    ],
    'readiness' => [
        'dependencies_ready' => env('V11_DEPENDENCIES_READY', true),
        'tests_ready' => env('V11_TESTS_READY', true),
        'documentation_ready' => env('V11_DOCUMENTATION_READY', true),
        'operations_ready' => env('V11_OPERATIONS_READY', true),
    ],
    'release_planning' => [
        'quick_win_score_minimum' => env('V11_QUICK_WIN_SCORE_MINIMUM', 8),
        'phase_one_limit' => env('V11_PHASE_ONE_LIMIT', 3),
    ],
    'candidate_review' => [
        'metadata_limit' => env('V11_CANDIDATE_METADATA_LIMIT', 20),
    ],
];
