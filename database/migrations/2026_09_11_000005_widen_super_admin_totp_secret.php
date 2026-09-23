<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * `super_admins` is used only by this app (the HRM has no super-admin concept),
 * so widening its own column is a panel-owned change. Laravel's encrypted-string
 * envelope (~230+ chars) overflows the original VARCHAR(255).
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE super_admins MODIFY totp_secret VARCHAR(512) NULL');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE super_admins MODIFY totp_secret VARCHAR(255) NULL');
    }
};
