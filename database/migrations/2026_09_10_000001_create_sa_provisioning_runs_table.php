<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-only (sa_ prefix). Tracks each tenant-provisioning run for idempotency,
 * the inquiry <-> tenant link (inquiries has no tenant_id), and a step timeline.
 * The HRM app never reads this.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sa_provisioning_runs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('inquiry_id')->nullable()->index();
            $table->unsignedBigInteger('tenant_id')->nullable()->index();
            $table->string('subdomain', 100)->index();
            $table->string('status', 20)->default('pending'); // pending|running|done|failed
            $table->string('failed_step', 60)->nullable();
            $table->text('error')->nullable();
            $table->json('steps')->nullable();   // ['tenant'=>id, 'company'=>id, ...]
            $table->json('input')->nullable();   // the submitted payload (no secrets)
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sa_provisioning_runs');
    }
};
