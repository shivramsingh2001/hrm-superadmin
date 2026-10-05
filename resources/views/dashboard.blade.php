@extends('layouts.app')
@section('title', 'Dashboard')

{{-- Header controls: data freshness + refresh, current period chip, reset, ⋮ period menu --}}
@section('header-actions')
    <span class="dash-fresh" title="Numbers computed {{ optional($metric?->created_at)->format('d M Y, h:i A') }}">
        <i class="bi bi-clock-history"></i> Updated {{ optional($metric?->created_at)->diffForHumans() ?? '—' }}
    </span>
    <form method="POST" action="{{ route('dashboard.refresh', request()->only(['range', 'from', 'to'])) }}" class="m-0">@csrf
        <button type="submit" class="dash-icon-btn" title="Refresh numbers now" aria-label="Refresh"><i class="bi bi-arrow-clockwise"></i></button>
    </form>
    @if($range['applied'])
        <span class="dash-period-chip" title="{{ $range['from'] }}{{ $range['from'] !== $range['to'] ? ' → ' . $range['to'] . ' (' . $range['days'] . ' days)' : '' }}"><i class="bi bi-calendar3"></i> {{ $range['label'] }}</span>
        <a href="{{ route('dashboard') }}" class="dash-icon-btn" title="Reset filter — show all data" aria-label="Reset filter"><i class="bi bi-arrow-counterclockwise"></i></a>
    @else
        <span class="dash-period-chip muted" title="No period filter — each card shows its normal window"><i class="bi bi-layers"></i> All data</span>
    @endif
    <div class="dropdown dash-period">
        <button type="button" class="dash-icon-btn" data-bs-toggle="dropdown" data-bs-auto-close="outside" aria-expanded="false" aria-label="Dashboard period" title="Change period">
            <i class="bi bi-three-dots-vertical"></i>
        </button>
        <div class="dropdown-menu dropdown-menu-end dash-period-menu">
            <div class="dash-period-head">Show data for</div>
            @foreach(['today' => ['Today', 'bi-sun'], 'last_7_days' => ['Last 7 days', 'bi-clock'], 'this_month' => ['This month', 'bi-calendar3'], 'previous_month' => ['Previous month', 'bi-arrow-counterclockwise']] as $key => $p)
                <a href="{{ route('dashboard', ['range' => $key]) }}" class="dropdown-item {{ $range['preset'] === $key ? 'active' : '' }}">
                    <i class="bi {{ $p[1] }}"></i><span>{{ $p[0] }}</span>@if($range['preset'] === $key)<i class="bi bi-check2 ms-auto"></i>@endif
                </a>
            @endforeach
            <div class="dropdown-divider"></div>
            <button type="button" class="dropdown-item {{ $range['preset'] === 'custom' ? 'active' : '' }}" id="dashCustomBtn">
                <i class="bi bi-sliders"></i><span>Custom range</span><i class="bi {{ $range['preset'] === 'custom' ? 'bi-check2' : 'bi-chevron-down' }} ms-auto"></i>
            </button>
            <form method="GET" action="{{ route('dashboard') }}" class="dash-custom" id="dashCustom" style="{{ $range['preset'] === 'custom' ? '' : 'display:none' }}">
                <input type="hidden" name="range" value="custom">
                <label class="dash-custom-label">From</label>
                <input type="date" name="from" value="{{ $range['preset'] === 'custom' ? $range['from'] : '' }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                <label class="dash-custom-label">To</label>
                <input type="date" name="to" value="{{ $range['preset'] === 'custom' ? $range['to'] : '' }}" max="{{ now()->toDateString() }}" class="form-control form-control-sm" required>
                <button type="submit" class="btn btn-sm btn-primary w-100 mt-2">Apply</button>
            </form>
            @if($range['applied'])
                <div class="dropdown-divider"></div>
                <a href="{{ route('dashboard') }}" class="dropdown-item"><i class="bi bi-arrow-counterclockwise"></i><span>Reset filter</span></a>
            @endif
            <div class="dash-period-foot">Headcounts, plan mix, renewals and the 6-month charts always show "now".</div>
        </div>
    </div>
@endsection

@section('content')
@php($m = $metric)

