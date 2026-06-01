<?php

return [
    'inbox_review' => [
        'usability_ready' => env('INBOX_ROADMAP_USABILITY_READY', true),
        'message_visibility_ready' => env('INBOX_ROADMAP_MESSAGE_VISIBILITY_READY', true),
        'polling_ready' => env('INBOX_ROADMAP_POLLING_READY', true),
        'mailbox_discoverability_ready' => env('INBOX_ROADMAP_DISCOVERABILITY_READY', true),
    ],
    'mailbox_lifecycle' => [
        'creation_ready' => env('INBOX_ROADMAP_MAILBOX_CREATION_READY', true),
        'usage_ready' => env('INBOX_ROADMAP_MAILBOX_USAGE_READY', true),
        'expiration_ready' => env('INBOX_ROADMAP_MAILBOX_EXPIRATION_READY', true),
        'cleanup_ready' => env('INBOX_ROADMAP_MAILBOX_CLEANUP_READY', true),
    ],
    'message_workflow' => [
        'arrival_ready' => env('INBOX_ROADMAP_MESSAGE_ARRIVAL_READY', true),
        'reading_ready' => env('INBOX_ROADMAP_MESSAGE_READING_READY', true),
        'attachment_ready' => env('INBOX_ROADMAP_ATTACHMENT_READY', true),
        'retention_ready' => env('INBOX_ROADMAP_MESSAGE_RETENTION_READY', true),
    ],
    'accessibility' => [
        'keyboard_navigation_ready' => env('INBOX_ROADMAP_KEYBOARD_READY', true),
        'screen_reader_ready' => env('INBOX_ROADMAP_SCREEN_READER_READY', true),
        'color_contrast_ready' => env('INBOX_ROADMAP_COLOR_CONTRAST_READY', true),
        'responsive_ready' => env('INBOX_ROADMAP_RESPONSIVE_READY', true),
    ],
    'ux' => [
        'quick_win_limit' => env('INBOX_ROADMAP_QUICK_WIN_LIMIT', 3),
        'high_impact_limit' => env('INBOX_ROADMAP_HIGH_IMPACT_LIMIT', 4),
        'low_risk_limit' => env('INBOX_ROADMAP_LOW_RISK_LIMIT', 4),
    ],
    'roadmap' => [
        'phase_one_limit' => env('INBOX_ROADMAP_PHASE_ONE_LIMIT', 4),
        'candidates' => [
            [
                'key' => 'clear_mailbox_lifecycle',
                'title' => 'Clarify mailbox lifecycle states',
                'category' => 'mailbox',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'message_arrival_feedback',
                'title' => 'Improve message arrival feedback',
                'category' => 'message-workflow',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'medium',
                'risk' => 'low',
            ],
            [
                'key' => 'accessibility_pass',
                'title' => 'Run focused inbox accessibility pass',
                'category' => 'accessibility',
                'priority' => 'high',
                'impact' => 'high',
                'complexity' => 'small',
                'risk' => 'low',
            ],
            [
                'key' => 'attachment_workflow_planning',
                'title' => 'Plan attachment workflow affordances',
                'category' => 'message-workflow',
                'priority' => 'medium',
                'impact' => 'medium',
                'complexity' => 'medium',
                'risk' => 'medium',
            ],
            [
                'key' => 'polling_expectation_copy',
                'title' => 'Define polling expectation improvements',
                'category' => 'inbox',
                'priority' => 'medium',
                'impact' => 'medium',
                'complexity' => 'small',
                'risk' => 'low',
            ],
        ],
    ],
];
