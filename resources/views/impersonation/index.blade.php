@extends('layouts.app')
@section('title', 'Impersonation')

@section('content')
@php($canImpersonate = in_array(auth()->user()->role, ['superadmin', 'support'], true))
<style>
    .im-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .im-head { padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .im-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .im-head .sub { font-size: .72rem; color: #9ca3af; }
    table.im-table { font-size: .8rem; margin: 0; }
    table.im-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.im-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.im-table tbody tr:hover { background: #fafbfc; }
    table.im-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    .state-pill { font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: .3rem; }
    .state-live { background: #dcfce7; color: #166534; }
    .state-ended { background: #f3f4f6; color: #6b7280; }
    .state-expired { background: #fef9c3; color: #854d0e; }
    .btn-link-plain { border: 0; background: transparent; padding: 0; color: #1f2937; font-size: inherit; font-weight: inherit; text-align: left; }
    .btn-link-plain:hover { color: #2563eb; text-decoration: underline; }
</style>

<div class="im-wrap">
    <div class="im-head">
        <h2>Impersonation</h2>
        <span class="sub">{{ $sessions->total() }} session(s) · hard-expire after {{ config('platform.impersonation_ttl_minutes') }} minutes regardless of activity — every session is logged</span>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table im-table align-middle mb-0">
            <thead><tr><th class="sr">Sr. No.</th><th>Started</th><th>Super admin</th><th>Tenant</th><th>As user</th><th>Expires</th><th>State</th><th class="text-end">Action</th></tr></thead>
            <tbody>
            @forelse($sessions as $s)
                <tr>
                    <td class="sr">{{ $sessions->firstItem() + $loop->index }}</td>
                    <td class="text-secondary">{{ $s->started_at->format('d M H:i') }}</td>
                    <td class="fw-semibold">{{ $s->superAdmin->name ?? '#'.$s->super_admin_id }}</td>
                    <td>
                        @php($rowAdmin = $primaryAdmins[$s->tenant_id] ?? null)
                        @if($canImpersonate && $rowAdmin)
                            <form method="POST" action="{{ route('tenants.impersonate', $s->tenant_id) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="tenant_user_id" value="{{ $rowAdmin->id }}">
                                <button type="submit" class="btn-link-plain" title="Impersonate {{ $rowAdmin->name }} (admin) →">{{ $s->tenant->company_name ?? '#'.$s->tenant_id }}</button>
                            </form>
                        @else
                            <a href="{{ route('tenants.show', $s->tenant_id) }}" class="text-decoration-none">{{ $s->tenant->company_name ?? '#'.$s->tenant_id }}</a>
                        @endif
                    </td>
                    <td class="text-secondary">
                        @php($rowUserActive = $s->tenantUser && (string) $s->tenantUser->status === '1')
                        @if($canImpersonate && $rowUserActive)
                            <form method="POST" action="{{ route('tenants.impersonate', $s->tenant_id) }}" class="d-inline">
                                @csrf
                                <input type="hidden" name="tenant_user_id" value="{{ $s->tenant_user_id }}">
                                <button type="submit" class="btn-link-plain" title="Impersonate {{ $s->tenantUser->name }} again →">{{ $s->tenantUser->name }}</button>
                            </form>
                        @else
                            {{ $s->tenantUser->name ?? '#'.$s->tenant_user_id }}
                        @endif
                    </td>
                    <td class="text-secondary">{{ optional($s->expires_at)->format('d M H:i') }}</td>
                    <td>
                        @if($s->isLive())<span class="state-pill state-live">live</span>
                        @elseif($s->ended_at)<span class="state-pill state-ended">ended · {{ $s->end_reason }}</span>
                        @else<span class="state-pill state-expired">expired</span>@endif
                    </td>
                    <td class="text-end">
                        @if($s->isLive())
                            <form method="POST" action="{{ route('impersonation.end', $s->session_token) }}">@csrf
                                <button class="btn btn-sm btn-outline-danger">Force end</button></form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-secondary">No impersonation sessions.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $sessions->links() }}</div>
</div>
@endsection
