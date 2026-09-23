<?php

namespace App\Http\Controllers;

use App\Console\Commands\MetricsSnapshot;
use App\Models\Inquiry;
use App\Models\PaymentLog;
use App\Models\PlatformMetric;
use App\Models\Tenant;
use App\Models\TenantHealth;
use App\Models\User;

class DashboardController extends Controller
{
    /** Trend charts cover this many trailing months (incl. current). */
    private const TREND_MONTHS = 6;

    public function index()
    {
        // Read the nightly snapshot; compute one on the fly if it's missing.
        $metric = PlatformMetric::orderByDesc('metric_date')->first();
        if (! $metric) {
            (new MetricsSnapshot())->handle();
            $metric = PlatformMetric::orderByDesc('metric_date')->first();
        }

        $recentEnquiries = Inquiry::whereIn('status', Inquiry::OPEN_STATUSES)
            ->orderByDesc('created_at')->limit(8)->get();

        $expiringTrials = Tenant::whereNotNull('trial_ends_at')
            ->whereBetween('trial_ends_at', [now(), now()->addDays(30)])
            ->orderBy('trial_ends_at')->get()
            ->each(fn ($t) => $t->days_left = now()->diffInDays($t->trial_ends_at, false));

        $atRisk = TenantHealth::where('is_stale', true)->with('tenant')->limit(10)->get();

        $rangeStart = now()->copy()->subMonths(self::TREND_MONTHS - 1)->startOfMonth();
        $months = [];
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = now()->copy()->subMonths($i)->format('Y-m');
        }

        $tenantsByMonth = array_fill_keys($months, 0);
        foreach (Tenant::selectRaw("DATE_FORMAT(created_at,'%Y-%m') m, count(*) c")
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('m')->get() as $row) {
            if (array_key_exists($row->m, $tenantsByMonth)) {
                $tenantsByMonth[$row->m] = (int) $row->c;
            }
        }

        $employeesByMonth = array_fill_keys($months, 0);
        foreach (User::selectRaw("DATE_FORMAT(created_at,'%Y-%m') m, count(*) c")
            ->where('created_at', '>=', $rangeStart)
            ->groupBy('m')->get() as $row) {
            if (array_key_exists($row->m, $employeesByMonth)) {
                $employeesByMonth[$row->m] = (int) $row->c;
            }
        }

        $revenueByMonth = array_fill_keys($months, 0.0);
        foreach (PaymentLog::selectRaw("DATE_FORMAT(payment_date,'%Y-%m') m, sum(amount) s")
            ->where('payment_date', '>=', $rangeStart)
            ->groupBy('m')->get() as $row) {
            if (array_key_exists($row->m, $revenueByMonth)) {
                $revenueByMonth[$row->m] = (float) $row->s;
            }
        }

        $topByEmployees = Tenant::withCount('users')
            ->orderByDesc('users_count')
            ->limit(6)
            ->get(['id', 'company_name', 'max_employees']);

        $totalEmployees = User::count();

        // ---- Small footer stats for the 5 summary cards ----
        $avgEmployeesPerTenant = $metric?->total_tenants ? round($totalEmployees / $metric->total_tenants, 1) : 0;
        $funnel = $metric?->funnel ?? [];
        $convertedEnquiries = ($funnel['paid'] ?? 0) + ($funnel['provisioned'] ?? 0);
        $newEnquiries7d = Inquiry::where('created_at', '>=', now()->subDays(7))->count();
        $totalRevenueAllTime = (float) PaymentLog::sum('amount');
        $trialsDueSoon = $expiringTrials->filter(fn ($t) => now()->diffInDays($t->trial_ends_at, false) <= 7)->count();
        $trialsDueLater = $expiringTrials->count() - $trialsDueSoon;

        // Pre-built for the Enquiry funnel progress-bar card — fully computed here (not in the
        // Blade view) since mixing a block-form @php with this file's other @php(...) inline
        // directives breaks Blade's compiler on this view.
        $funnelLabels = ['new' => 'New', 'contacted' => 'Contacted', 'negotiating' => 'Negotiating',
            'payment_sent' => 'Payment sent', 'paid' => 'Paid', 'provisioned' => 'Provisioned', 'lost' => 'Lost'];
        $funnelPeak = max(array_merge([1], array_map(fn ($k) => $funnel[$k] ?? 0, array_keys($funnelLabels))));
        $funnelStages = [];
        foreach ($funnelLabels as $key => $label) {
            $count = $funnel[$key] ?? 0;
            $funnelStages[] = ['label' => $label, 'count' => $count, 'pct' => (int) round($count / $funnelPeak * 100)];
        }

        return view('dashboard', compact(
            'metric', 'recentEnquiries', 'expiringTrials', 'atRisk',
            'months', 'tenantsByMonth', 'employeesByMonth', 'revenueByMonth',
            'topByEmployees', 'totalEmployees',
            'avgEmployeesPerTenant', 'convertedEnquiries', 'newEnquiries7d',
            'totalRevenueAllTime', 'trialsDueSoon', 'trialsDueLater', 'funnelStages',
        ));
    }
}
