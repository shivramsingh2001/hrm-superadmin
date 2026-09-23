<?php

/**
 * Canonical modules and actions for the Super Admin Panel's OWN custom-role
 * permission matrix (distinct from config/rbac.php, which is the tenant-facing
 * matrix). Phase 1: these permissions are recorded and assignable but not yet
 * consulted by any route/controller — see the "Users & Custom Roles" plan.
 */
return [
    'modules' => [
        'dashboard' => 'Dashboard',
        'tenants' => 'Tenants',
        'enquiries' => 'Enquiries',
        'plans' => 'Subscription Plans',
        'feature_registry' => 'Feature Registry',
        'feature_templates' => 'Feature Templates',
        'impersonation' => 'Impersonation',
        'tenant_health' => 'Tenant Health',
        'audit_logs' => 'Audit Logs',
        'api_console' => 'API Console',
        'users' => 'Panel Users',
        'roles' => 'Panel Roles',
    ],

    'actions' => ['view', 'create', 'edit', 'delete'],
];
