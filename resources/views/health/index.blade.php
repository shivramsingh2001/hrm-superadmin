@extends('layouts.app')
@section('title', 'Tenant Health')

@section('content')
<style>
    .th-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .th-head { display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: .5rem;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .th-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .th-head .sub { font-size: .72rem; color: #9ca3af; }
    .th-sort { display: flex; gap: .3rem; }
    .th-sort a { font-size: .74rem; font-weight: 500; color: #6b7280; padding: .3rem .65rem; border-radius: .4rem; text-decoration: none; }
    .th-sort a:hover { background: #f3f4f6; color: #111827; }
    .th-sort a.active { background: #eff6ff; color: #2563eb; font-weight: 600; }
    table.th-table { font-size: .8rem; margin: 0; }
    table.th-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.th-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.th-table tbody tr:hover { background: #fafbfc; }
    table.th-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    .risk-pill { font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: .3rem; }
    .risk-ok { background: #dcfce7; color: #166534; }
    .risk-at-risk { background: #fef9c3; color: #854d0e; }
</style>

<div class="th-wrap">
    <div class="th-head">
        <div>
            <h2>Tenant Health</h2>
            <span class="sub">{{ $rows->total() }} tenant(s) · nightly snapshot — run <code style="font-size:.68rem">php artisan metrics:snapshot</code> to refresh</span>
        </div>
        <div class="th-sort">
            @foreach(['stale'=>'At-risk first','utilisation'=>'Seat utilisation','logins'=>'Logins (30d)'] as $k => $label)
                <a href="{{ route('health.index', ['sort' => $k]) }}" class="{{ $sort === $k ? 'active' : '' }}">{{ $label }}</a>
            @endforeach
        </div>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table th-table align-middle mb-0">
            <thead><tr><th class="sr">Sr. No.</th><th>Tenant</th><th>Users (active/total)</th><th>Logins 30d</th><th>Seats</th><th>Last activity</th><th>Status</th></tr></thead>
            <tbody>
            @forelse($rows as $h)
                <tr>
                    <td class="sr">{{ $rows->firstItem() + $loop->index }}</td>
                    <td><a href="{{ route('tenants.show', $h->tenant_id) }}" class="fw-semibold text-decoration-none">{{ $h->tenant->company_name ?? '#'.$h->tenant_id }}</a></td>
                    <td>{{ $h->users_active }} / {{ $h->users_total }}</td>
                    <td>{{ $h->logins_30d }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <span class="text-secondary">{{ $h->users_active }} / {{ $h->seat_limit ?: '∞' }}</span>
                            @if($h->seat_utilisation)
                                <div class="progress" style="height:5px;width:80px">
                                    <div class="progress-bar {{ $h->seat_utilisation > 90 ? 'bg-danger' : 'bg-primary' }}"
                                         style="width:{{ min(100, $h->seat_utilisation) }}%"></div>
                                </div>
                            @endif
                        </div>
                    </td>
                    <td class="text-secondary">{{ $h->last_activity_at ? $h->last_activity_at->diffForHumans() : '—' }}</td>
                    <td>@if($h->is_stale)<span class="risk-pill risk-at-risk">at risk</span>@else<span class="risk-pill risk-ok">ok</span>@endif</td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-secondary">No health data yet — run <code>php artisan metrics:snapshot</code>.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $rows->links() }}</div>
</div>
@endsection
