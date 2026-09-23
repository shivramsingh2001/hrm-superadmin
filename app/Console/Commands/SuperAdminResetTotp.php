<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;

/**
 * Recovery: clear an account's TOTP so it re-enrols on next login. TOTP cannot
 * be disabled from the UI — this CLI path is the only reset.
 */
class SuperAdminResetTotp extends Command
{
    protected $signature = 'superadmin:reset-totp {email}';

    protected $description = 'Reset a super admin account\'s TOTP (forces re-enrolment).';

    public function handle(): int
    {
        $admin = SuperAdmin::where('email', $this->argument('email'))->first();
        if (! $admin) {
            $this->error('No such super admin.');

            return self::FAILURE;
        }

        $admin->forceFill(['totp_secret' => null, 'totp_enabled_at' => null])->save();
        $this->info("TOTP reset for {$admin->email} — they will re-enrol at next sign-in.");

        return self::SUCCESS;
    }
}
