<?php

declare(strict_types=1);

return [
    'roles' => [
        'admin' => [
            'view_admin',
            'manage_organizations',
            'assign_owners',
            'approve_transactions',
            'approve_expense_requests',
            'manage_own_organization',
            'submit_expense_requests',
            'join_organizations',
            'view_audit_logs',
        ],
        'owner' => [
            'manage_own_organization',
            'submit_expense_requests',
            'join_organizations',
        ],
        'student' => [
            'join_organizations',
        ],
    ],
];
