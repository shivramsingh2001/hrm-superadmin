@extends('layouts.app')
@section('title', 'Tenants')

@section('content')
@php
    $activeCount = \App\Models\Tenant::where('status', 'active')->count();
    $isSuper = auth()->user()->isSuperadmin();
    $canImpersonate = in_array(auth()->user()->role, ['superadmin', 'support'], true);
@endphp

<style>
    .tenants-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .tenants-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .tenants-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .tenants-head .sub { font-size: .72rem; color: #9ca3af; }
    .tenants-filters { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .7rem .9rem; margin-bottom: .75rem; }
    .tenants-filters label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-bottom: .2rem; display: block; }
    .tenants-filters .form-control, .tenants-filters .form-select { font-size: .82rem; }
    table.tenants-table { font-size: .8rem; margin: 0; }
    table.tenants-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.tenants-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.tenants-table tbody tr:hover { background: #fafbfc; }
    table.tenants-table .sr { color: #adb3ba; width: 2.4rem; }
    table.tenants-table code { font-size: .72rem; color: #6b7280; }
    table.tenants-table .company-logo { width: 26px; height: 26px; border-radius: .35rem; object-fit: cover; border: 1px solid #e5e7eb; }
    table.tenants-table .company-logo-fallback { width: 26px; height: 26px; border-radius: .35rem; background: #eef2ff; color: #4338ca;
        font-size: .68rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; }
    table.tenants-table .plan-badge { font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; padding: .1rem .4rem; border-radius: .3rem; }
    table.tenants-table .btn-icon { border: 0; background: transparent; color: #6b7280; padding: .2rem .4rem; line-height: 1; }
    table.tenants-table .btn-icon:hover { color: #2563eb; }
    .tenants-head .new-hint { font-size: .68rem; color: #adb3ba; margin-top: .15rem; }
    .btn-link-plain { border: 0; background: transparent; padding: 0; color: #1f2937; font-size: inherit;
        font-weight: 600; text-align: left; }
    .btn-link-plain:hover { color: #2563eb; text-decoration: underline; }
</style>

<div class="tenants-wrap">
    <div class="tenants-head">
        <div>
            <h2>Tenants</h2>
            <span class="sub">{{ $tenants->total() }} total · {{ $activeCount }} active</span>
        </div>
        @if($isSuper)
            <div class="text-end">
                <button type="button" class="btn btn-sm btn-primary"
                        data-tenant-url="{{ route('tenants.create') }}?drawer=1" data-tenant-title="Create tenant">
                    <i class="bi bi-plus-lg"></i> Create Tenant
                </button>
                <div class="new-hint">Provision a new company on the platform.</div>
            </div>
        @endif
    </div>

    <div class="tenants-filters">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-4">
                <label>Search</label>
                <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Company, subdomain, email or phone">
            </div>
            <div class="col-md-2">
                <label>Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Any status</option>
                    @foreach(['active','suspended','trial','expired'] as $s)
                        <option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst($s) }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3">
                <label>Plan</label>
                <select name="plan_id" class="form-select form-select-sm">
                    <option value="">Any plan</option>
                    @foreach($allPlans as $p)
                        <option value="{{ $p->id }}" @selected((string) request('plan_id') === (string) $p->id)>{{ $p->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-3 d-flex gap-2">
                <button class="btn btn-sm btn-primary"><i class="bi bi-funnel"></i> Filter</button>
                <a href="{{ route('tenants.index') }}" class="btn btn-sm btn-outline-secondary">Reset</a>
            </div>
        </form>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table tenants-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Company</th>
                    <th>Company code</th>
                    <th>Email</th>
                    <th>Contact no.</th>
                    <th>Plan</th>
                    <th>Employees</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($tenants as $t)
                <tr>
                    <td class="sr">{{ $tenants->firstItem() + $loop->index }}</td>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            @if($t->logo)
                                <img src="{{ file_url($t->logo, 'tenant_logo') }}" class="company-logo" alt="">
                            @else
                                <span class="company-logo-fallback">{{ strtoupper(substr($t->company_name, 0, 1)) }}</span>
                            @endif
                            @php($admin = $primaryAdmins[$t->id] ?? null)
                            @if($canImpersonate && $admin)
                                <form method="POST" action="{{ route('tenants.impersonate', $t) }}" class="d-inline">
                                    @csrf
                                    <input type="hidden" name="tenant_user_id" value="{{ $admin->id }}">
                                    <button type="submit" class="btn-link-plain fw-semibold" title="Impersonate {{ $admin->name }} (admin) →">{{ $t->company_name }}</button>
                                </form>
                            @else
                                <span class="fw-semibold" title="{{ $canImpersonate ? 'No active admin user to impersonate' : '' }}">{{ $t->company_name }}</span>
                            @endif
                        </div>
                    </td>
                    <td><code>{{ $t->subdomain }}</code></td>
                    <td class="text-secondary">{{ $t->email }}</td>
                    <td class="text-secondary">{{ $t->phone ?: '—' }}</td>
                    <td><span class="plan-badge">{{ $plans[$t->subscription_plan_id] ?? $t->subscription_plan ?? '—' }}</span></td>
                    <td>{{ $headcounts[$t->id] ?? 0 }} / {{ $t->max_employees }}</td>
                    <td><span class="badge badge-status-{{ $t->status }}">{{ $t->status }}</span></td>
                    <td class="text-secondary">{{ optional($t->created_at)->format('d M Y') }}</td>
                    <td class="text-end">
                        <a href="{{ route('tenants.show', $t) }}" class="btn-icon" title="View">
                            <i class="bi bi-eye fs-6"></i>
                        </a>
                        @if($isSuper)
                            <button type="button" class="btn-icon" title="Edit"
                                    data-tenant-url="{{ route('tenants.edit', $t) }}?drawer=1" data-tenant-title="Edit · {{ $t->company_name }}">
                                <i class="bi bi-pencil fs-6"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-secondary">No tenants match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $tenants->links() }}</div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="tenantDrawer" aria-labelledby="tenantDrawerLabel"
     style="width:520px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom d-block" style="padding-top:.9rem;padding-bottom:.9rem">
        <div class="d-flex align-items-start justify-content-between">
            <h6 class="offcanvas-title mb-0" id="tenantDrawerLabel" style="font-size:.9rem">Tenant</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="text-secondary" style="font-size:.72rem;margin-top:.2rem">
            Provision a new company: account, admin user, subscription and defaults.
        </div>
    </div>
    <div class="offcanvas-body px-4 py-3" id="tenantDrawerBody"></div>
</div>

<style>
    #tenantDrawer .offcanvas-body { padding-bottom: 0; }
    #tenantDrawer .pc-actions { padding-bottom: .9rem !important; }
</style>
@endsection

@section('scripts')
<script>
    (function () {
        var drawerEl = document.getElementById('tenantDrawer');
        var body = document.getElementById('tenantDrawerBody');
        var label = document.getElementById('tenantDrawerLabel');
        var oc = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

        document.querySelectorAll('[data-tenant-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                label.textContent = btn.dataset.tenantTitle || 'Tenant';
                body.innerHTML = '<div class="text-secondary small p-2">Loading…</div>';
                oc.show();
                fetch(btn.dataset.tenantUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        body.innerHTML = html;
                        // innerHTML doesn't execute <script> tags — re-insert them so the wizard/plan JS runs.
                        body.querySelectorAll('script').forEach(function (oldScript) {
                            var newScript = document.createElement('script');
                            Array.from(oldScript.attributes).forEach(function (attr) { newScript.setAttribute(attr.name, attr.value); });
                            newScript.textContent = oldScript.textContent;
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });
                    })
                    .catch(function () { body.innerHTML = '<div class="text-danger small p-2">Failed to load the form.</div>'; });
            });
        });
    })();
</script>
@endsection
