<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EnquiryController;
use App\Http\Controllers\FeatureRegistryController;
use App\Http\Controllers\FeatureTemplateController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\ProvisioningController;
use App\Http\Controllers\SuperAdminRoleController;
use App\Http\Controllers\SuperAdminUserController;
use App\Http\Controllers\TenantController;
use Illuminate\Support\Facades\Route;

// Public lead-capture form — no auth.
Route::get('/contact', [ContactController::class, 'show'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:10,1');

// Ops probe — no auth.
Route::get('/health', [\App\Http\Controllers\HealthController::class, 'check']);

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'login']);
});

// Second-factor step — reached after password auth, before the session is
// established. Guarded by the pending-id in the session.
Route::middleware('throttle:20,1')->group(function () {
    Route::get('/2fa/setup', [\App\Http\Controllers\Auth\TotpController::class, 'setup'])->name('totp.setup');
    Route::post('/2fa/setup', [\App\Http\Controllers\Auth\TotpController::class, 'enable'])->name('totp.enable');
    Route::get('/2fa', [\App\Http\Controllers\Auth\TotpController::class, 'verify'])->name('totp.verify');
    Route::post('/2fa', [\App\Http\Controllers\Auth\TotpController::class, 'check'])->name('totp.check');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::get('/tenant-health', [\App\Http\Controllers\HealthController::class, 'index'])->name('health.index');
    Route::get('/api-console', [\App\Http\Controllers\ApiConsoleController::class, 'index'])->name('api-console');

    // Enquiries → payment
    Route::get('/enquiries', [EnquiryController::class, 'index'])->name('enquiries.index');
    Route::get('/enquiries/export', [EnquiryController::class, 'export'])->name('enquiries.export');
    Route::get('/enquiries/{inquiry}', [EnquiryController::class, 'show'])->name('enquiries.show');
    Route::post('/enquiries/{inquiry}/status', [EnquiryController::class, 'updateStatus'])->name('enquiries.status');
    Route::post('/enquiries/{inquiry}/note', [EnquiryController::class, 'addNote'])->name('enquiries.note');
    Route::post('/enquiries/{inquiry}/assign', [EnquiryController::class, 'assign'])->name('enquiries.assign');
    Route::post('/enquiries/{inquiry}/payment', [EnquiryController::class, 'recordPayment'])
        ->middleware('sa_role:billing')->name('enquiries.payment');

    // Tenant provisioning (superadmin only)
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::get('/tenants/create', [ProvisioningController::class, 'create'])->name('tenants.create');
        Route::post('/tenants', [ProvisioningController::class, 'store'])->name('tenants.store');
    });

    // Tenants
    Route::get('/tenants', [TenantController::class, 'index'])->name('tenants.index');
    Route::get('/tenants/{tenant}', [TenantController::class, 'show'])->name('tenants.show');
    Route::post('/tenants/{tenant}/features', [TenantController::class, 'updateFeature'])->name('tenants.features');
    Route::post('/tenants/{tenant}/payments', [TenantController::class, 'recordPayment'])->name('tenants.payments');
    // Lifecycle — suspension / plan / limits / deletion are superadmin-only;
    // renew / trial-convert also allow the billing role (they take a payment).
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::get('/tenants/{tenant}/edit', [TenantController::class, 'edit'])->name('tenants.edit');
        Route::put('/tenants/{tenant}', [TenantController::class, 'update'])->name('tenants.update');
        Route::post('/tenants/{tenant}/suspend', [TenantController::class, 'suspend'])->name('tenants.suspend');
        Route::post('/tenants/{tenant}/activate', [TenantController::class, 'activate'])->name('tenants.activate');
        Route::post('/tenants/{tenant}/change-plan', [TenantController::class, 'changePlan'])->name('tenants.change-plan');
        Route::post('/tenants/{tenant}/limits', [TenantController::class, 'updateLimits'])->name('tenants.limits');
        Route::post('/tenants/{tenant}/tracking', [TenantController::class, 'updateTracking'])->name('tenants.tracking');
        Route::post('/tenants/{tenant}/duration', [TenantController::class, 'setDuration'])->name('tenants.duration');
        Route::post('/tenants/{tenant}/modules', [TenantController::class, 'updateModules'])->name('tenants.modules');
        Route::post('/tenants/{tenant}/extend-trial', [TenantController::class, 'extendTrial'])->name('tenants.extend-trial');
        Route::post('/tenants/{tenant}/request-deletion', [TenantController::class, 'requestDeletion'])->name('tenants.request-deletion');
        Route::post('/tenants/{tenant}/cancel-deletion', [TenantController::class, 'cancelDeletion'])->name('tenants.cancel-deletion');
    });
    Route::middleware('sa_role:billing')->group(function () {
        Route::post('/tenants/{tenant}/renew', [TenantController::class, 'renew'])->name('tenants.renew');
        Route::post('/tenants/{tenant}/convert-trial', [TenantController::class, 'convertTrial'])->name('tenants.convert-trial');
    });

    // Subscription plans
    Route::get('/plans', [PlanController::class, 'index'])->name('plans.index');
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::get('/plans/create', [PlanController::class, 'create'])->name('plans.create');
        Route::post('/plans', [PlanController::class, 'store'])->name('plans.store');
        Route::get('/plans/{plan}/edit', [PlanController::class, 'edit'])->name('plans.edit');
        Route::put('/plans/{plan}', [PlanController::class, 'update'])->name('plans.update');
        Route::delete('/plans/{plan}', [PlanController::class, 'destroy'])->name('plans.destroy');
        Route::patch('/plans/{plan}/toggle', [PlanController::class, 'toggle'])->name('plans.toggle');
        Route::post('/plans/{plan}/revert/{version}', [PlanController::class, 'revert'])->name('plans.revert');
    });

    // Feature registry
    Route::get('/feature-registry', [FeatureRegistryController::class, 'index'])->name('feature-registry.index');
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::post('/feature-registry/reseed', [FeatureRegistryController::class, 'reseed'])->name('feature-registry.reseed');
        Route::post('/feature-registry/{key}', [FeatureRegistryController::class, 'update'])->name('feature-registry.update');
    });

    // Impersonation & support
    Route::get('/impersonation', [\App\Http\Controllers\ImpersonationController::class, 'index'])->name('impersonation.index');
    Route::post('/impersonation/{token}/end', [\App\Http\Controllers\ImpersonationController::class, 'end'])->name('impersonation.end');
    Route::post('/tenants/{tenant}/impersonate', [\App\Http\Controllers\ImpersonationController::class, 'start'])
        ->middleware('sa_role:support')->name('tenants.impersonate');
    Route::post('/tenants/{tenant}/notes', [TenantController::class, 'addNote'])->name('tenants.notes.store');
    Route::delete('/tenants/{tenant}/notes/{note}', [TenantController::class, 'deleteNote'])->name('tenants.notes.destroy');

    // Tenant RBAC (superadmin manages roles on a tenant's behalf)
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::post('/tenants/{tenant}/roles', [\App\Http\Controllers\RoleController::class, 'store'])->name('tenants.roles.store');
        Route::post('/tenants/{tenant}/roles/{role}/permissions', [\App\Http\Controllers\RoleController::class, 'updatePermissions'])->name('tenants.roles.permissions');
        Route::post('/tenants/{tenant}/roles/{role}/clone', [\App\Http\Controllers\RoleController::class, 'clone'])->name('tenants.roles.clone');
        Route::delete('/tenants/{tenant}/roles/{role}', [\App\Http\Controllers\RoleController::class, 'destroy'])->name('tenants.roles.destroy');
    });

    // Bulk feature-override templates
    Route::get('/feature-templates', [FeatureTemplateController::class, 'index'])->name('feature-templates.index');
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::get('/feature-templates/create', [FeatureTemplateController::class, 'create'])->name('feature-templates.create');
        Route::post('/feature-templates', [FeatureTemplateController::class, 'store'])->name('feature-templates.store');
        Route::delete('/feature-templates/{template}', [FeatureTemplateController::class, 'destroy'])->name('feature-templates.destroy');
        Route::get('/feature-templates/{template}/apply', [FeatureTemplateController::class, 'applyForm'])->name('feature-templates.apply-form');
        Route::post('/feature-templates/{template}/apply', [FeatureTemplateController::class, 'apply'])->name('feature-templates.apply');
    });

    // Audit logs
    Route::get('/audit-logs', [AuditLogController::class, 'index'])->name('audit-logs.index');
    Route::get('/audit-logs/export', [AuditLogController::class, 'export'])->name('audit-logs.export');

    // Panel users & custom roles (superadmin only)
    Route::middleware('sa_role:superadmin')->group(function () {
        Route::get('/superadmin-users', [SuperAdminUserController::class, 'index'])->name('superadmin-users.index');
        Route::get('/superadmin-users/create', [SuperAdminUserController::class, 'create'])->name('superadmin-users.create');
        Route::post('/superadmin-users', [SuperAdminUserController::class, 'store'])->name('superadmin-users.store');
        Route::get('/superadmin-users/{superadmin}/edit', [SuperAdminUserController::class, 'edit'])->name('superadmin-users.edit');
        Route::put('/superadmin-users/{superadmin}', [SuperAdminUserController::class, 'update'])->name('superadmin-users.update');
        Route::post('/superadmin-users/{superadmin}/toggle', [SuperAdminUserController::class, 'toggle'])->name('superadmin-users.toggle');
        Route::post('/superadmin-users/{superadmin}/reset-totp', [SuperAdminUserController::class, 'resetTotp'])->name('superadmin-users.reset-totp');

        Route::get('/superadmin-roles', [SuperAdminRoleController::class, 'index'])->name('superadmin-roles.index');
        Route::post('/superadmin-roles', [SuperAdminRoleController::class, 'store'])->name('superadmin-roles.store');
        Route::put('/superadmin-roles/{role}', [SuperAdminRoleController::class, 'update'])->name('superadmin-roles.update');
        Route::post('/superadmin-roles/{role}/permissions', [SuperAdminRoleController::class, 'updatePermissions'])->name('superadmin-roles.permissions');
        Route::delete('/superadmin-roles/{role}', [SuperAdminRoleController::class, 'destroy'])->name('superadmin-roles.destroy');
    });

    // Notifications
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/{notification}/read', [NotificationController::class, 'markRead'])->name('notifications.read');
});
