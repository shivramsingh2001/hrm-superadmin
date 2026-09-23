@extends('layouts.app')
@section('title', 'Dashboard')

@section('content')
@php($m = $metric)

<style>
    .dash-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .dash-wrap a { text-decoration: none; }
    .dash-wrap a:hover { text-decoration: none; }

    .kpi5-card { background: #fff; border-radius: .55rem; padding: .6rem .7rem; height: 100%;
        box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06); }
    .kpi5-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
    .kpi5-icon { width: 1.7rem; height: 1.7rem; border-radius: .45rem; background: #eff6ff; color: #2563eb;
        display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; flex: none; }
    .kpi5-pill { font-size: .55rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
        background: #dcfce7; color: #166534; padding: .12rem .4rem; border-radius: 999px; white-space: nowrap; }
    .kpi5-value { font-size: 1.05rem; font-weight: 700; color: #111827; line-height: 1.1; }
    .kpi5-label { font-size: .58rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-top: .1rem; }
    .kpi5-divider { border-top: 1px solid #f1f2f4; margin: .5rem 0 .4rem; }
    .kpi5-foot { display: flex; }
    .kpi5-stat { flex: 1; min-width: 0; }
    .kpi5-stat + .kpi5-stat { border-left: 1px solid #f1f2f4; padding-left: .5rem; margin-left: .5rem; }
    .kpi5-stat .n { font-size: .64rem; font-weight: 700; color: #374151; }
    .kpi5-stat .l { font-size: .64rem; color: #adb3ba; text-transform: uppercase; letter-spacing: .02em; margin-left: .25rem; }

    .dash-wrap .card { border: 1px solid #e5e7eb; }
    .dash-wrap .card-header { background: #fff !important; font-size: .82rem; font-weight: 600; color: #1f2937; }
    .dash-wrap .card-header .hint { font-size: .68rem; color: #9ca3af; font-weight: 400; }

    .list-card-body { height: 220px; overflow-y: auto; }
    .list-row { display: flex; align-items: center; justify-content: space-between; padding: .5rem .95rem;
        border-bottom: 1px solid #f1f2f4; font-size: .8rem; }
    .list-row:last-child { border-bottom: 0; }
    .list-row .name { color: #374151; font-weight: 500; }
    .list-row .meta { color: #9ca3af; font-size: .74rem; }
    .empty-row { padding: .8rem .95rem; color: #9ca3af; font-size: .8rem; }

    .urgency-pill { font-size: .68rem; font-weight: 600; padding: .15rem .45rem; border-radius: .3rem; }
    .urgency-soon { background: #fee2e2; color: #991b1b; }
    .urgency-week { background: #fef9c3; color: #854d0e; }
    .urgency-later { background: #f3f4f6; color: #6b7280; }

    /* Enquiry funnel — Bootstrap progress bars, soft-blue track/fill */
    .funnel-list { display: flex; flex-direction: column; gap: .5rem; }
    .funnel-row { display: flex; align-items: center; gap: .55rem; }
    .funnel-label { flex: 0 0 5.7rem; font-size: .68rem; color: #475569; font-weight: 500; }
    .funnel-track { flex: 1; height: .5rem; border-radius: 999px; background: #eaf2ff; }
    .funnel-track .progress-bar { background-color: #60a5fa; border-radius: 999px; }
    .funnel-count { flex: 0 0 1.6rem; text-align: right; font-size: .72rem; font-weight: 700; color: #1e3a8a; }
</style>

<div class="dash-wrap">

    {{-- KPI cards --}}
    <div class="row row-cols-1 row-cols-md-2 row-cols-lg-5 g-2 mb-3">
        @foreach([
            [
                'icon' => 'bi-building', 'pill' => 'Active',
                'value' => number_format($m?->total_tenants ?? 0), 'label' => 'Total Tenants',
                'stats' => [['n' => $m?->active_tenants ?? 0, 'l' => 'Active'], ['n' => $m?->trial_tenants ?? 0, 'l' => 'Trial']],
            ],
            [
                'icon' => 'bi-people', 'pill' => 'Live',
                'value' => number_format($totalEmployees), 'label' => 'Total Employees',
                'stats' => [['n' => $avgEmployeesPerTenant, 'l' => 'Avg / Tenant'], ['n' => $topByEmployees->first()->users_count ?? 0, 'l' => 'Top Tenant']],
            ],
            [
                'icon' => 'bi-inbox', 'pill' => 'Open',
                'value' => number_format($m?->open_enquiries ?? 0), 'label' => 'Open Enquiries',
                'stats' => [['n' => $newEnquiries7d, 'l' => 'New (7d)'], ['n' => $convertedEnquiries, 'l' => 'Converted']],
            ],
            [
                'icon' => 'bi-currency-rupee', 'pill' => 'This Month',
                'value' => '₹' . number_format($m?->mrr ?? 0), 'label' => 'Monthly Revenue',
                'stats' => [['n' => '₹' . number_format($m?->arr ?? 0), 'l' => 'ARR'], ['n' => '₹' . number_format($totalRevenueAllTime), 'l' => 'All-time']],
            ],
            [
                'icon' => 'bi-hourglass-split', 'pill' => 'Trial',
                'value' => number_format($m?->trial_tenants ?? 0), 'label' => 'Trial Tenants',
                'stats' => [['n' => $trialsDueSoon, 'l' => '≤ 7 days'], ['n' => $trialsDueLater, 'l' => '8-30 days']],
            ],
        ] as $card)
        <div class="col">
            <div class="kpi5-card">
                <div class="kpi5-top">
                    <span class="kpi5-icon"><i class="bi {{ $card['icon'] }}"></i></span>
                    <span class="kpi5-pill">{{ $card['pill'] }}</span>
                </div>
                <div class="kpi5-value">{{ $card['value'] }}</div>
                <div class="kpi5-label">{{ $card['label'] }}</div>
                <div class="kpi5-divider"></div>
                <div class="kpi5-foot">
                    @foreach($card['stats'] as $s)
                        <div class="kpi5-stat"><span class="n">{{ $s['n'] }}</span><span class="l">{{ $s['l'] }}</span></div>
                    @endforeach
                </div>
            </div>
        </div>
        @endforeach
    </div>

    {{-- Main trend + funnel + tenant status --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6"><div class="card h-100">
            <div class="card-header"><span>Tenants, employees &amp; revenue — last 6 months</span></div>
            <div class="card-body p-2"><div style="height:290px"><canvas id="growthChart"></canvas></div></div>
        </div></div>
        <div class="col-md-3"><div class="card h-100">
            <div class="card-header">Enquiry funnel</div>
            <div class="card-body">
                <div class="funnel-list">
                @foreach($funnelStages as $stage)
                    <div class="funnel-row">
                        <div class="funnel-label">{{ $stage['label'] }}</div>
                        <div class="progress funnel-track" role="progressbar" aria-valuenow="{{ $stage['pct'] }}" aria-valuemin="0" aria-valuemax="100">
                            <div class="progress-bar" style="width: {{ $stage['pct'] }}%"></div>
                        </div>
                        <div class="funnel-count">{{ $stage['count'] }}</div>
                    </div>
                @endforeach
                </div>
            </div>
        </div></div>
        <div class="col-md-3"><div class="card h-100">
            <div class="card-header">Tenant status</div>
            <div class="card-body p-2"><canvas id="statusChart" height="150"></canvas></div>
        </div></div>
    </div>

    {{-- Lists --}}
    <div class="row g-3">
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>Top 6 by employees</span><a href="{{ route('tenants.index') }}" class="small">all →</a>
                </div>
                <div class="list-card-body">
                @forelse($topByEmployees as $t)
                    <div class="list-row">
                        <a href="{{ route('tenants.show', $t) }}" class="name">{{ $t->company_name }}</a>
                        <span class="meta">{{ $t->users_count }}</span>
                    </div>
                @empty <div class="empty-row">None.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>Open enquiries</span><a href="{{ route('enquiries.index') }}" class="small">all →</a>
                </div>
                <div class="list-card-body">
                @forelse($recentEnquiries as $e)
                    <div class="list-row">
                        <a href="{{ route('enquiries.show', $e) }}" class="name">{{ $e->company_name }}</a>
                        <span class="badge bg-secondary">{{ str_replace('_',' ',$e->status) }}</span>
                    </div>
                @empty <div class="empty-row">None.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>Trials expiring ≤ 30 days</span><span class="hint">{{ count($expiringTrials) }}</span>
                </div>
                <div class="list-card-body">
                @forelse($expiringTrials as $t)
                    <div class="list-row">
                        <a href="{{ route('tenants.show', $t) }}" class="name">{{ $t->company_name }}</a>
                        <span class="urgency-pill {{ $t->days_left <= 7 ? 'urgency-soon' : ($t->days_left <= 14 ? 'urgency-week' : 'urgency-later') }}">
                            {{ $t->trial_ends_at->format('d M') }}
                        </span>
                    </div>
                @empty <div class="empty-row">None.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-3">
            <div class="card">
                <div class="card-header d-flex justify-content-between">
                    <span>At-risk tenants</span><a href="{{ route('health.index') }}" class="small">health →</a>
                </div>
                <div class="list-card-body">
                @forelse($atRisk as $h)
                    <div class="list-row">
                        <a href="{{ route('tenants.show', $h->tenant_id) }}" class="name">{{ $h->tenant->company_name ?? '#'.$h->tenant_id }}</a>
                        <span class="meta">{{ $h->last_activity_at ? $h->last_activity_at->diffForHumans(null, true) : 'no logins' }}</span>
                    </div>
                @empty <div class="empty-row">None flagged.</div> @endforelse
                </div>
            </div>
        </div>
    </div>
</div>
@endsection

@section('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
<script>
(function () {
    const months = @json($months);
    const monthLabels = months.map(m => {
        const [y, mo] = m.split('-');
        return new Date(y, mo - 1, 1).toLocaleString('en-US', { month: 'short' });
    });
    const tenantsData = @json(array_values($tenantsByMonth));
    const employeesData = @json(array_values($employeesByMonth));
    const revenueData = @json(array_values($revenueByMonth));

    // Styled to match the "Weekly Attendance Overview" chart on the HRM dashboard:
    // rounded grouped columns, minimal axis chrome, legend top-right, indigo + amber palette.
    new Chart(document.getElementById('growthChart'), {
        type: 'bar',
        data: {
            labels: monthLabels,
            datasets: [
                { label: 'Tenants', data: tenantsData, backgroundColor: '#1e3a8a', borderRadius: 5, borderSkipped: false, yAxisID: 'y' },
                { label: 'Employees', data: employeesData, backgroundColor: '#3b82f6', borderRadius: 5, borderSkipped: false, yAxisID: 'y' },
                { label: 'Revenue', data: revenueData, backgroundColor: '#bfdbfe', borderRadius: 5, borderSkipped: false, yAxisID: 'y1' },
            ]
        },
        options: {
            maintainAspectRatio: false,
            categoryPercentage: 0.6,
            barPercentage: 0.9,
            plugins: {
                legend: { position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 11, weight: 600 } } },
            },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11, weight: 600 } } },
                y: { beginAtZero: true, ticks: { precision: 0 }, border: { display: false },
                    grid: { color: '#f1f2f4' }, title: { display: true, text: 'Count', font: { size: 10 } } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, border: { display: false },
                    title: { display: true, text: 'Revenue', font: { size: 10 } } },
            }
        }
    });

    new Chart(document.getElementById('statusChart'), {
        type: 'doughnut',
        data: {
            labels: ['Active', 'Trial', 'Suspended', 'Other'],
            datasets: [{
                data: [
                    {{ $m?->active_tenants ?? 0 }},
                    {{ $m?->trial_tenants ?? 0 }},
                    {{ $m?->suspended_tenants ?? 0 }},
                    Math.max(0, {{ $m?->total_tenants ?? 0 }} - {{ $m?->active_tenants ?? 0 }} - {{ $m?->trial_tenants ?? 0 }} - {{ $m?->suspended_tenants ?? 0 }}),
                ],
                backgroundColor: ['#1e3a8a','#3b82f6','#93c5fd','#dbeafe'],
            }]
        },
        options: { plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 }, padding: 8 } } } }
    });
})();
</script>
@endsection
