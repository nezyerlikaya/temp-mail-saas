<?php

return [
    'groups' => [
        'users' => [
            'users.view' => 'View users',
            'users.update' => 'Update users',
            'users.suspend' => 'Suspend users',
        ],
        'staff' => [
            'staff.view' => 'View staff',
            'staff.manage' => 'Manage staff',
        ],
        'system' => [
            'system.view' => 'View system',
            'system.manage' => 'Manage system',
        ],
        'operations' => [
            'operations.view' => 'View operations center',
        ],
        'health' => [
            'health.view' => 'View health center',
        ],
        'queue' => [
            'queue.view' => 'View queue center',
        ],
        'content' => [
            'content.view' => 'View content',
            'content.manage' => 'Manage content',
        ],
        'seo' => [
            'seo.view' => 'View SEO',
            'seo.manage' => 'Manage SEO',
        ],
        'mail' => [
            'mail.view' => 'View mail',
            'mail.quarantine' => 'Quarantine mail',
        ],
        'domains' => [
            'domains.view' => 'View domains',
            'domains.manage' => 'Manage domains',
        ],
        'abuse' => [
            'abuse.view' => 'View abuse',
            'abuse.manage' => 'Manage abuse',
        ],
        'billing' => [
            'billing.view' => 'View billing center',
        ],
        'audit' => [
            'audit.view' => 'View audit center',
        ],
        'settings' => [
            'settings.view' => 'View settings',
            'settings.manage' => 'Manage settings',
        ],
        'localization' => [
            'localization.view' => 'View localization center',
            'localization.manage' => 'Manage languages and translations',
            'localization.import' => 'Import translations',
            'localization.export' => 'Export translations',
        ],
    ],

    'roles' => [
        'super_admin' => [
            'name' => 'Super Admin',
            'permissions' => ['*'],
        ],
        'admin' => [
            'name' => 'Admin',
            'permissions' => [
                'users.view',
                'users.update',
                'users.suspend',
                'staff.view',
                'system.view',
                'operations.view',
                'health.view',
                'queue.view',
                'content.view',
                'content.manage',
                'seo.view',
                'seo.manage',
                'mail.view',
                'mail.quarantine',
                'domains.view',
                'domains.manage',
                'abuse.view',
                'abuse.manage',
                'billing.view',
                'audit.view',
                'settings.view',
                'localization.view',
                'localization.manage',
                'localization.import',
                'localization.export',
            ],
        ],
        'support' => [
            'name' => 'Support',
            'permissions' => [
                'users.view',
                'operations.view',
                'health.view',
                'queue.view',
                'mail.view',
                'domains.view',
                'abuse.view',
                'billing.view',
                'audit.view',
                'settings.view',
                'localization.view',
                'localization.import',
                'localization.export',
            ],
        ],
        'moderator' => [
            'name' => 'Moderator',
            'permissions' => [
                'users.view',
                'content.view',
                'content.manage',
                'abuse.view',
                'abuse.manage',
                'mail.view',
                'mail.quarantine',
            ],
        ],
    ],
];
