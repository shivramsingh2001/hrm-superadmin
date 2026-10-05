<?php

namespace App\Console\Commands;

use App\Models\Inquiry;
use App\Models\PlatformMetric;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantHealth;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Nightly pre-computation for the dashboard (Phase 8.1 / 8.3). Idempotent —
 * upserts one sa_platform_metrics row per day and refreshes sa_tenant_health.
 */
class MetricsSnapshot extends Command
{
    protected $signature = 'metrics:snapshot';

    protected $description = 'Compute platform metrics + per-tenant health snapshots.';

    public function handle(): int
    {
        $this->platform();
        $n = $this->health();
        $this->info('Metrics snapshot written; ' . $n . ' tenant-health rows.');

        return self::SUCCESS;
    }

    private function platform(): void
    {
        $tenants = Tenant::query();
        $monthStart = now()->startOfMonth();

        $plans = SubscriptionPlan::all()->keyBy('id');
        $headcounts = User::selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');

        $mrr = 0.0;
        foreach (TenantSubscription::query()->active()->get()->unique('tenant_id') as $sub) {
            $plan = $plans->get($sub->plan_id);
            if ($plan) {
                $mrr += $plan->monthlyPrice((int) ($headcounts[$sub->tenant_id] ?? 0));
            }
        }

        $funnel = Inquiry::selectRaw('status, count(*) c')->groupBy('status')->pluck('c', 'status')->all();

        $byMonth = [];
        for ($i = 11; $i >= 0; $i--) {
            $m = now()->copy()->subMonths($i)->format('Y-m');
            $byMonth[$m] = 0;
        }
        foreach (Tenant::selectRaw("DATE_FORMAT(created_at,'%Y-%m') m, count(*) c")
            ->where('created_at', '>=', now()->copy()->subMonths(11)->startOfMonth())
            ->groupBy('m')->get() as $row) {
            if (array_key_exists($row->m, $byMonth)) {
                $byMonth[$row->m] = (int) $row->c;
            }
        }

        // avg days from enquiry received -> provisioning run completed
        $avg = DB::table('sa_provisioning_runs as r')
            ->join('inquiries as i', 'i.id', '=', 'r.inquiry_id')
            ->where('r.status', 'done')
            ->selectRaw('AVG(TIMESTAMPDIFF(HOUR, i.created_at, r.updated_at) / 24) d')
            ->value('d');

        PlatformMetric::updateOrCreate(
            ['metric_date' => now()->toDateString()],
            [
                'total_tenants' => (clone $tenants)->count(),
                'active_tenants' => (clone $tenants)->where('status', 'active')->count(),
                'suspended_tenants' => (clone $tenants)->where('status', 'suspended')->count(),
                'trial_tenants' => (clone $tenants)->whereNotNull('trial_ends_at')->where('trial_ends_at', '>', now())->count(),
                'open_enquiries' => Inquiry::whereIn('status', Inquiry::OPEN_STATUSES)->count(),
                'mrr' => round($mrr, 2),
                'arr' => round($mrr * 12, 2),
                'new_tenants_month' => (clone $tenants)->where('created_at', '>=', $monthStart)->count(),
                'churned_tenants_month' => Tenant::onlyTrashed()->where('deleted_at', '>=', $monthStart)->count()
                    + (clone $tenants)->where('status', 'suspended')->where('updated_at', '>=', $monthStart)->count(),
                'funnel' => $funnel,
                'new_tenants_by_month' => $byMonth,
                'avg_enquiry_to_provision_days' => $avg !== null ? round((float) $avg, 2) : null,
                'created_at' => now(),
            ],
        );
    }

    private function health(): int
    {
        $today = now()->toDateString();
        $agg = User::selectRaw('tenant_id,
                count(*) total,
                sum(case when status = 1 then 1 else 0 end) active,
                sum(case when last_login_at >= ? then 1 else 0 end) logins_30d,
                max(last_login_at) last_activity', [now()->copy()->subDays(30)])
            ->whereNotNull('tenant_id')
            ->groupBy('tenant_id')->get()->keyBy('tenant_id');

        // users.last_login_at isn't always written by the HRM app (it was empty for every user
        // on the dev DB while the audit log had hundreds of logins), so activity also comes from
        // the audit trail: latest successful login, and distinct users who logged in in 30 days.
        $audit = DB::table('audit_logs')
            ->where('action', 'auth.login_success')->whereNotNull('tenant_id')
            ->selectRaw('tenant_id, MAX(created_at) last_login,
                COUNT(DISTINCT CASE WHEN created_at >= ? THEN actor_id END) users_30d', [now()->copy()->subDays(30)])
            ->groupBy('tenant_id')->get()->keyBy('tenant_id');

        $limits = Tenant::pluck('max_employees', 'id');
        $n = 0;

        foreach ($limits as $tid => $limit) {
            $a = $agg->get($tid);
            $l = $audit->get($tid);
            $active = (int) ($a->active ?? 0);
            $limit = (int) $limit ?: 0;
            $last = max($a->last_activity ?? null, $l->last_login ?? null); // later of the two (string compare of Y-m-d H:i:s)
            $logins30d = max((int) ($a->logins_30d ?? 0), (int) ($l->users_30d ?? 0));

            TenantHealth::updateOrCreate(
                ['tenant_id' => $tid],
                [
                    'snapshot_date' => $today,
                    'users_total' => (int) ($a->total ?? 0),
                    'users_active' => $active,
                    'logins_30d' => $logins30d,
                    'last_activity_at' => $last,
                    'seat_limit' => $limit,
                    'seat_utilisation' => $limit > 0 ? round($active / $limit * 100, 2) : 0,
                    'is_stale' => ! $last || \Illuminate\Support\Carbon::parse($last)->lt(now()->subDays(14)),
                    'updated_at' => now(),
                ],
            );
            $n++;
        }

        return $n;
    }
}
