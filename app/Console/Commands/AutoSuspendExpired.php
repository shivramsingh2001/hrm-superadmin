<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\TenantLifecycleService;
use Illuminate\Console\Command;

/**
 * Suspends tenants whose subscription lapsed more than `auto_suspend_grace_days`
 * ago with no renewal. Also suspends trials past trial_ends_at + grace.
 */
class AutoSuspendExpired extends Command
{
    protected $signature = 'tenants:auto-suspend {--dry-run}';

    protected $description = 'Suspend tenants with a lapsed subscription or expired trial.';

    public function handle(TenantLifecycleService $lifecycle): int
    {
        $grace = (int) config('lifecycle.auto_suspend_grace_days', 3);
        $cutoff = now()->subDays($grace);
        $n = 0;

        // Paid subscriptions whose end_date has passed.
        $lapsedTenantIds = TenantSubscription::whereNotNull('end_date')
            ->whereDate('end_date', '<', $cutoff->toDateString())
            ->whereIn('status', ['active'])
            ->pluck('tenant_id')->unique();

        // Trials past their end.
        $trialTenantIds = Tenant::whereNotNull('trial_ends_at')
            ->where('trial_ends_at', '<', $cutoff)
            ->where('status', 'active')
            ->pluck('id');

        $ids = $lapsedTenantIds->merge($trialTenantIds)->unique();

        foreach (Tenant::whereIn('id', $ids)->where('status', 'active')->get() as $t) {
            // Skip if a renewal payment landed after the lapse.
            $renewed = TenantSubscription::where('tenant_id', $t->id)
                ->where(fn ($q) => $q->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
                ->whereIn('status', ['active', 'trial'])->exists();
            if ($renewed) {
                continue;
            }

            $reason = $trialTenantIds->contains($t->id) ? 'trial_ended' : 'non_payment';
            $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "suspend {$t->subdomain} ({$reason})");
            if (! $this->option('dry-run')) {
                $lifecycle->suspend($t, $reason, 'Auto-suspended by scheduler', null);
            }
            $n++;
        }

        $this->info(($this->option('dry-run') ? 'Would suspend ' : 'Suspended ') . "{$n} tenant(s).");

        return self::SUCCESS;
    }
}
