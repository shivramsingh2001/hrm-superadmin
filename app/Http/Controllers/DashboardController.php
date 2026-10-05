<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Inquiry;
use App\Models\PaymentLog;
use App\Models\PlatformMetric;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantHealth;
use App\Models\TenantSubscription;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    /** Trend charts cover this many trailing months (incl. current). */
    private const TREND_MONTHS = 6;

    /**
     * Period filter (?range=today|last_7_days|this_month|previous_month|custom&from=&to=):
     * - No filter (default): every card keeps its normal window — revenue this month vs last,
     *   failed logins last 7 days, logins chart last 14 days, tenant activity last 30 days,
     *   "this period" tiles = this month.
     * - Filter applied: all of those use the chosen period.
     * Headcounts, plan distribution, renewals, the 6-month growth chart,
     * the funnel and tenant status always show "now".
     */
    public function index()
    {
        $metric = $this->freshMetric();
        $range = $this->range();
        $applied = $range['applied'];
        $today = Carbon::today();

        // Windows used when no filter is applied
        $win = fn (int $days) => $applied ? [$range['start'], $range['end']] : [$today->copy()->subDays($days - 1), $today->copy()];
        $monthWin = $applied ? [$range['start'], $range['end']] : [$today->copy()->startOfMonth(), $today->copy()];

        $recentEnquiries = Inquiry::whereIn('status', Inquiry::OPEN_STATUSES)
            ->orderByDesc('created_at')->limit(8)->get();

        $expiringTrials = Tenant::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(30)])
            ->orderBy('trial_ends_at')->get()
            ->each(fn ($t) => $t->days_left = now()->diffInDays($t->trial_ends_at, false));

        $atRisk = TenantHealth::where('is_stale', true)->with('tenant')->limit(10)->get();

        // ---- 6-month growth chart (always "now") ----
        $rangeStart = now()->copy()->subMonths(self::TREND_MONTHS - 1)->startOfMonth();
        $months = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = now()->copy()->subMonths($i)->format('Y-m');
        }
        $tenantsByMonth = $this->byMonth(Tenant::query(), 'created_at', 'count(*)', $rangeStart, $months);
        $employeesByMonth = $this->byMonth(User::query(), 'created_at', 'count(*)', $rangeStart, $months);
        $revenueByMonth = $this->byMonth(PaymentLog::query(), 'payment_date', 'sum(amount)', $rangeStart, $months, true);

        $topByEmployees = Tenant::withCount('users')->orderByDesc('users_count')->limit(6)
            ->get(['id', 'company_name', 'max_employees']);
        $totalEmployees = User::count();

        // ---- KPI footers ----
        $avgEmployeesPerTenant = $metric?->total_tenants ? round($totalEmployees / $metric->total_tenants, 1) : 0;
        $funnel = $metric?->funnel ?? [];
        $convertedEnquiries = ($funnel['paid'] ?? 0) + ($funnel['provisioned'] ?? 0);
        $newEnquiries7d = Inquiry::where('created_at', '>=', now()->subDays(7))->count();
        $totalRevenueAllTime = (float) PaymentLog::sum('amount');
        $trialsDueSoon = $expiringTrials->filter(fn ($t) => now()->diffInDays($t->trial_ends_at, false) <= 7)->count();
        $trialsDueLater = $expiringTrials->count() - $trialsDueSoon;

        // Enquiry funnel bars (computed here, not in Blade — see note in the view)
        $funnelLabels = ['new' => 'New', 'contacted' => 'Contacted', 'negotiating' => 'Negotiating',
            'payment_sent' => 'Payment sent', 'paid' => 'Paid', 'provisioned' => 'Provisioned', 'lost' => 'Lost'];
        $funnelPeak = max(array_merge([1], array_map(fn ($k) => $funnel[$k] ?? 0, array_keys($funnelLabels))));
        $funnelStages = [];
        foreach ($funnelLabels as $key => $label) {
            $count = $funnel[$key] ?? 0;
            $funnelStages[] = ['label' => $label, 'count' => $count, 'pct' => (int) round($count / $funnelPeak * 100)];
        }

        return view('dashboard', array_merge(compact(
            'metric', 'recentEnquiries', 'expiringTrials', 'atRisk',
            'months', 'tenantsByMonth', 'employeesByMonth', 'revenueByMonth',
            'topByEmployees', 'totalEmployees',
            'avgEmployeesPerTenant', 'convertedEnquiries', 'newEnquiries7d',
            'totalRevenueAllTime', 'trialsDueSoon', 'trialsDueLater', 'funnelStages',
            'range',
        ), [
            'periodSummary' => $this->periodSummary($monthWin, $applied ? $range['label'] : 'This month'),
            'revenue' => $this->revenue($monthWin, $applied),
            'security' => $this->security($win(7), $win(14), $win(30), $applied),
        ]));
    }

    /** Recompute the snapshot now (button in the header), then back to the dashboard. */
    public function refresh()
    {
        // Via Artisan so the command gets an output buffer — calling handle() directly fails on $this->info() in a web request.
        Artisan::call('metrics:snapshot');

        return redirect()->route('dashboard', request()->only(['range', 'from', 'to']))
            ->with('success', 'Dashboard numbers refreshed.');
    }

    /**
     * Latest platform snapshot; recomputed when there is none for today. The nightly
     * `metrics:snapshot` only runs when the server cron (`schedule:run`) is set up —
     * without this the dashboard could show weeks-old numbers.
     */
    private function freshMetric(): ?PlatformMetric
    {
        $metric = PlatformMetric::orderByDesc('metric_date')->first();
        if (! $metric || ! $metric->metric_date->isToday()) {
            Artisan::call('metrics:snapshot');
            $metric = PlatformMetric::orderByDesc('metric_date')->first();
        }

        return $metric;
    }

    /** Small "in this period" tiles: new tenants / employees / enquiries, revenue collected, logins. */
    private function periodSummary(array $win, string $label): array
    {
        [$s, $e] = [$win[0]->copy()->startOfDay(), $win[1]->copy()->endOfDay()];

        return [
            'label' => $label,
            'new_tenants' => Tenant::whereBetween('created_at', [$s, $e])->count(),
            'new_employees' => User::whereBetween('created_at', [$s, $e])->count(),
            'new_enquiries' => Inquiry::whereBetween('created_at', [$s, $e])->count(),
            'collected' => (float) PaymentLog::whereBetween('payment_date', [$s->toDateString(), $e->toDateString()])->sum('amount'),
            'logins' => AuditLog::where('action', 'auth.login_success')->whereBetween('created_at', [$s, $e])->count(),
        ];
    }

    /** Revenue & plans: collected vs the previous equal window, by plan, plan mix, upcoming renewals. */
    private function revenue(array $win, bool $applied): array
    {
        [$s, $e] = [$win[0]->copy()->startOfDay(), $win[1]->copy()->endOfDay()];
        // Previous window: last month when unfiltered, else the same number of days just before.
        if ($applied) {
            $days = (int) $s->diffInDays($e) + 1;
            [$ps, $pe] = [$s->copy()->subDays($days), $s->copy()->subDay()->endOfDay()];
        } else {
            [$ps, $pe] = [$s->copy()->subMonthNoOverflow()->startOfMonth(), $s->copy()->subMonthNoOverflow()->endOfMonth()];
        }
        $sum = fn ($a, $b) => (float) PaymentLog::whereBetween('payment_date', [$a->toDateString(), $b->toDateString()])->sum('amount');
        $current = $sum($s, $e);
        $previous = $sum($ps, $pe);

        $plans = SubscriptionPlan::pluck('name', 'id');

        $byPlan = PaymentLog::leftJoin('tenants', 'tenants.id', '=', 'payment_logs.tenant_id')
            ->whereBetween('payment_logs.payment_date', [$s->toDateString(), $e->toDateString()])
            ->selectRaw('tenants.subscription_plan_id as plan_id, SUM(payment_logs.amount) as total, COUNT(*) as n')
            ->groupBy('tenants.subscription_plan_id')->orderByDesc('total')->get()
            ->map(fn ($r) => ['plan' => $plans[$r->plan_id] ?? 'No plan / not provisioned', 'total' => (float) $r->total, 'n' => (int) $r->n]);
        $peak = max(1, (float) $byPlan->max('total'));
        $byPlan = $byPlan->map(fn ($r) => $r + ['pct' => (int) round($r['total'] / $peak * 100)]);

        $mix = Tenant::where('status', 'active')->selectRaw('subscription_plan_id, COUNT(*) c')
            ->groupBy('subscription_plan_id')->pluck('c', 'subscription_plan_id')
            ->mapWithKeys(fn ($c, $pid) => [($plans[$pid] ?? 'No plan') => (int) $c]);

        $renewals = TenantSubscription::query()->active()
            ->whereBetween('end_date', [now()->toDateString(), now()->addDays(30)->toDateString()])
            ->with('tenant:id,company_name')->orderBy('end_date')->limit(8)->get()
            ->map(fn ($sub) => [
                'tenant_id' => $sub->tenant_id,
                'tenant' => $sub->tenant->company_name ?? ('#' . $sub->tenant_id),
                'plan' => $plans[$sub->plan_id] ?? '—',
                'end_date' => Carbon::parse($sub->end_date),
                'days_left' => (int) now()->startOfDay()->diffInDays(Carbon::parse($sub->end_date), false),
            ]);

        return [
            'current' => $current,
            'previous' => $previous,
            'change' => $previous > 0 ? round(($current - $previous) / $previous * 100) : null,
            'current_label' => $applied ? 'This period' : 'This month',
            'previous_label' => $applied ? 'Previous ' . ((int) $s->diffInDays($e) + 1) . ' days' : $ps->format('F Y'),
            'by_plan' => $byPlan->values()->all(),
            'mix' => $mix->all(),
            'renewals' => $renewals->all(),
        ];
    }

    /** Security & usage: failed logins, logins per day, super-admin actions, most / least active tenants. */
    private function security(array $failWin, array $chartWin, array $activityWin, bool $applied): array
    {
        $b = fn (array $w) => [$w[0]->copy()->startOfDay(), $w[1]->copy()->endOfDay()];
        $tenantNames = Tenant::withTrashed()->pluck('company_name', 'id');

        $failed = AuditLog::where('action', 'auth.login_failed')->whereBetween('created_at', $b($failWin));
        $failedByTenant = (clone $failed)->selectRaw('tenant_id, COUNT(*) c')->groupBy('tenant_id')->orderByDesc('c')->limit(5)
            ->get()->map(fn ($r) => ['tenant_id' => $r->tenant_id, 'tenant' => $r->tenant_id ? ($tenantNames[$r->tenant_id] ?? '#' . $r->tenant_id) : 'Unknown / no tenant', 'count' => (int) $r->c]);

        // Logins per day (successful vs failed), monthly bars for long periods
        [$cs, $ce] = $b($chartWin);
        $daily = AuditLog::whereIn('action', ['auth.login_success', 'auth.login_failed'])->whereBetween('created_at', [$cs, $ce])
            ->selectRaw("DATE(created_at) d, action, COUNT(*) c")->groupBy('d', 'action')->get();
        $days = iterator_to_array(CarbonPeriod::create($cs->copy()->startOfDay(), $ce->copy()->startOfDay()));
        $monthly = count($days) > 62;
        $chart = ['labels' => [], 'success' => [], 'failed' => []];
        $bucket = [];
        foreach ($days as $d) {
            $key = $monthly ? $d->format('M Y') : $d->format(count($days) <= 7 ? 'D' : 'd M');
            $bucket[$key] ??= ['success' => 0, 'failed' => 0];
        }
        foreach ($daily as $r) {
            $d = Carbon::parse($r->d);
            $key = $monthly ? $d->format('M Y') : $d->format(count($days) <= 7 ? 'D' : 'd M');
            if (isset($bucket[$key])) {
                $bucket[$key][$r->action === 'auth.login_success' ? 'success' : 'failed'] += (int) $r->c;
            }
        }
        foreach ($bucket as $label => $v) {
            $chart['labels'][] = $label;
            $chart['success'][] = $v['success'];
            $chart['failed'][] = $v['failed'];
        }

        // Super-admin actions (incl. while impersonating)
        $adminNames = DB::table('super_admins')->pluck('name', 'id');
        $adminActions = AuditLog::whereIn('actor_type', ['super_admin', 'super_admin_impersonating'])
            ->when($applied, fn ($q) => $q->whereBetween('created_at', $b($failWin)))
            ->orderByDesc('created_at')->limit(6)->get(['actor_type', 'actor_id', 'tenant_id', 'action', 'created_at'])
            ->map(fn ($a) => [
                'who' => $adminNames[$a->actor_id] ?? 'Super admin',
                'action' => str_replace(['.', '_'], [' · ', ' '], $a->action),
                'impersonating' => $a->actor_type === 'super_admin_impersonating',
                'tenant' => $a->tenant_id ? ($tenantNames[$a->tenant_id] ?? '#' . $a->tenant_id) : null,
                'when' => $a->created_at,
            ]);

        // Tenant activity: logins in the window + seat usage
        $logins = AuditLog::where('action', 'auth.login_success')->whereNotNull('tenant_id')->whereBetween('created_at', $b($activityWin))
            ->selectRaw('tenant_id, COUNT(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $activity = Tenant::where('status', 'active')->withCount('users')->get(['id', 'company_name', 'max_employees'])
            ->map(fn ($t) => [
                'id' => $t->id,
                'name' => $t->company_name,
                'logins' => (int) ($logins[$t->id] ?? 0),
                'users' => (int) $t->users_count,
                'limit' => (int) $t->max_employees,
                'seat_pct' => $t->max_employees > 0 ? (int) round($t->users_count / $t->max_employees * 100) : null,
            ]);

        return [
            'failed_count' => (clone $failed)->count(),
            'failed_label' => $applied ? 'in this period' : 'last 7 days',
            'failed_by_tenant' => $failedByTenant->all(),
            'chart' => $chart,
            'chart_label' => $applied ? 'this period' : 'last 14 days',
            'admin_actions' => $adminActions->all(),
            'activity_label' => $applied ? 'this period' : 'last 30 days',
            'most_active' => $activity->sortByDesc('logins')->take(5)->values()->all(),
            'least_active' => $activity->sortBy('logins')->take(5)->values()->all(),
        ];
    }

    /**
     * Period from the query string — same presets as the HRM dashboard. No / unknown
     * `range` = no filter (`applied` false). Custom: swapped if reversed, capped at a year.
     */
    private function range(): array
    {
        $today = Carbon::today();
        $preset = (string) request('range', 'none');
        $note = null;

        switch ($preset) {
            case 'today':
                [$start, $end, $label] = [$today->copy(), $today->copy(), 'Today'];
                break;
            case 'last_7_days':
                [$start, $end, $label] = [$today->copy()->subDays(6), $today->copy(), 'Last 7 days'];
                break;
            case 'this_month':
                [$start, $end, $label] = [$today->copy()->startOfMonth(), $today->copy(), 'This month'];
                break;
            case 'previous_month':
                $prev = $today->copy()->subMonthNoOverflow();
                [$start, $end, $label] = [$prev->copy()->startOfMonth(), $prev->copy()->endOfMonth()->startOfDay(), $prev->format('F Y')];
                break;
            case 'custom':
                try {
                    $start = Carbon::parse((string) request('from'))->startOfDay();
                    $end = Carbon::parse((string) request('to', request('from')))->startOfDay();
                } catch (\Throwable $e) {
                    $start = $end = $today->copy();
                }
                if ($end->lt($start)) {
                    [$start, $end] = [$end, $start];
                }
                if ($start->diffInDays($end) > 365) {
                    $end = $start->copy()->addDays(365);
                    $note = 'Custom range limited to one year — showing ' . $start->format('d M Y') . ' to ' . $end->format('d M Y') . '.';
                }
                $label = $start->equalTo($end) ? $start->format('d M Y') : $start->format('d M Y') . ' – ' . $end->format('d M Y');
                break;
            default:
                $preset = 'none';
                [$start, $end, $label] = [$today->copy(), $today->copy(), 'All data'];
        }

        return [
            'preset' => $preset, 'start' => $start, 'end' => $end, 'label' => $label,
            'from' => $start->toDateString(), 'to' => $end->toDateString(),
            'days' => (int) $start->diffInDays($end) + 1, 'note' => $note,
            'applied' => $preset !== 'none',
        ];
    }

    /** Monthly totals for the trend chart, zero-filled for the given months. */
    private function byMonth($query, string $column, string $agg, Carbon $from, array $months, bool $float = false): array
    {
        $out = array_fill_keys($months, $float ? 0.0 : 0);
        foreach ($query->selectRaw("DATE_FORMAT({$column},'%Y-%m') m, {$agg} v")->where($column, '>=', $from)->groupBy('m')->get() as $row) {
            if (array_key_exists($row->m, $out)) {
                $out[$row->m] = $float ? (float) $row->v : (int) $row->v;
            }
        }

        return $out;
    }
}
