@extends('layouts.app')
@section('title', 'Subscription Plans')

@section('content')
@php
    $featTotal = count(config('features'));
    $activeCount = $plans->where('is_active', true)->count();
    $isSuper = auth()->user()->isSuperadmin();
@endphp

<style>
    .plans-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .plans-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem .5rem 0 0; border-bottom: 0; }
    .plans-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .plans-head .sub { font-size: .72rem; color: #9ca3af; }
    table.plans-table { font-size: .78rem; margin: 0; }
    table.plans-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .5rem .7rem; white-space: nowrap; }
    table.plans-table tbody td { padding: .5rem .7rem; vertical-align: middle; color: #374151; }
    table.plans-table tbody tr:hover { background: #fafbfc; }
    table.plans-table .sr { color: #adb3ba; width: 2.4rem; }
    table.plans-table code { font-size: .72rem; color: #6b7280; }
    table.plans-table .mod-badge { font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca;
        padding: .1rem .4rem; border-radius: .3rem; }
    table.plans-table .form-switch { min-height: auto; padding-left: 2.3em; }
    table.plans-table .form-switch .form-check-input { width: 2em; height: 1em; margin-top: .1em; cursor: pointer; }
    table.plans-table .btn-icon { border: 0; background: transparent; color: #6b7280; padding: .2rem .4rem; line-height: 1; }
    table.plans-table .btn-icon:hover { color: #2563eb; }
    table.plans-table .desc { max-width: 15rem; font-size: .72rem; color: #9ca3af; overflow: hidden;
        text-overflow: ellipsis; white-space: nowrap; }
    .plans-head .new-hint { font-size: .68rem; color: #adb3ba; margin-top: .15rem; }
</style>

<div class="plans-wrap">
    <div class="plans-head">
        <div>
            <h2>Subscription Plans</h2>
            <span class="sub">{{ $plans->count() }} total · {{ $activeCount }} active</span>
        </div>
        @if($isSuper)
            <div class="text-end">
                <button type="button" class="btn btn-sm btn-primary"
                        data-plan-url="{{ route('plans.create') }}?drawer=1" data-plan-title="New plan">
                    <i class="bi bi-plus-lg"></i> New plan
                </button>
                <div class="new-hint">Pricing tier + the feature set new tenants inherit.</div>
            </div>
        @endif
    </div>

    <div class="card rounded-top-0"><div class="table-responsive">
        <table class="table plans-table align-middle">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Slug</th>
                    <th>Pricing</th>
                    <th>Trial</th>
                    <th>Modules</th>
                    <th>Status</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @foreach($plans as $p)
                @php $onCount = count(array_filter($p->features ?? [])); @endphp
                <tr>
                    <td class="sr">{{ $loop->iteration }}</td>
                    <td class="fw-semibold">{{ $p->name }}</td>
                    <td><div class="desc" title="{{ $p->description }}">{{ $p->description ?: '—' }}</div></td>
                    <td><code>{{ $p->slug }}</code></td>
                    <td>
                        @if($p->pricing_type === 'fixed')
                            {{ number_format($p->price, 2) }} <span class="text-secondary">/ {{ $p->billing_cycle }}</span>
                        @else
                            {{ number_format($p->price_per_employee, 2) }}
                            <span class="text-secondary">· {{ str_replace('_', ' ', $p->pricing_type) }}</span>
                        @endif
                    </td>
                    <td>{{ $p->trial_days }}d</td>
                    <td>
                        <span class="mod-badge" title="{{ implode(', ', array_keys(array_filter($p->features ?? []))) }}">
                            {{ $onCount }} / {{ $featTotal }}
                        </span>
                    </td>
                    <td>
                        @if($isSuper)
                            <form method="POST" action="{{ route('plans.toggle', $p) }}" class="d-inline">
                                @csrf @method('PATCH')
                                <div class="form-check form-switch mb-0">
                                    <input type="checkbox" class="form-check-input" role="switch"
                                           @checked($p->is_active) onchange="this.form.submit()"
                                           title="{{ $p->is_active ? 'Active — click to deactivate' : 'Inactive — click to activate' }}">
                                </div>
                            </form>
                        @else
                            {!! $p->is_active
                                ? '<span class="badge bg-success">active</span>'
                                : '<span class="badge bg-secondary">inactive</span>' !!}
                        @endif
                    </td>
                    <td class="text-end">
                        @if($isSuper)
                            <button type="button" class="btn-icon" title="Edit plan"
                                    data-plan-url="{{ route('plans.edit', $p) }}?drawer=1" data-plan-title="Edit · {{ $p->name }}">
                                <i class="bi bi-pencil fs-6"></i>
                            </button>
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div></div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="planDrawer" aria-labelledby="planDrawerLabel"
     style="width:480px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom d-block" style="padding-top:.9rem;padding-bottom:.9rem">
        <div class="d-flex align-items-start justify-content-between">
            <h6 class="offcanvas-title mb-0" id="planDrawerLabel" style="font-size:.9rem">Plan</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
        <div class="text-secondary" style="font-size:.72rem;margin-top:.2rem">
            Define pricing and the feature set every tenant on this plan inherits.
        </div>
    </div>
    <div class="offcanvas-body px-4 py-3" id="planDrawerBody"></div>
</div>

<style>
    #planDrawer .offcanvas-body { padding-bottom: 0; }
    #planDrawer .pc-actions { padding-bottom: .9rem !important; }
</style>
@endsection

@section('scripts')
<script>
    (function () {
        var drawerEl = document.getElementById('planDrawer');
        var body = document.getElementById('planDrawerBody');
        var label = document.getElementById('planDrawerLabel');
        var oc = bootstrap.Offcanvas.getOrCreateInstance(drawerEl);

        document.querySelectorAll('[data-plan-url]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                label.textContent = btn.dataset.planTitle || 'Plan';
                body.innerHTML = '<div class="text-secondary small p-2">Loading…</div>';
                oc.show();
                fetch(btn.dataset.planUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) { body.innerHTML = html; })
                    .catch(function () { body.innerHTML = '<div class="text-danger small p-2">Failed to load the form.</div>'; });
            });
        });
    })();
</script>
@endsection
