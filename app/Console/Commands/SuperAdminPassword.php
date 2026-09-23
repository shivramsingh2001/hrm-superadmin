<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

class SuperAdminPassword extends Command
{
    protected $signature = 'superadmin:set-password {email} {password?} {--name=} {--role=superadmin}';

    protected $description = 'Set (or create) a super admin account password.';

    public function handle(): int
    {
        $email = $this->argument('email');
        $password = $this->argument('password') ?: $this->secret('New password');

        if (! $password || strlen($password) < 8) {
            $this->error('Password must be at least 8 characters.');

            return self::FAILURE;
        }

        $admin = SuperAdmin::firstOrNew(['email' => $email]);
        $creating = ! $admin->exists;

        if ($creating) {
            $admin->name = $this->option('name') ?: $this->ask('Full name', 'Super Admin');
            $admin->role = $this->option('role');
            $admin->is_active = true;
        }

        $admin->password = Hash::make($password);
        $admin->save();

        $this->info(($creating ? 'Created' : 'Updated') . " {$admin->email} (role: {$admin->role}, active: " . ($admin->is_active ? 'yes' : 'no') . ').');

        return self::SUCCESS;
    }
}
