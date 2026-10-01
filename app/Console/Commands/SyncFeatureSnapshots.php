<?php

namespace App\Console\Commands;

use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\FeatureService;
use Illuminate\Console\Command;

/**
 * One-off backfill: make every active subscription's features_snapshot equal
 * the tenant's effective feature set (all registered keys, overrides applied)
 * — the state FeatureService::syncSnapshot() keeps going forward. Effective
 * features do not change; only the snapshot row is brought in line.
 */
class SyncFeatureSnapshots extends Command
{
    protected $signature = 'features:sync-snapshots {--tenant= : Only this tenant id} {--dry-run}';

    protected $description = 'Write each tenant\'s effective features into its active subscription snapshot.';

    public function handle(FeatureService $features): int
    {
        $query = Tenant::query()->orderBy('id');
        if ($this->option('tenant')) {
            $query->whereKey((int) $this->option('tenant'));
        }

        $n = 0;
        foreach ($query->get(['id', 'subdomain']) as $tenant) {
            $sub = TenantSubscription::query()->where('tenant_id', $tenant->id)->active()->first();
            if (! $sub) {
                continue;
            }

            $before = $sub->features_snapshot;
            $matrix = $features->matrixForTenant($tenant->id);
            $effective = array_map(fn ($f) => (bool) $f['effective'], $matrix);

            if ($before === $effective) {
                continue;
            }

            $this->line(($this->option('dry-run') ? '[dry-run] ' : '') . "sync {$tenant->subdomain} (#{$tenant->id}): "
                . count(array_filter($effective)) . ' enabled');
            $n++;

            if (! $this->option('dry-run')) {
                $features->bust($tenant->id); // syncSnapshot() + cache drop
            }
        }

        $this->info("{$n} subscription snapshot(s) " . ($this->option('dry-run') ? 'would be ' : '') . 'synced.');

        return self::SUCCESS;
    }
}
