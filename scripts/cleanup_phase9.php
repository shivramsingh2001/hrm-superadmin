<?php

/*
 | Phase 9 API test-artifact cleanup.
 |
 | The auto-mode classifier blocks a multi-table cascade delete from tinker,
 | so run this yourself from the panel root:
 |
 |     php artisan tinker < scripts/cleanup_phase9.php
 |
 | It removes only rows created while verifying the Phase 9 API:
 |   - tenant 15 "API Provisioned Ltd" (subdomain api-prov) + everything provisioning wrote for it
 |   - standalone test roles 37 ("api_role", tenant 15) and 38 ("api_perm_test", tenant 12)
 |   - subscription plan 6 "API Plan 2"
 |   - inquiry 3 "API Test Co"
 |   - impersonation session 5 (tenant 15)
 |
 | Re-runnable: every delete is id/tenant scoped and a no-op once the rows are gone.
 | Adjust the ids below if your local data differs (check with the SELECTs first).
 */

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$TENANT   = 15;
$ROLES    = [37, 38];   // standalone test roles (37 is also under tenant 15, caught by the tenant sweep)
$PLAN     = 6;
$INQUIRY  = 3;

DB::transaction(function () use ($TENANT, $ROLES, $PLAN, $INQUIRY) {
    // --- standalone test roles + their permissions ---
    DB::table('role_permissions')->whereIn('role_id', $ROLES)->delete();

    // --- tenant 15: unwind everything ProvisioningService created ---
    $tenantRoleIds = DB::table('roles')->where('tenant_id', $TENANT)->pluck('id')->all();
    DB::table('role_permissions')->whereIn('role_id', $tenantRoleIds)->delete();
    DB::table('roles')->where('tenant_id', $TENANT)->delete();
    DB::table('roles')->whereIn('id', $ROLES)->delete();

    $userIds = DB::table('users')->where('tenant_id', $TENANT)->pluck('id')->all();
    DB::table('leave_balances')->whereIn('user_id', $userIds)->delete();
    DB::table('users')->where('tenant_id', $TENANT)->delete();

    DB::table('leave_types')->where('tenant_id', $TENANT)->delete();
    DB::table('shifts')->where('tenant_id', $TENANT)->delete();
    DB::table('tenant_feature_overrides')->where('tenant_id', $TENANT)->delete();
    DB::table('tenant_subscriptions')->where('tenant_id', $TENANT)->delete();
    DB::table('tenant_default_config')->where('tenant_id', $TENANT)->delete();
    DB::table('companies')->where('tenant_id', $TENANT)->delete();
    DB::table('impersonation_sessions')->where('tenant_id', $TENANT)->delete();

    if (Schema::hasTable('sa_provisioning_runs')) {
        DB::table('sa_provisioning_runs')->where('tenant_id', $TENANT)->delete();
    }
    DB::table('audit_logs')->where('tenant_id', $TENANT)->delete();
    DB::table('tenants')->where('id', $TENANT)->delete();

    // --- test plan + enquiry ---
    DB::table('subscription_plans')->where('id', $PLAN)->delete();
    DB::table('inquiries')->where('id', $INQUIRY)->delete();
});

echo 'cleanup done'.PHP_EOL;
echo '  tenant 15:            '.(DB::table('tenants')->find(15) ? 'STILL PRESENT' : 'gone').PHP_EOL;
echo '  roles 37/38:          '.DB::table('roles')->whereIn('id', [37, 38])->count().' left'.PHP_EOL;
echo '  plan 6:               '.DB::table('subscription_plans')->where('id', 6)->count().' left'.PHP_EOL;
echo '  inquiry 3:            '.DB::table('inquiries')->where('id', 3)->count().' left'.PHP_EOL;
echo '  active impersonations: '.DB::table('impersonation_sessions')->whereNull('ended_at')->count().PHP_EOL;
