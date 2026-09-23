<?php

/**
 * Canonical modules and actions for the tenant permission matrix (SRS §9.2).
 * role_permissions.action is an ENUM — keep `actions` in sync with the column.
 */
return [
    'modules' => [
        'employee' => 'Employee',
        'attendance' => 'Attendance',
        'leave' => 'Leave',
        'payroll' => 'Payroll',
        'tasks' => 'Tasks',
        'projects' => 'Projects',
        'recruitment' => 'Recruitment',
        'onboarding' => 'Onboarding',
        'offboarding' => 'Offboarding',
        'expenses' => 'Expenses',
        'loans' => 'Loans',
        'meetings' => 'Meetings',
        'announcements' => 'Announcements',
        'reports' => 'Reports',
        'settings' => 'Settings',
    ],

    'actions' => ['view', 'create', 'edit', 'delete', 'approve', 'export', 'manage'],

    // own = just the user's own record; team = records of people reporting
    // to them; company = every record in the tenant. Stored per-row on
    // role_permissions.scope; only meaningful for modules where "whose
    // record" applies.
    'scopes' => [
        'own' => 'Own records only',
        'team' => 'Team records',
        'company' => 'All company records',
    ],

    // Slugs that may never be edited or deleted from the panel.
    'system_slugs' => ['admin', 'hr', 'manager', 'employee'],
];
