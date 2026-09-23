<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-only (sa_ prefix). Phase 8 — nightly pre-computed platform metrics and
 * per-tenant health so the dashboard is a cheap read, not a live aggregation.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sa_platform_metrics', function (Blueprint $table) {
            $table->id();
            $table->date('metric_date')->unique();
            $table->unsignedInteger('total_tenants')->default(0);
            $table->unsignedInteger('active_tenants')->default(0);
            $table->unsignedInteger('suspended_tenants')->default(0);
            $table->unsignedInteger('trial_tenants')->default(0);
            $table->unsignedInteger('open_enquiries')->default(0);
            $table->decimal('mrr', 14, 2)->default(0);
            $table->decimal('arr', 14, 2)->default(0);
            $table->unsignedInteger('new_tenants_month')->default(0);
            $table->unsignedInteger('churned_tenants_month')->default(0);
            $table->json('funnel')->nullable();            // { status: count }
            $table->json('new_tenants_by_month')->nullable(); // { "YYYY-MM": count } last 12
            $table->decimal('avg_enquiry_to_provision_days', 6, 2)->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('sa_tenant_health', function (Blueprint $table) {
            $table->unsignedBigInteger('tenant_id')->primary();
            $table->date('snapshot_date');
            $table->unsignedInteger('users_total')->default(0);
            $table->unsignedInteger('users_active')->default(0);
            $table->unsignedInteger('logins_30d')->default(0);
            $table->timestamp('last_activity_at')->nullable();
            $table->integer('seat_limit')->default(0);
            $table->decimal('seat_utilisation', 5, 2)->default(0);
            $table->boolean('is_stale')->default(false);   // no login in 14 days
            $table->timestamp('updated_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sa_tenant_health');
        Schema::dropIfExists('sa_platform_metrics');
    }
};
