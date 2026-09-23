<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-only (sa_ prefix). Custom roles for THIS panel's own users — layered
 * alongside the existing super_admins.role ENUM (superadmin/support/billing),
 * which stays the required base role. role_id is an optional, finer-grained
 * module permission set on top of it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sa_roles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->string('description', 500)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sa_role_permissions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('role_id')->index();
            $table->string('module', 60);
            $table->string('action', 20); // view|create|edit|delete
            $table->timestamps();

            $table->unique(['role_id', 'module', 'action']);
        });

        Schema::table('super_admins', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->after('role');
        });
    }

    public function down(): void
    {
        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn('role_id');
        });
        Schema::dropIfExists('sa_role_permissions');
        Schema::dropIfExists('sa_roles');
    }
};
