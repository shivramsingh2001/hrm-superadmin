<?php

namespace App\Console\Commands;

use App\Models\SuperAdmin;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Services\FeatureService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * One-time rollout helper: expense_management, project_management and
 * loan_management default OFF and were never enforced anywhere, so some
 * tenants may already have real data in those modules with no override
 * turning the flag on. Run this ONCE, before the route/sidebar gates for
 * those three modules go live, so nobody already using the module is locked
 * out. Not meant to run on a schedule.
 */
class BackfillFeatureUsage extends Command
{
    protected $signature = 'tenants:backfill-feature-usage {--dry-run} {--actor= : super_admins.id to attribute the override to (required unless --dry-run)}';

    protected $description = 'Grant a tenant_feature_overrides override for tenants with pre-existing Expense/Project/Loan data before those gates are enforced.';

    /** @var array<string, string> feature key => table with a tenant_id column */
    private const MODULES = [
        'expense_management' => 'expenses',
        'project_management' => 'projects',
        'loan_management' => 'loans',
    ];

    public function handle(FeatureService $features): int
    {
        $dryRun = (bool) $this->option('dry-run');

        $actorId = null;
        if (! $dryRun) {
            $actorId = (int) $this->option('actor');
            if (! $actorId || ! SuperAdmin::whereKey($actorId)->exists()) {
                $this->error('Pass --actor=<super_admins.id> to attribute this backfill (or --dry-run to preview only).');

                return self::FAILURE;
            }
        }

        $n = 0;

        foreach (self::MODULES as $key => $table) {
            $tenantIds = DB::table($table)->whereNotNull('tenant_id')->distinct()->pluck('tenant_id');

            foreach ($tenantIds as $tenantId) {
                $tenant = Tenant::find($tenantId);
                if (! $tenant) {
                    continue;
                }

                if ($features->enabled((int) $tenantId, $key)) {
                    continue; // already enabled via plan or an existing override — nothing to do
                }

                $this->line(($dryRun ? '[dry-run] ' : '') . "grant {$key} to {$tenant->subdomain} (tenant #{$tenantId}, has {$table} data)");
                $n++;

                if ($dryRun) {
                    continue;
                }

                TenantFeatureOverride::updateOrCreate(
                    ['tenant_id' => $tenantId, 'feature_key' => $key],
                    [
                        'is_enabled' => true,
                        'reason' => 'Backfilled: pre-existing data found before this feature was enforced',
                        'overridden_by' => $actorId,
                    ]
                );
                $features->bust((int) $tenantId);
            }
        }

        $this->info(($dryRun ? 'Would grant ' : 'Granted ') . "{$n} override(s).");

        return self::SUCCESS;
    }
}
