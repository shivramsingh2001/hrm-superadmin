@extends('layouts.app')
@section('title', 'Audit Logs')

@section('content')
<style>
    .al-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .al-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .al-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .al-head .sub { font-size: .72rem; color: #9ca3af; }
    .al-filters { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .7rem .9rem; margin-bottom: .75rem; }
    .al-filters label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-bottom: .2rem; display: block; }
    .al-filters .form-control, .al-filters .form-select { font-size: .82rem; }
    table.al-table { font-size: .8rem; margin: 0; }
    table.al-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.al-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.al-table tbody tr:hover { background: #fafbfc; }
    table.al-table code { font-size: .72rem; color: #4338ca; background: #eef2ff; padding: .1rem .35rem; border-radius: .3rem; }
    table.al-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    table.al-table .diff { max-width: 340px; font-size: .72rem; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .actor-pill { font-size: .68rem; font-weight: 600; background: #f3f4f6; color: #374151; padding: .1rem .4rem; border-radius: .3rem; }
</style>

<div class="al-wrap">
    <div class="al-head">
        <div>
            <h2>Audit Logs</h2>
            <span class="sub">{{ $logs->total() }} record(s)</span>
        </div>
        <a href="{{ route('audit-logs.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-download"></i> Export CSV
        </a>
    </div>

    <div class="al-filters">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label>Actor</label>
                <select name="actor_id" class="form-select form-select-sm">
                    <option value="">Any actor</option>
                    @foreach($admins as $id => $name)<option value="{{ $id }}" @selected(request('actor_id')==$id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Tenant</label>
                <select name="tenant_id" class="form-select form-select-sm">
                    <option value="">Any tenant</option>
                    @foreach($tenants as $id => $name)<option value="{{ $id }}" @selected(request('tenant_id')==$id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label>Action</label>
                <select name="action" class="form-select form-select-sm">
                    <option value="">Any action</option>
                    @foreach($actions as $a)<option value="{{ $a }}" @selected(request('action')===$a)>{{ $a }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>From</label>
                <input name="from" type="date" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label>To</label>
                <input name="to" type="date" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-sm btn-primary" title="Filter"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('audit-logs.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table al-table align-middle mb-0">
            <thead><tr><th class="sr">Sr. No.</th><th>When</th><th>Actor</th><th>Tenant</th><th>Action</th><th>Entity</th><th>Change</th></tr></thead>
            <tbody>
            @forelse($logs as $log)
                <tr>
                    <td class="sr">{{ $logs->firstItem() + $loop->index }}</td>
                    <td class="text-secondary text-nowrap">{{ $log->created_at?->format('d M Y H:i:s') }}</td>
                    <td><span class="actor-pill">{{ $log->actor_type }} #{{ $log->actor_id }}</span></td>
                    <td>{{ $tenants[$log->tenant_id] ?? ($log->tenant_id ? '#'.$log->tenant_id : '—') }}</td>
                    <td><code>{{ $log->action }}</code></td>
                    <td class="text-secondary">{{ $log->entity_type }} {{ $log->entity_id }}</td>
                    <td><span class="diff" title="{{ json_encode($log->new_values) }}">{{ \Illuminate\Support\Str::limit(json_encode($log->new_values), 90) }}</span></td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-secondary">No audit records match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
</div>
@endsection
