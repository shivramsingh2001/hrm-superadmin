<?php

/**
 * Defaults applied by App\Services\ProvisioningService when a new tenant is
 * created. A trained operator can override most of these in the provisioning
 * form; this file is the fallback.
 */
return [

    // Subdomains that can never be used by a tenant.
    'subdomain_blocklist' => [
        'www', 'api', 'app', 'admin', 'superadmin', 'mail', 'smtp', 'ftp', 'ns1', 'ns2',
        'dashboard', 'portal', 'status', 'blog', 'help', 'support', 'docs', 'cdn', 'static',
        'assets', 'test', 'staging', 'dev', 'demo', 'billing', 'account', 'accounts', 'login',
    ],

    // Default operational config -> tenant_default_config + tenants columns.
    'default_config' => [
        'working_days' => ['mon', 'tue', 'wed', 'thu', 'fri'],
        'working_hours_per_day' => 8.00,
        'grace_minutes' => 10,
        'overtime_threshold_minutes' => 60,
        'weekoff_days' => ['sat', 'sun'],
    ],

    // Default company shift created during provisioning (name kept generic).
    'default_shift' => [
        'name' => 'General Shift',
        'start_time' => '09:30:00',
        'end_time' => '18:30:00',
        'grace_minutes' => 10,
        'break_time' => 60,
        'color_code' => '#2563eb',
    ],

    // Leave types seeded into leave_types (credit_type: weekly|monthly|yearly|no).
    // 'code' is a stable, tenant-scoped flag the main app uses to identify
    // system-managed types (e.g. 'lwp') instead of hardcoding numeric ids —
    // leave it unset for ordinary types.
    'leave_types' => [
        ['name' => 'Casual Leave', 'credit_type' => 'yearly', 'credit_value' => 12, 'description' => 'General personal leave.'],
        ['name' => 'Sick Leave', 'credit_type' => 'yearly', 'credit_value' => 12, 'description' => 'Illness / medical leave.'],
        ['name' => 'Earned Leave', 'credit_type' => 'monthly', 'credit_value' => 1.5, 'description' => 'Accrues monthly, encashable.'],
        ['name' => 'Unpaid Leave', 'credit_type' => 'no', 'credit_value' => 0, 'description' => 'Leave without pay.', 'code' => 'lwp'],
    ],

    // System roles + (module, action) grants seeded into roles / role_permissions.
    // action: view|create|edit|delete|approve|export. 'all' expands to every action.
    'roles' => [
        'admin' => [
            'name' => 'Administrator', 'is_system' => true,
            'permissions' => [
                'attendance' => 'all', 'leave' => 'all', 'payroll' => 'all', 'tasks' => 'all',
                'projects' => 'all', 'recruitment' => 'all', 'onboarding' => 'all', 'offboarding' => 'all',
                'expenses' => 'all', 'loans' => 'all', 'meetings' => 'all', 'announcements' => 'all',
                'reports' => 'all', 'settings' => 'all',
            ],
        ],
        'hr' => [
            'name' => 'HR', 'is_system' => true,
            'permissions' => [
                'attendance' => 'all', 'leave' => 'all', 'payroll' => ['view'], 'tasks' => ['view'],
                'recruitment' => 'all', 'onboarding' => 'all', 'offboarding' => 'all',
                'loans' => ['view'], 'meetings' => 'all', 'announcements' => 'all',
                'reports' => ['view', 'export'],
            ],
        ],
        'manager' => [
            'name' => 'Manager', 'is_system' => true,
            'permissions' => [
                'attendance' => ['view', 'approve'], 'leave' => ['view', 'approve'],
                'tasks' => 'all', 'projects' => ['view'], 'recruitment' => ['view'],
                'meetings' => 'all', 'reports' => ['view'],
            ],
        ],
        'employee' => [
            'name' => 'Employee', 'is_system' => true,
            'permissions' => [
                'attendance' => ['view'], 'leave' => ['view', 'create'],
                'tasks' => ['view', 'edit'], 'payroll' => ['view'], 'loans' => ['view', 'create'],
                'meetings' => ['view'], 'announcements' => ['view'],
            ],
        ],
        'finance' => [
            'name' => 'Finance', 'is_system' => false,
            'permissions' => [
                'payroll' => 'all', 'loans' => 'all', 'expenses' => 'all',
                'reports' => ['view', 'export'],
            ],
        ],
        'recruiter' => [
            'name' => 'Recruiter', 'is_system' => false,
            'permissions' => ['recruitment' => 'all', 'onboarding' => ['view', 'create', 'edit']],
        ],
    ],

    'all_actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export'],

    // Employee-id prefix for the seeded tenant-admin user (matches HRM convention).
    'employee_id_prefix' => 'SH',
];
