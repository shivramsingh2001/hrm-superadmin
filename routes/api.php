<?php

use App\Http\Controllers\Api\AuditLogController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\EnquiryController;
use App\Http\Controllers\Api\PlanController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\TenantController;
use App\Http\Controllers\HealthController;
use Illuminate\Support\Facades\Route;

/*
 * Super Admin Panel API — /api/v1/super-admin/*
 * Bearer JWT signed with SUPER_ADMIN_JWT_SECRET (distinct from APP_KEY and any
 * tenant-HRM JWT). Parity with the Blade panel; the panel stays the fallback.
 */
Route::prefix('v1/super-admin')->group(function () {

    Route::post('auth/login', [AuthController::class, 'login']);
    Route::post('auth/refresh', [AuthController::class, 'refresh']);
    Route::get('health', [HealthController::class, 'check']);

    Route::middleware('api.jwt')->group(function () {
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        Route::get('dashboard', [DashboardController::class, 'index']);

        // Enquiries
        Route::get('enquiries', [EnquiryController::class, 'index']);
        Route::post('enquiries', [EnquiryController::class, 'store']);
        Route::get('enquiries/{inquiry}', [EnquiryController::class, 'show']);
        Route::patch('enquiries/{inquiry}/status', [EnquiryController::class, 'updateStatus']);
        Route::post('enquiries/{inquiry}/payment', [EnquiryController::class, 'payment'])->middleware('api.sa_role:billing');

        // Tenants
        Route::get('tenants', [TenantController::class, 'index']);
        Route::get('tenants/{tenant}', [TenantController::class, 'show']);
        Route::get('tenants/{tenant}/features', [TenantController::class, 'features']);
        Route::post('tenants/{tenant}/impersonate', [TenantController::class, 'impersonate'])->middleware('api.sa_role:support');

        Route::middleware('api.sa_role:superadmin')->group(function () {
            Route::post('tenants', [TenantController::class, 'store']);
            Route::put('tenants/{tenant}/features', [TenantController::class, 'updateFeatures']);
            Route::post('tenants/{tenant}/suspend', [TenantController::class, 'suspend']);
            Route::post('tenants/{tenant}/activate', [TenantController::class, 'activate']);
            Route::post('tenants/{tenant}/change-plan', [TenantController::class, 'changePlan']);
            Route::patch('tenants/{tenant}/limits', [TenantController::class, 'updateLimits']);
        });

        // Plans
        Route::get('plans', [PlanController::class, 'index']);
        Route::middleware('api.sa_role:billing')->group(function () {
            Route::post('plans', [PlanController::class, 'store']);
            Route::put('plans/{plan}', [PlanController::class, 'update']);
            Route::delete('plans/{plan}', [PlanController::class, 'destroy']);
        });

        // RBAC
        Route::get('roles', [RoleController::class, 'index']);
        Route::middleware('api.sa_role:superadmin')->group(function () {
            Route::post('roles', [RoleController::class, 'store']);
            Route::put('roles/{role}/permissions', [RoleController::class, 'updatePermissions']);
        });

        // Audit
        Route::get('audit-logs', [AuditLogController::class, 'index']);
    });
});
