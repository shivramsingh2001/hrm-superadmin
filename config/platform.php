<?php

/**
 * Links to the tenant HRM app that shares this database.
 */
return [
    // Base URL of the HRM app (for the cross-app feature-cache bust call).
    'hrm_internal_url' => env('HRM_INTERNAL_URL', 'http://127.0.0.1:8000'),

    // Shared secret sent as X-Internal-Token; must match the HRM's
    // SUPERADMIN_INTERNAL_TOKEN. Empty = skip the cross-app call (rely on TTL).
    'internal_token' => env('SUPERADMIN_INTERNAL_TOKEN', ''),

    // Public tenant domain used to build login URLs shown in the panel / emails.
    'tenant_domain' => env('TENANT_DOMAIN', 'hrmplatform.com'),

    // How long an impersonation session is valid (hard cap, regardless of activity).
    'impersonation_ttl_minutes' => (int) env('IMPERSONATION_TTL_MINUTES', 60),

    // Where the HRM's "consume" endpoint lives. In prod this would be
    // https://{subdomain}.{tenant_domain}; locally the HRM resolves the tenant
    // from the ?tenant= query param that start() appends.
    'hrm_web_url' => env('HRM_WEB_URL', env('HRM_INTERNAL_URL', 'http://127.0.0.1:8000')),

    // --- Security (Phase 7) ---------------------------------------------------
    // HMAC key for tamper-evident audit_logs. Shared with the HRM app.
    'audit_hmac_key' => env('AUDIT_LOG_HMAC_KEY', ''),

    // Global IP allowlist (comma list). Empty = no global restriction; a
    // per-account super_admins.allowed_ips list still applies.
    'ip_whitelist' => array_filter(array_map('trim', explode(',', (string) env('SUPER_ADMIN_IP_WHITELIST', '')))),

    // Hard session cap regardless of activity (minutes). Idle timeout is
    // SESSION_LIFETIME.
    'session_hard_minutes' => (int) env('SUPER_ADMIN_SESSION_HARD_MINUTES', 720),

    // --- API (Phase 9) -----------------------------------------------------
    // JWT for /api/v1/super-admin/*. MUST differ from APP_KEY and from any
    // tenant-HRM JWT secret.
    'jwt' => [
        'secret' => env('SUPER_ADMIN_JWT_SECRET', ''),
        'access_ttl' => (int) env('SUPER_ADMIN_JWT_TTL', 3600),          // 1 h
        'refresh_ttl' => (int) env('SUPER_ADMIN_JWT_REFRESH_TTL', 43200), // 12 h
        'issuer' => 'hrm-superadmin',
    ],
];
