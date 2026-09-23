<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Services\TenantLifecycleService;
use Illuminate\Console\Command;

/**
 * Hard-deletes (soft-delete + anonymise PII + block logins) tenants whose
 * deletion was requested more than `deletion_grace_days` ago and not cancelled.
 */
class ExecuteTenantDeletions extends Command
{
    protected $signature = 'tenants:execute-deletions {--dry-run}';

    protected $description = 'Anonymise and soft-delete tenants past their deletion grace period.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $cutoff = now()->subDays((int) config('lifecycle.deletion_grace_days', 30));
        $n = 0;

        $due = Tenant::whereNotNull('deletion_requested_at')
            ->where('deletion_requested_at', '<=', $cutoff)
            ->get();

        foreach ($due as $t) {
            $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "delete {$t->subdomain} (requested {$t->deletion_requested_at->format('d M Y')})");
            if (! $this->option('dry-run')) {
                $lifecycle->executeDeletion($t);
            }
            $n++;
        }

        $this->info(($this->option('dry-run') ? 'Would delete ' : 'Deleted ') . "{$n} tenant(s).");

        return self::SUCCESS;
    }
}
