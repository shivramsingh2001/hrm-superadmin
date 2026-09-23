<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use Illuminate\Console\Command;

class SuperAdminList extends Command
{
    protected $signature = 'superadmin:list';

    protected $description = 'List platform super admin accounts.';

    public function handle(): int
    {
        $rows = SuperAdmin::orderBy('id')->get(['id', 'name', 'email', 'role', 'is_active', 'last_login_at']);

        $this->table(
            ['ID', 'Name', 'Email', 'Role', 'Active', 'Last login'],
            $rows->map(fn ($a) => [
                $a->id, $a->name, $a->email, $a->role,
                $a->is_active ? 'yes' : 'no',
                $a->last_login_at?->diffForHumans() ?? '—',
            ]),
        );

        return self::SUCCESS;
    }
}