<style>
    /* One colour theme: the panel's blue (#2563eb, same as the sidebar / avatar) in shades, plus neutral greys.
       Urgency is shown by blue intensity — solid = act now, light = later — never by red / amber / green. */
    :root {
        --b-900: #1e3a8a; --b-800: #1e40af; --b-700: #1d4ed8; --b-600: #2563eb; --b-500: #3b82f6;
        --b-400: #60a5fa; --b-300: #93c5fd; --b-200: #bfdbfe; --b-100: #dbeafe; --b-50: #eff6ff;
    }
    .dash-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .chip { display: inline-flex; align-items: center; gap: 3px; font-size: .66rem; font-weight: 600; padding: .12rem .45rem;
        border-radius: 999px; background: var(--b-50); color: var(--b-700); white-space: nowrap; text-transform: capitalize; }
    .chip.solid { background: var(--b-600); color: #fff; }
    .section-title i { color: var(--b-600); }
    .dash-wrap a { text-decoration: none; }
    .dash-wrap a:hover { text-decoration: none; }

    /* Header controls */
    .dash-fresh { font-size: .7rem; color: #6b7280; white-space: nowrap; }
    .dash-icon-btn { width: 30px; height: 30px; border-radius: 8px; border: 1px solid #e5e7eb; background: #fff; color: #475569;
        display: inline-flex; align-items: center; justify-content: center; padding: 0; text-decoration: none; }
    .dash-icon-btn:hover, .dash-icon-btn[aria-expanded="true"] { background: #eff6ff; color: #2563eb; border-color: #93c5fd; }
    .dash-period-chip { display: inline-flex; align-items: center; gap: 5px; height: 30px; padding: 0 10px; border-radius: 8px;
        background: #eff6ff; color: #2563eb; font-size: .72rem; font-weight: 700; white-space: nowrap; }
    .dash-period-chip.muted { background: #f3f4f6; color: #6b7280; }
    .dash-period-menu { width: 215px; padding: 5px; border-radius: 10px; box-shadow: 0 8px 24px rgba(15, 23, 42, .12); }
    .dash-period-head { font-size: .58rem; font-weight: 700; color: #9ca3af; text-transform: uppercase; letter-spacing: .05em; padding: 3px 7px 5px; }
    .dash-period .dash-period-menu .dropdown-item { display: flex; align-items: center; gap: 7px; padding: 5px 7px; font-size: .72rem;
        border-radius: 6px; color: #334155; width: 100%; background: none; border: 0; text-align: left; }
    .dash-period .dash-period-menu .dropdown-item:hover { background: #f1f5f9; color: #2563eb; }
    .dash-period .dash-period-menu .dropdown-item.active { background: #eff6ff; color: #2563eb; font-weight: 700; }
    .dash-custom { padding: 3px 7px 5px; }
    .dash-custom-label { display: block; font-size: .62rem; font-weight: 600; color: #64748b; margin: 3px 0 2px; }
    .dash-period-foot { font-size: .62rem; line-height: 1.35; color: #9ca3af; padding: 5px 7px 2px; border-top: 1px solid #f1f5f9; margin-top: 3px; }

    .kpi5-card { background: #fff; border-radius: .55rem; padding: .6rem .7rem; height: 100%;
        box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06); }
    .kpi5-top { display: flex; align-items: center; justify-content: space-between; margin-bottom: .4rem; }
    .kpi5-icon { width: 1.7rem; height: 1.7rem; border-radius: .45rem; background: #eff6ff; color: #2563eb;
        display: inline-flex; align-items: center; justify-content: center; font-size: .8rem; flex: none; }
    .kpi5-pill { font-size: .55rem; font-weight: 700; text-transform: uppercase; letter-spacing: .02em;
        background: var(--b-50); color: var(--b-700); padding: .12rem .4rem; border-radius: 999px; white-space: nowrap; }
    .kpi5-value { font-size: 1.05rem; font-weight: 700; color: #111827; line-height: 1.1; }
    .kpi5-label { font-size: .58rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-top: .1rem; }
    .kpi5-divider { border-top: 1px solid #f1f2f4; }
    .kpi5-foot { display: flex; }
    .kpi5-stat { flex: 1; min-width: 0; }
    .kpi5-stat + .kpi5-stat { border-left: 1px solid #f1f2f4; padding-left: .5rem; margin-left: .5rem; }
    .kpi5-stat .n { font-size: .64rem; font-weight: 700; color: #374151; }
    .kpi5-stat .l { font-size: .64rem; color: #adb3ba; text-transform: uppercase; letter-spacing: .02em; margin-left: .25rem; }

    .dash-wrap .card { border: 1px solid #e5e7eb; }
    .dash-wrap .card-header { background: #fff !important; font-size: .82rem; font-weight: 600; color: #1f2937; }
    .dash-wrap .card-header .hint { font-size: .68rem; color: #9ca3af; font-weight: 400; }
    .section-title { font-size: .72rem; font-weight: 700; color: #6b7280; text-transform: uppercase; letter-spacing: .05em; margin: 0 0 .45rem; }

    .list-card-body { height: 220px; overflow-y: auto; }
    .list-row { display: flex; align-items: center; justify-content: space-between; gap: .5rem; padding: .5rem .95rem;
        border-bottom: 1px solid #f1f2f4; font-size: .8rem; }
    .list-row:last-child { border-bottom: 0; }
    .list-row .name { color: #374151; font-weight: 500; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .list-row .meta { color: #9ca3af; font-size: .74rem; white-space: nowrap; }
    .empty-row { padding: .8rem .95rem; color: #9ca3af; font-size: .8rem; }

    .urgency-pill { font-size: .68rem; font-weight: 600; padding: .15rem .45rem; border-radius: .3rem; white-space: nowrap; }
    .urgency-soon { background: var(--b-600); color: #fff; }      /* ≤ 7 days */
    .urgency-week { background: var(--b-100); color: var(--b-800); } /* 8–14 days */
    .urgency-later { background: var(--b-50); color: var(--b-500); } /* later */

    /* "This period" tiles */
    .ps-grid { display: grid; grid-template-columns: repeat(5, 1fr); gap: .5rem; }
    @media (max-width: 991.98px) { .ps-grid { grid-template-columns: repeat(2, 1fr); } }
    .ps-tile { background: #fff; border: 1px solid #e5e7eb; border-radius: .55rem; padding: .5rem .7rem; }
    .ps-tile .v { font-size: 1rem; font-weight: 800; color: #111827; }
    .ps-tile .l { font-size: .62rem; color: #6b7280; text-transform: uppercase; letter-spacing: .03em; }

    /* Trend row (growth chart / funnel / tenant status) — kept short: one fixed chart height */
    .chart-box { position: relative; height: 200px; }
    .trend-row .card-header { padding: .45rem .75rem; }
    .trend-row .funnel-card-body { padding: .6rem .75rem; }

    /* Enquiry funnel / bar lists — Bootstrap progress bars, soft-blue track/fill */
    .funnel-list { display: flex; flex-direction: column; gap: .32rem; }
    .funnel-row { display: flex; align-items: center; gap: .55rem; }
    .funnel-label { flex: 0 0 5.7rem; font-size: .68rem; color: #475569; font-weight: 500; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .funnel-track { flex: 1; height: .5rem; border-radius: 999px; background: #eaf2ff; }
    .funnel-track .progress-bar { background-color: #60a5fa; border-radius: 999px; }
    .funnel-count { flex: 0 0 auto; min-width: 1.6rem; text-align: right; font-size: .72rem; font-weight: 700; color: #1e3a8a; }

    /* Revenue */
    .rev-big { font-size: 1.45rem; font-weight: 800; color: var(--b-900); line-height: 1.1; }
    .rev-delta { font-size: .72rem; font-weight: 700; padding: .1rem .45rem; border-radius: 999px; }
    .rev-delta.up { background: var(--b-600); color: #fff; }        /* direction is in the arrow, not the colour */
    .rev-delta.down { background: var(--b-100); color: var(--b-800); }
    .rev-delta.flat { background: var(--b-50); color: var(--b-500); }

    /* Tenant activity */
    .seat-track { width: 70px; height: .4rem; border-radius: 999px; background: var(--b-50); overflow: hidden; display: inline-block; vertical-align: middle; }
    .seat-fill { height: 100%; background: var(--b-300); }
    .seat-fill.high { background: var(--b-600); }   /* ≥ 90% */
    .seat-fill.full { background: var(--b-900); }   /* at / over the limit */
    .dash-wrap .table thead th { background: var(--b-50); color: var(--b-800); font-weight: 600; border-bottom-color: var(--b-100); }
    .dash-wrap a:not(.btn):not(.dropdown-item) { color: var(--b-600); }
    .dash-wrap a.name { color: #374151; }
    .dash-wrap a.name:hover { color: var(--b-600); }
</style>

<div class="dash-wrap">
    @if($range['note'])<div class="alert alert-info py-2 small">{{ $range['note'] }}</div>@endif

    {{-- KPI cards (always "now") --}}
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

    {{-- In the period (this month when no filter) --}}
    <div class="section-title"><i class="bi bi-calendar3"></i> {{ $periodSummary['label'] }}</div>
    <div class="ps-grid mb-3">
        <div class="ps-tile"><div class="v">{{ number_format($periodSummary['new_tenants']) }}</div><div class="l">New tenants</div></div>
        <div class="ps-tile"><div class="v">{{ number_format($periodSummary['new_employees']) }}</div><div class="l">New employees</div></div>
        <div class="ps-tile"><div class="v">{{ number_format($periodSummary['new_enquiries']) }}</div><div class="l">New enquiries</div></div>
        <div class="ps-tile"><div class="v">₹{{ number_format($periodSummary['collected']) }}</div><div class="l">Revenue collected</div></div>
        <div class="ps-tile"><div class="v">{{ number_format($periodSummary['logins']) }}</div><div class="l">Tenant logins</div></div>
    </div>

    {{-- Main trend + funnel + tenant status (always last 6 months / now) --}}
    <div class="row g-3 mb-3 trend-row">
        <div class="col-md-6"><div class="card h-100">
            <div class="card-header"><span>Tenants, employees &amp; revenue — last 6 months</span></div>
            <div class="card-body p-2"><div class="chart-box"><canvas id="growthChart"></canvas></div></div>
        </div></div>
        <div class="col-md-3"><div class="card h-100">
            <div class="card-header">Enquiry funnel</div>
            <div class="card-body funnel-card-body">
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
            <div class="card-body p-2"><div class="chart-box"><canvas id="statusChart"></canvas></div></div>
        </div></div>
    </div>

    {{-- Revenue & plans --}}
    <div class="section-title"><i class="bi bi-currency-rupee"></i> Revenue &amp; plans</div>
    <div class="row g-3 mb-3">
        <div class="col-lg-3 col-md-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Revenue collected</span><span class="hint">{{ $revenue['current_label'] }}</span></div>
            <div class="card-body">
                <div class="rev-big">₹{{ number_format($revenue['current']) }}</div>
                <div class="mt-2 d-flex align-items-center gap-2">
                    @if($revenue['change'] === null)
                        <span class="rev-delta flat">no earlier data</span>
                    @else
                        <span class="rev-delta {{ $revenue['change'] > 0 ? 'up' : ($revenue['change'] < 0 ? 'down' : 'flat') }}">
                            <i class="bi {{ $revenue['change'] > 0 ? 'bi-arrow-up' : ($revenue['change'] < 0 ? 'bi-arrow-down' : 'bi-dash') }}"></i> {{ abs($revenue['change']) }}%
                        </span>
                    @endif
                    <span class="small text-muted">vs {{ $revenue['previous_label'] }} (₹{{ number_format($revenue['previous']) }})</span>
                </div>
                <div class="small text-muted mt-3">MRR ₹{{ number_format($m?->mrr ?? 0) }} · ARR ₹{{ number_format($m?->arr ?? 0) }}</div>
            </div>
        </div></div>
        <div class="col-lg-3 col-md-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Revenue by plan</span><span class="hint">{{ $revenue['current_label'] }}</span></div>
            <div class="card-body">
                <div class="funnel-list">
                    @forelse($revenue['by_plan'] as $p)
                        <div class="funnel-row" title="{{ $p['n'] }} payment(s)">
                            <div class="funnel-label">{{ $p['plan'] }}</div>
                            <div class="progress funnel-track"><div class="progress-bar" style="width: {{ $p['pct'] }}%"></div></div>
                            <div class="funnel-count">₹{{ number_format($p['total']) }}</div>
                        </div>
                    @empty
                        <div class="text-muted small">No payments in this period.</div>
                    @endforelse
                </div>
            </div>
        </div></div>
        <div class="col-lg-3 col-md-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Plan mix</span><span class="hint">active tenants</span></div>
            <div class="card-body p-2"><div class="chart-box"><canvas id="planChart"></canvas></div></div>
        </div></div>
        <div class="col-lg-3 col-md-6" id="renewals"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Renewals due ≤ 30 days</span><span class="hint">{{ count($revenue['renewals']) }}</span></div>
            <div class="list-card-body">
                @forelse($revenue['renewals'] as $r)
                    <div class="list-row">
                        <a href="{{ route('tenants.show', $r['tenant_id']) }}" class="name" title="{{ $r['plan'] }}">{{ $r['tenant'] }} <span class="meta">· {{ $r['plan'] }}</span></a>
                        <span class="urgency-pill {{ $r['days_left'] <= 7 ? 'urgency-soon' : ($r['days_left'] <= 14 ? 'urgency-week' : 'urgency-later') }}">{{ $r['end_date']->format('d M') }}</span>
                    </div>
                @empty <div class="empty-row">No renewals in the next 30 days.</div> @endforelse
            </div>
        </div></div>
    </div>

    {{-- Security & usage --}}
    <div class="section-title"><i class="bi bi-shield-check"></i> Security &amp; usage</div>
    <div class="row g-3 mb-3">
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Tenant logins per day</span><span class="hint">{{ $security['chart_label'] }}</span></div>
            <div class="card-body p-2"><div class="chart-box"><canvas id="loginChart"></canvas></div></div>
        </div></div>
        <div class="col-lg-3 col-md-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Failed logins</span><a href="{{ route('audit-logs.index', ['action' => 'auth.login_failed']) }}" class="small">all →</a></div>
            <div class="card-body pb-1">
                <div class="d-flex align-items-baseline gap-2"><span class="rev-big">{{ number_format($security['failed_count']) }}</span><span class="small text-muted">{{ $security['failed_label'] }}</span></div>
            </div>
            <div>
                @forelse($security['failed_by_tenant'] as $f)
                    <div class="list-row">
                        @if($f['tenant_id'])<a href="{{ route('tenants.show', $f['tenant_id']) }}" class="name">{{ $f['tenant'] }}</a>@else<span class="name">{{ $f['tenant'] }}</span>@endif
                        <span class="meta">{{ $f['count'] }}</span>
                    </div>
                @empty <div class="empty-row">No failed logins.</div> @endforelse
            </div>
        </div></div>
        <div class="col-lg-3 col-md-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span>Recent super-admin actions</span><a href="{{ route('audit-logs.index') }}" class="small">audit →</a></div>
            <div class="list-card-body">
                @forelse($security['admin_actions'] as $a)
                    <div class="list-row" style="align-items:flex-start">
                        <span class="name" style="white-space:normal">
                            <span class="d-block">{{ $a['action'] }} @if($a['impersonating'])<span class="chip solid">impersonating</span>@endif</span>
                            <span class="meta">{{ $a['who'] }}{{ $a['tenant'] ? ' · ' . $a['tenant'] : '' }}</span>
                        </span>
                        <span class="meta">{{ \Illuminate\Support\Carbon::parse($a['when'])->diffForHumans(null, true) }}</span>
                    </div>
                @empty <div class="empty-row">No actions recorded.</div> @endforelse
            </div>
        </div></div>
    </div>

    {{-- Tenant activity --}}
    <div class="row g-3 mb-3">
        @foreach(['most_active' => ['Most active tenants', 'bi-graph-up-arrow'], 'least_active' => ['Least active tenants', 'bi-graph-down-arrow']] as $key => $title)
        <div class="col-lg-6"><div class="card h-100">
            <div class="card-header d-flex justify-content-between"><span><i class="bi {{ $title[1] }} me-1"></i>{{ $title[0] }}</span><span class="hint">logins · {{ $security['activity_label'] }}</span></div>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0 small align-middle">
                    <thead><tr><th class="ps-3">Tenant</th><th class="text-end">Logins</th><th>Seats used</th></tr></thead>
                    <tbody>
                    @forelse($security[$key] as $t)
                        <tr>
                            <td class="ps-3"><a href="{{ route('tenants.show', $t['id']) }}">{{ $t['name'] }}</a></td>
                            <td class="text-end fw-semibold">{{ $t['logins'] }}</td>
                            <td>
                                @if($t['seat_pct'] !== null)
                                    <span class="seat-track"><span class="seat-fill d-block {{ $t['seat_pct'] >= 100 ? 'full' : ($t['seat_pct'] >= 90 ? 'high' : '') }}" style="width: {{ min(100, $t['seat_pct']) }}%"></span></span>
                                    <span class="text-muted ms-1">{{ $t['users'] }}/{{ $t['limit'] }}</span>
                                @else
                                    <span class="text-muted">{{ $t['users'] }} · no limit</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="text-center text-muted py-3">No active tenants.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div></div>
        @endforeach
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
                        <span class="chip">{{ str_replace('_',' ',$e->status) }}</span>
                    </div>
                @empty <div class="empty-row">None.</div> @endforelse
                </div>
            </div>
        </div>
        <div class="col-lg-3" id="trials">
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
    // Period menu: "Custom range" reveals the from/to dates.
    const customBtn = document.getElementById('dashCustomBtn');
    const customBox = document.getElementById('dashCustom');
    if (customBtn && customBox) customBtn.addEventListener('click', () => {
        customBox.style.display = customBox.style.display === 'none' ? '' : 'none';
    });

    const months = @json($months);
    const monthLabels = months.map(m => {
        const [y, mo] = m.split('-');
        return new Date(y, mo - 1, 1).toLocaleString('en-US', { month: 'short' });
    });
    const tenantsData = @json(array_values($tenantsByMonth));
    const employeesData = @json(array_values($employeesByMonth));
    const revenueData = @json(array_values($revenueByMonth));
    const smallLegend = { position: 'top', align: 'end', labels: { boxWidth: 10, font: { size: 11, weight: 600 } } };

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
            plugins: { legend: smallLegend },
            scales: {
                x: { grid: { display: false }, border: { display: false }, ticks: { font: { size: 11, weight: 600 } } },
                y: { beginAtZero: true, ticks: { precision: 0 }, border: { display: false },
                    grid: { color: '#f1f2f4' }, title: { display: true, text: 'Count', font: { size: 10 } } },
                y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, border: { display: false },
                    title: { display: true, text: 'Revenue', font: { size: 10 } } },
            }
        }
    });

    const donut = (id, labels, data, colors) => new Chart(document.getElementById(id), {
        type: 'doughnut',
        data: { labels, datasets: [{ data, backgroundColor: colors }] },
        // Fixed 200px box (.chart-box) instead of the canvas's width-driven aspect ratio.
        options: { maintainAspectRatio: false, cutout: '62%',
            plugins: { legend: { position: 'bottom', labels: { boxWidth: 8, font: { size: 9 }, padding: 6 } } } }
    });

    donut('statusChart', ['Active', 'Trial', 'Suspended', 'Other'], [
        {{ $m?->active_tenants ?? 0 }},
        {{ $m?->trial_tenants ?? 0 }},
        {{ $m?->suspended_tenants ?? 0 }},
        Math.max(0, {{ $m?->total_tenants ?? 0 }} - {{ $m?->active_tenants ?? 0 }} - {{ $m?->trial_tenants ?? 0 }} - {{ $m?->suspended_tenants ?? 0 }}),
    ], ['#1e3a8a','#3b82f6','#93c5fd','#dbeafe']);

    const planMix = @json($revenue['mix']);
    donut('planChart', Object.keys(planMix), Object.values(planMix), ['#1e3a8a','#2563eb','#3b82f6','#60a5fa','#93c5fd','#bfdbfe','#dbeafe']);

    const login = @json($security['chart']);
    new Chart(document.getElementById('loginChart'), {
        type: 'bar',
        data: {
            labels: login.labels,
            datasets: [
                { label: 'Successful', data: login.success, backgroundColor: '#2563eb', borderRadius: 4, borderSkipped: false },
                { label: 'Failed', data: login.failed, backgroundColor: '#bfdbfe', borderRadius: 4, borderSkipped: false },
            ]
        },
        options: {
            maintainAspectRatio: false, categoryPercentage: 0.65, barPercentage: 0.9,
            plugins: { legend: smallLegend },
            scales: {
                x: { stacked: true, grid: { display: false }, border: { display: false }, ticks: { font: { size: 10 }, maxRotation: 0, autoSkip: true } },
                y: { stacked: true, beginAtZero: true, ticks: { precision: 0 }, border: { display: false }, grid: { color: '#f1f2f4' } },
            }
        }
    });
})();
</script>
@endsection
