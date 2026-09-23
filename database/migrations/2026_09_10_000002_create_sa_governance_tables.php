<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Panel-only (sa_ prefix). 4.2 bulk feature-override templates + 4.3 plan
 * version history. The HRM app never reads these.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sa_feature_override_templates', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->json('map');            // { feature_key: bool, ... }
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });

        Schema::create('sa_plan_versions', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('plan_id')->index();
            $table->unsignedInteger('version');
            $table->string('name');
            $table->decimal('price', 10, 2)->nullable();
            $table->string('pricing_type', 40)->nullable();
            $table->decimal('price_per_employee', 10, 2)->nullable();
            $table->string('billing_cycle', 20)->nullable();
            $table->integer('max_employees')->nullable();
            $table->json('features');
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('changed_by')->nullable();
            $table->timestamp('created_at')->nullable();

            $table->unique(['plan_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sa_plan_versions');
        Schema::dropIfExists('sa_feature_override_templates');
    }
};
