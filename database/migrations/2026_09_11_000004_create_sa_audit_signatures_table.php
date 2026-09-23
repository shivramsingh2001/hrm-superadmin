<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-only (sa_ prefix). Tamper-evident HMAC per audit_logs row — kept in a
 * side table so the shared audit_logs schema is untouched.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sa_audit_signatures', function (Blueprint $table) {
            $table->unsignedBigInteger('audit_log_id')->primary();
            $table->string('hmac', 64);
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sa_audit_signatures');
    }
};
