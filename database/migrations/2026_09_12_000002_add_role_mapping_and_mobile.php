<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Unifies the user form's role picker into a single dropdown: the 3 original
 * base roles (superadmin/support/billing) become locked "system" rows in
 * sa_roles so they sit in the same list as any custom role. Each sa_roles row
 * carries base_role, which is what actually gets written to the still-ENUM
 * super_admins.role column — that column can't be removed, everything's
 * existing access control runs on it.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sa_roles', function (Blueprint $table) {
            $table->string('base_role', 20)->nullable()->after('slug');
            $table->boolean('is_system')->default(false)->after('base_role');
        });

        Schema::table('super_admins', function (Blueprint $table) {
            $table->string('mobile', 20)->nullable()->after('email');
        });

        $map = ['superadmin' => 'Superadmin', 'support' => 'Support', 'billing' => 'Billing'];
        foreach ($map as $slug => $name) {
            $id = DB::table('sa_roles')->where('slug', $slug)->value('id');
            if (! $id) {
                $id = DB::table('sa_roles')->insertGetId([
                    'name' => $name,
                    'slug' => $slug,
                    'base_role' => $slug,
                    'is_system' => true,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
            // Backfill any existing user of this base role that doesn't have a role_id yet.
            DB::table('super_admins')->where('role', $slug)->whereNull('role_id')->update(['role_id' => $id]);
        }
    }

    public function down(): void
    {
        DB::table('sa_roles')->where('is_system', true)->delete();

        Schema::table('super_admins', function (Blueprint $table) {
            $table->dropColumn('mobile');
        });
        Schema::table('sa_roles', function (Blueprint $table) {
            $table->dropColumn(['base_role', 'is_system']);
        });
    }
};
