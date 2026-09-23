@extends('layouts.app')
@section('title', 'Panel Users')

@section('content')
<style>
    .su-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .su-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .su-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .su-head .sub { font-size: .72rem; color: #9ca3af; }
    .su-head .new-hint { font-size: .68rem; color: #adb3ba; margin-top: .15rem; }
    .su-filters { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .7rem .9rem; margin-bottom: .75rem; }
    .su-filters label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-bottom: .2rem; display: block; }
    .su-filters .form-control, .su-filters .form-select { font-size: .82rem; }
    table.su-table { font-size: .8rem; margin: 0; }
    table.su-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.su-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.su-table tbody tr:hover { background: #fafbfc; }
    table.su-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    .role-pill { font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: .3rem; background: #eff6ff; color: #2563eb; }
    .role-pill.custom { background: #fef3c7; color: #92400e; }
    .status-pill { font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: .3rem; }
    .status-active { background: #dcfce7; color: #166534; }
    .status-inactive { background: #fee2e2; color: #991b1b; }
    table.su-table .btn-icon { border: 0; background: transparent; color: #6b7280; padding: .2rem .4rem; line-height: 1; }
    table.su-table .btn-icon:hover { color: #2563eb; }
</style>

<div class="su-wrap">
    <div class="su-head">
        <div>
            <h2>Panel Users</h2>
            <span class="sub">{{ $users->total() }} user(s) with access to this Super Admin Panel</span>
        </div>
        <div class="text-end">
            <button type="button" class="btn btn-sm btn-primary"
                    data-drawer-url="{{ route('superadmin-users.create') }}?drawer=1" data-drawer-title="New panel user">
                <i class="bi bi-plus-lg"></i> New User
            </button>
            <div class="new-hint">Create a login for someone who needs access to this panel.</div>
        </div>
    </div>

    <div class="su-filters">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label>Search</label>
                <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Name or email">
            </div>
            <div class="col-md-3">
                <label>Role</label>
                <select name="role_id" class="form-select form-select-sm">
                    <option value="">Any role</option>
                    @foreach($panelRoles as $id => $name)
                        <option value="{{ $id }}" @selected((string) request('role_id') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2 d-flex gap-2">
                <button class="btn btn-sm btn-primary" title="Filter"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('superadmin-users.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table su-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Name</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Last login</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($users as $u)
                <tr>
                    <td class="sr">{{ $users->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold">{{ $u->name }}</td>
                    <td class="text-secondary">{{ $u->email }}</td>
                    <td class="text-secondary">{{ $u->mobile ?: '—' }}</td>
                    <td>
                        @if($u->panelRole)
                            <span class="role-pill {{ $u->panelRole->is_system ? '' : 'custom' }}">{{ $u->panelRole->name }}</span>
                        @else
                            <span class="text-secondary">—</span>
                        @endif
                    </td>
                    <td><span class="status-pill status-{{ $u->is_active ? 'active' : 'inactive' }}">{{ $u->is_active ? 'active' : 'inactive' }}</span></td>
                    <td class="text-secondary">{{ $u->last_login_at ? $u->last_login_at->diffForHumans() : 'never' }}</td>
                    <td class="text-end text-nowrap">
                        <button type="button" class="btn-icon" title="Edit"
                                data-drawer-url="{{ route('superadmin-users.edit', $u) }}?drawer=1" data-drawer-title="Edit · {{ $u->name }}">
                            <i class="bi bi-pencil fs-6"></i>
                        </button>
                        <form method="POST" action="{{ route('superadmin-users.reset-totp', $u) }}" class="d-inline"
                              onsubmit="return confirm('Reset TOTP for {{ $u->name }}? They will re-enrol at next sign-in.')">
                            @csrf
                            <button type="submit" class="btn-icon" title="Reset TOTP"><i class="bi bi-shield-lock fs-6"></i></button>
                        </form>
                        <form method="POST" action="{{ route('superadmin-users.toggle', $u) }}" class="d-inline">
                            @csrf
                            <button type="submit" class="btn-icon" title="{{ $u->is_active ? 'Deactivate' : 'Activate' }}">
                                <i class="bi {{ $u->is_active ? 'bi-pause-circle' : 'bi-play-circle' }} fs-6"></i>
                            </button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="8" class="text-secondary">No panel users yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $users->links() }}</div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="userDrawer" aria-labelledby="userDrawerLabel" style="width:520px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom d-block" style="padding-top:.9rem;padding-bottom:.9rem">
        <div class="d-flex align-items-start justify-content-between">
            <h6 class="offcanvas-title mb-0" id="userDrawerLabel" style="font-size:.9rem">User</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body px-4 py-3" id="userDrawerBody"></div>
</div>

<style>
    #userDrawer .offcanvas-body { padding-bottom: 0; }
    #userDrawer .pc-actions { padding-bottom: .9rem !important; }
</style>
@endsection

@section('scripts')
<script>
    (function () {
        var el = document.getElementById('userDrawer');
        var body = document.getElementById('userDrawerBody');
        var label = document.getElementById('userDrawerLabel');
        var oc = bootstrap.Offcanvas.getOrCreateInstance(el);

        document.querySelectorAll('[data-drawer-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                label.textContent = btn.dataset.drawerTitle || 'User';
                body.innerHTML = '<div class="text-secondary small p-2">Loading…</div>';
                oc.show();
                fetch(btn.dataset.drawerUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) { body.innerHTML = html; })
                    .catch(function () { body.innerHTML = '<div class="text-danger small p-2">Failed to load the form.</div>'; });
            });
        });
    })();
</script>
@endsection
