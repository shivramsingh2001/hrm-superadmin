@extends('layouts.app')
@section('title', $tenant->company_name)

@section('content')
@if(session('provision_admin_password'))
    <div class="alert alert-success">
        <strong>Tenant provisioned.</strong> The tenant admin can sign in at
        <code>https://{{ $tenant->subdomain }}.hrmplatform…</code> with
        <code>{{ session('provision_admin_email') }}</code> and this one-time password
        (shown once — copy it now):
        <div class="mt-1"><code style="font-size:1.1rem">{{ session('provision_admin_password') }}</code></div>
    </div>
@elseif(session('provision_admin_password') === null && session()->has('provision_admin_email'))
    <div class="alert alert-info">Tenant already had an admin user — no new password generated.</div>
@endif
<div class="tenant-compact">
<div class="card tenant-header-card mb-3">
    <div class="card-body d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div class="d-flex align-items-start gap-3">
            <div class="tenant-avatar">{{ strtoupper(substr($tenant->company_name, 0, 1)) }}</div>
            <div>
                <h4 class="mb-1 d-flex align-items-center flex-wrap gap-2">
                    {{ $tenant->company_name }}
                    <span class="badge badge-status-{{ $tenant->status }}">{{ $tenant->status }}</span>
                </h4>
                <div class="d-flex flex-wrap align-items-center gap-2">
                    <span class="tenant-meta-chip"><i class="bi bi-globe2"></i>{{ $tenant->subdomain }}</span>
                    <span class="tenant-meta-chip"><i class="bi bi-box-seam"></i>{{ $plan->name ?? $tenant->subscription_plan ?? 'no plan' }}</span>
                    <span class="tenant-meta-chip"><i class="bi bi-people"></i>{{ $userCount }} / {{ $tenant->max_employees }} employees</span>
                    <span class="tenant-meta-chip"><i class="bi bi-calendar3"></i>created {{ optional($tenant->created_at)->format('d M Y') }}</span>
                    @if($tenant->trial_ends_at)
                        <span class="badge badge-status-trial">trial ends {{ $tenant->trial_ends_at->format('d M') }}</span>
                    @endif
                    @if($tenant->deletion_requested_at)
                        <span class="badge bg-danger">deletion {{ $tenant->deletion_requested_at->addDays($graceDays ?? config('lifecycle.deletion_grace_days'))->diffForHumans() }}</span>
                    @endif
                </div>
            </div>
        </div>
        <div class="d-flex gap-2">
            @if(auth()->user()->isSuperadmin())
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        data-tenant-url="{{ route('tenants.edit', $tenant) }}?drawer=1" data-tenant-title="Edit · {{ $tenant->company_name }}">
                    <i class="bi bi-pencil"></i> Edit
                </button>
            @endif
            @if($tenant->status === 'suspended')
                <form method="POST" action="{{ route('tenants.activate', $tenant) }}">@csrf
                    <button class="btn btn-sm btn-success"><i class="bi bi-play-circle"></i> Reactivate</button></form>
            @else
                <button class="btn btn-sm btn-outline-danger" data-bs-toggle="modal" data-bs-target="#suspendModal">
                    <i class="bi bi-pause-circle"></i> Suspend</button>
            @endif
        </div>
    </div>
</div>

@php
    $tabs = ['overview'=>'Overview','features'=>'Features','lifecycle'=>'Lifecycle','users'=>'Users','billing'=>'Billing','roles'=>'Roles','support'=>'Support','audit'=>'Audit'];
@endphp
<ul class="nav tenant-tabs mb-3" id="tenantTab">
    @foreach($tabs as $key => $label)
        <li class="nav-item">
            <a class="nav-link {{ $tab === $key ? 'active' : '' }}" href="{{ route('tenants.show', [$tenant, 'tab' => $key]) }}">{{ $label }}</a>
        </li>
    @endforeach
</ul>

@if($tab === 'overview')
    <div class="row g-3">
        <div class="col-lg-6"><div class="card"><div class="card-header bg-white fw-semibold">Company</div><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5">Legal name</dt><dd class="col-7">{{ $company->legal_name ?? '—' }}</dd>
                <dt class="col-5">Email</dt><dd class="col-7">{{ $tenant->email }}</dd>
                <dt class="col-5">Phone</dt><dd class="col-7">{{ $tenant->phone ?? '—' }}</dd>
                <dt class="col-5">Country</dt><dd class="col-7">{{ $tenant->country }}</dd>
                <dt class="col-5">Timezone</dt><dd class="col-7">{{ $tenant->timezone }}</dd>
                <dt class="col-5">Currency</dt><dd class="col-7">{{ $tenant->currency }}</dd>
            </dl>
        </div></div></div>
        <div class="col-lg-6"><div class="card"><div class="card-header bg-white fw-semibold">Subscription</div><div class="card-body">
            @php
                $pricingLabel = match($plan?->pricing_type) {
                    'per_employee_per_day' => '₹' . number_format($plan->price_per_employee ?? 0, 2) . ' / employee / day',
                    'per_employee_per_month' => '₹' . number_format($plan->price_per_employee ?? 0, 2) . ' / employee / month',
                    'fixed' => '₹' . number_format($plan->price ?? 0, 2) . ' (fixed)',
                    default => '—',
                };
                $endDate = $sub?->end_date;
                $daysLeft = $endDate ? now()->startOfDay()->diffInDays($endDate->copy()->startOfDay(), false) : null;
            @endphp
            <dl class="row mb-0 small">
                <dt class="col-5">Plan</dt><dd class="col-7">{{ $plan->name ?? '—' }}</dd>
                <dt class="col-5">Status</dt><dd class="col-7">{{ $sub->status ?? '—' }}</dd>
                <dt class="col-5">Pricing</dt><dd class="col-7">{{ $pricingLabel }}{{ $plan?->billing_cycle ? ' · billed ' . $plan->billing_cycle : '' }}</dd>
                <dt class="col-5">Amount paid</dt>
                <dd class="col-7">
                    @if($payment)
                        {{ $payment->currency }} {{ number_format($payment->amount, 2) }}
                        <span class="text-secondary">({{ $payment->payment_mode }}, {{ $payment->payment_date->format('d M Y') }})</span>
                    @else
                        <span class="text-secondary">no payment on file — list price {{ $pricingLabel }}</span>
                    @endif
                </dd>
                <dt class="col-5">Start</dt><dd class="col-7">{{ optional($sub?->start_date)->format('d M Y') ?? '—' }}</dd>
                <dt class="col-5">End</dt>
                <dd class="col-7">
                    @if(!$endDate)
                        open-ended
                    @elseif($daysLeft < 0)
                        {{ $endDate->format('d M Y') }} <span class="badge bg-danger">expired {{ abs((int) $daysLeft) }}d ago</span>
                    @else
                        {{ $endDate->format('d M Y') }} <span class="badge bg-info text-dark">{{ (int) $daysLeft }}d left</span>
                    @endif
                </dd>
                <dt class="col-5">Trial ends</dt><dd class="col-7">{{ optional($tenant->trial_ends_at)->format('d M Y') ?? '—' }}</dd>
            </dl>
        </div></div></div>

        <div class="col-lg-6"><div class="card"><div class="card-header bg-white fw-semibold">Usage</div><div class="card-body">
            <dl class="row mb-0 small">
                <dt class="col-5">Employees</dt>
                <dd class="col-7">{{ $userCount }} / {{ $sub->max_employees_override ?? $tenant->max_employees }}</dd>
                <dt class="col-5">Location tracking</dt>
                <dd class="col-7">
                    @if($tenant->field_tracking_enabled)
                        {{ $trackingSeatsUsed }} / {{ $tenant->field_tracking_seats }} seats in use
                    @else
                        <span class="text-secondary">Not enabled</span>
                    @endif
                </dd>
            </dl>
        </div></div></div>
    </div>

@elseif($tab === 'features')
    <div class="card"><div class="table-responsive"><table class="table mb-0 align-middle">
        <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Feature</th><th>Plan default</th><th>Effective</th><th>Source</th><th style="width:280px">Set override</th></tr></thead>
        <tbody>
        @foreach($matrix as $key => $f)
            <tr>
                <td class="text-secondary">{{ $loop->iteration }}</td>
                <td><strong>{{ $f['name'] }}</strong><br><span class="text-secondary small">{{ $f['description'] }}</span>
                    @if($f['override_reason'])<br><span class="text-secondary small fst-italic">“{{ $f['override_reason'] }}”</span>@endif
                </td>
                <td>{!! $f['plan_value'] ? '<span class="text-success">on</span>' : '<span class="text-secondary">off</span>' !!}</td>
                <td class="js-effective">{!! $f['effective'] ? '<span class="badge bg-success">enabled</span>' : '<span class="badge bg-secondary">disabled</span>' !!}</td>
                <td class="js-source"><span class="badge src-{{ $f['source'] }}">{{ $f['source'] }}</span></td>
                <td>
                    <div class="d-flex gap-1 align-items-center">
                        <form method="POST" action="{{ route('tenants.features', $tenant) }}" class="d-flex gap-2 align-items-center flex-grow-1 js-feature-toggle">
                            @csrf
                            <input type="hidden" name="feature_key" value="{{ $key }}">
                            <input type="hidden" name="action" value="{{ $f['effective'] ? 'disable' : 'enable' }}">
                            <input name="reason" class="form-control form-control-sm" placeholder="reason (optional)" style="min-width:90px">
                            <div class="form-check form-switch mb-0" title="Turn this feature on/off for this tenant">
                                <input class="form-check-input" type="checkbox" role="switch" style="width:2.4em;height:1.25em;cursor:pointer" @checked($f['effective'])>
                            </div>
                        </form>
                        <form method="POST" action="{{ route('tenants.features', $tenant) }}" class="js-clear-override {{ $f['source'] === 'override' ? '' : 'd-none' }}">
                            @csrf
                            <input type="hidden" name="feature_key" value="{{ $key }}">
                            <button name="action" value="clear" class="btn btn-sm btn-outline-danger" title="Clear override, fall back to plan default">×</button>
                        </form>
                    </div>
                </td>
            </tr>
        @endforeach
        </tbody>
    </table></div></div>

@elseif($tab === 'lifecycle')
    @php($isSuper = auth()->user()->isSuperadmin())

    {{-- Row 1: entitlement basics --}}
    <div class="row g-3 mb-3">
        <div class="col-md-4">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Change plan</div><div class="card-body">
                <form method="POST" action="{{ route('tenants.change-plan', $tenant) }}" class="row g-2">@csrf
                    <div class="col-6"><select name="plan_id" class="form-select form-select-sm" required>
                        @foreach($plans as $p)<option value="{{ $p->id }}" @selected($p->id == $tenant->subscription_plan_id)>{{ $p->name }} ({{ $p->slug }})</option>@endforeach
                    </select></div>
                    <div class="col-6"><input type="date" name="effective_from" class="form-control form-control-sm" title="Effective from (blank = now)" placeholder="Effective from"></div>
                    <div class="col-12"><input name="note" class="form-control form-control-sm" placeholder="note (optional)"></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary w-100" {{ $isSuper ? '' : 'disabled' }}>Apply</button></div>
                </form>
                {{-- <p class="text-secondary small mt-2 mb-0">Ends the current subscription and opens a new one with a fresh feature snapshot. Existing tenants keep their old snapshot until this runs.</p> --}}
            </div></div>
        </div>

        <div class="col-md-4">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Employee limits</div><div class="card-body">
                <form method="POST" action="{{ route('tenants.limits', $tenant) }}" class="row g-2">@csrf
                    <div class="col-6"><label class="form-label small">Max employees (-1 = ∞)</label>
                        <input name="max_employees" type="number" value="{{ $tenant->max_employees }}" class="form-control form-control-sm"></div>
                    <div class="col-6"><label class="form-label small">Subscription override</label>
                        <input name="max_employees_override" type="number" min="0" value="{{ $sub->max_employees_override ?? '' }}" class="form-control form-control-sm" placeholder="blank = none"></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary" {{ $isSuper ? '' : 'disabled' }}>Save limits</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-md-4">
            <div class="card h-100"><div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span>Location tracking (GPS)</span>
                    <span class="badge bg-light text-dark border fw-normal">{{ $trackingSeatsUsed }} / {{ $tenant->field_tracking_seats ?? 0 }} seats</span>
                </div><div class="card-body">
                <form method="POST" action="{{ route('tenants.tracking', $tenant) }}" class="row g-2">@csrf
                    <div class="col-6 d-flex align-items-end">
                        <div class="form-check">
                            <input type="checkbox" name="field_tracking_enabled" value="1" class="form-check-input" id="ft_enabled" @checked($tenant->field_tracking_enabled)>
                            <label for="ft_enabled" class="form-check-label small">Enabled</label>
                        </div>
                    </div>
                    <div class="col-6"><label class="form-label small">Seats purchased</label>
                        <input name="field_tracking_seats" type="number" min="0" value="{{ $tenant->field_tracking_seats ?? 0 }}" class="form-control form-control-sm"></div>
                    <div class="col-12"><button class="btn btn-sm btn-primary" {{ $isSuper ? '' : 'disabled' }}>Save</button></div>
                </form>
            </div></div>
        </div>
    </div>

    {{-- Row 2: term --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Subscription duration</div><div class="card-body">
                <p class="text-secondary small mb-2">Set an end date without recording a payment (e.g. a courtesy extension or correcting the term). For a paid renewal use "Renew subscription" instead.</p>
                <form method="POST" action="{{ route('tenants.duration', $tenant) }}" class="row g-2">@csrf
                    <div class="col-6"><select name="duration_months" class="form-select form-select-sm">
                        <option value="">— custom date —</option>
                        <option value="1">1 month</option>
                        <option value="3">3 months</option>
                        <option value="6">6 months</option>
                        <option value="12">12 months</option>
                    </select></div>
                    <div class="col-6"><input type="date" name="end_date" class="form-control form-control-sm"></div>
                    <div class="col-12"><button class="btn btn-sm btn-outline-primary" {{ $isSuper ? '' : 'disabled' }}>Set duration</button></div>
                </form>
            </div></div>
        </div>

        <div class="col-md-6">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Subscription history</div>
                <div class="table-responsive"><table class="table mb-0">
                    <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Plan</th><th>Status</th><th>Start</th><th>End</th></tr></thead>
                    <tbody>
                    @forelse($subTimeline as $s)
                        <tr><td class="text-secondary">{{ $loop->iteration }}</td><td>{{ optional($plans->firstWhere('id', $s->plan_id))->name ?? '#'.$s->plan_id }}</td>
                            <td><span class="badge bg-secondary">{{ $s->status }}</span></td>
                            <td>{{ optional($s->start_date)->format('d M Y') }}</td>
                            <td>{{ optional($s->end_date)->format('d M Y') ?? ($s->trial_ends_at ? 'trial '.$s->trial_ends_at->format('d M') : 'ongoing') }}</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No subscription rows.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    {{-- Row 3: renew subscription + trial --}}
    <div class="row g-3 mb-3">
        <div class="{{ $tenant->trial_ends_at ? 'col-md-6' : 'col-md-12' }}">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Renew subscription</div><div class="card-body">
                <form method="POST" action="{{ route('tenants.renew', $tenant) }}" class="row g-2">@csrf
                    <div class="col-4"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
                    <div class="col-4"><input name="currency" class="form-control form-control-sm" value="{{ $tenant->currency ?? 'INR' }}" required></div>
                    <div class="col-4"><select name="payment_mode" class="form-select form-select-sm">
                        <option value="upi">UPI</option><option value="bank_transfer">Bank</option><option value="cheque">Cheque</option><option value="cash">Cash</option><option value="card">Card</option></select></div>
                    <div class="col-6"><input name="reference_number" class="form-control form-control-sm" placeholder="Reference / UTR" required></div>
                    <div class="col-6"><input name="payment_date" type="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-8"><input name="new_end_date" type="date" class="form-control form-control-sm" title="Extend subscription to" required></div>
                    <div class="col-4"><button class="btn btn-sm btn-primary w-100">Renew</button></div>
                </form>
                <p class="text-secondary small mt-2 mb-0">Just changing modules, not the term? Use the <strong>Edit modules</strong> card below — no payment needed.</p>
            </div></div>
        </div>

        @if($tenant->trial_ends_at)
            <div class="col-md-6">
                <div class="card h-100"><div class="card-header bg-white fw-semibold">Trial</div><div class="card-body">
                    <form method="POST" action="{{ route('tenants.extend-trial', $tenant) }}" class="row g-2 mb-2">@csrf
                        <div class="col-8"><input type="date" name="trial_ends_at" class="form-control form-control-sm" required></div>
                        <div class="col-4"><button class="btn btn-sm btn-outline-primary w-100" {{ $isSuper ? '' : 'disabled' }}>Extend</button></div>
                    </form>
                    <form method="POST" action="{{ route('tenants.convert-trial', $tenant) }}" class="row g-2">@csrf
                        <div class="col-4"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
                        <div class="col-4"><input name="currency" class="form-control form-control-sm" value="{{ $tenant->currency ?? 'INR' }}" required></div>
                        <div class="col-4"><select name="payment_mode" class="form-select form-select-sm">
                            <option value="upi">UPI</option><option value="bank_transfer">Bank</option><option value="cheque">Cheque</option><option value="cash">Cash</option><option value="card">Card</option></select></div>
                        <div class="col-6"><input name="reference_number" class="form-control form-control-sm" placeholder="Reference" required></div>
                        <div class="col-6"><input name="payment_date" type="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required></div>
                        <div class="col-8"><input name="end_date" type="date" class="form-control form-control-sm" title="Paid-until date" required></div>
                        <div class="col-4"><button class="btn btn-sm btn-success w-100">Convert</button></div>
                    </form>
                </div></div>
            </div>
        @endif
    </div>

    {{-- Row 4: edit modules + edit history --}}
    <div class="row g-3 mb-3">
        <div class="col-md-6">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Edit modules</div><div class="card-body">
                <p class="text-secondary small mb-2">Turn modules on/off for this tenant right now — no payment or renewal needed. For one module at a time with a reason logged, use the <a href="{{ route('tenants.show', [$tenant, 'tab' => 'features']) }}">Features tab</a> instead.</p>
                <form method="POST" action="{{ route('tenants.modules', $tenant) }}">
                    @csrf
                    <div class="border rounded p-2 mb-2 module-check-grid">
                        @foreach($matrix as $key => $f)
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" name="features[{{ $key }}]" value="1"
                                       id="mfeat_{{ $key }}" @checked($f['effective'])>
                                <label class="form-check-label small" for="mfeat_{{ $key }}" title="{{ $f['description'] }}">{{ $f['name'] }}</label>
                            </div>
                        @endforeach
                    </div>
                    <button class="btn btn-sm btn-primary" {{ $isSuper ? '' : 'disabled' }}>Save modules</button>
                </form>
            </div></div>
        </div>

        <div class="col-md-6">
            <div class="card h-100"><div class="card-header bg-white fw-semibold">Renewal &amp; edit history</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th style="width:2.6rem">Sr. No.</th><th>When</th><th>Action</th><th>Detail</th></tr></thead>
                    <tbody>
                    @forelse($history as $h)
                        <tr>
                            <td class="text-secondary">{{ $loop->iteration }}</td>
                            <td class="text-secondary" style="white-space:nowrap">{{ $h['when'] }}</td>
                            <td><span class="badge bg-light text-dark border">{{ $h['label'] }}</span></td>
                            <td class="text-secondary small">{{ $h['detail'] }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="text-secondary">No renewals or edits yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

    {{-- Row 5: danger zone --}}
    <div class="row g-3">
        <div class="col-12">
            <div class="card border-danger"><div class="card-header bg-white fw-semibold text-danger">Danger zone</div><div class="card-body">
                @if($tenant->deletion_requested_at)
                    <p class="small">Deletion requested {{ $tenant->deletion_requested_at->format('d M Y') }} — executes
                        {{ $tenant->deletion_requested_at->addDays($graceDays)->format('d M Y') }}.</p>
                    <form method="POST" action="{{ route('tenants.cancel-deletion', $tenant) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary" {{ $isSuper ? '' : 'disabled' }}>Cancel deletion</button></form>
                @else
                    <form method="POST" action="{{ route('tenants.request-deletion', $tenant) }}" class="d-flex gap-2">@csrf
                        <input name="reason" class="form-control form-control-sm" placeholder="Reason for deletion" required>
                        <button class="btn btn-sm btn-outline-danger" {{ $isSuper ? '' : 'disabled' }}>Request deletion</button>
                    </form>
                    <p class="text-secondary small mt-2 mb-0">{{ $graceDays }}-day grace period. Then PII is anonymised and the tenant soft-deleted. Audit log is kept.</p>
                @endif
            </div></div>
        </div>
    </div>

@elseif($tab === 'users')
    @php($canImpersonateUsers = in_array(auth()->user()->role, ['superadmin', 'support'], true))
    <p class="text-secondary small mb-2">@if($canImpersonateUsers)Click an active user's name to impersonate them and open their HRM dashboard.@endif</p>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Name</th><th>Email</th><th>Role</th><th>Emp ID</th><th>Status</th></tr></thead>
        <tbody>
        @forelse($users as $u)
            @php($userActive = (string) $u->status === '1')
            <tr><td class="text-secondary">{{ $users->firstItem() + $loop->index }}</td>
                <td>
                    @if($canImpersonateUsers && $userActive)
                        <form method="POST" action="{{ route('tenants.impersonate', $tenant) }}" class="d-inline">
                            @csrf
                            <input type="hidden" name="tenant_user_id" value="{{ $u->id }}">
                            <button type="submit" class="btn-link-plain" title="Impersonate {{ $u->name }} →">{{ $u->name }}</button>
                        </form>
                    @else
                        {{ $u->name }}
                    @endif
                </td>
                <td>{{ $u->email }}</td><td>{{ $u->role }}</td>
                <td>{{ $u->employee_id }}</td>
                <td>{{ $userActive ? 'active' : 'inactive' }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-secondary">No users.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $users->links() }}</div>

@elseif($tab === 'billing')
    <div class="card mb-3"><div class="card-header bg-white fw-semibold">Record a payment</div><div class="card-body">
        <form method="POST" action="{{ route('tenants.payments', $tenant) }}" class="row g-2">
            @csrf
            <div class="col-md-2"><input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="Amount" required></div>
            <div class="col-md-1"><input name="currency" class="form-control form-control-sm" value="{{ $tenant->currency ?? 'INR' }}" required></div>
            <div class="col-md-2"><select name="payment_mode" class="form-select form-select-sm">
                <option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                <option value="cheque">Cheque</option><option value="cash">Cash</option><option value="card">Card</option>
            </select></div>
            <div class="col-md-2"><input name="reference_number" class="form-control form-control-sm" placeholder="Reference #" required></div>
            <div class="col-md-2"><input name="payment_date" type="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required></div>
            <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Save</button></div>
            <div class="col-12"><input name="notes" class="form-control form-control-sm" placeholder="Notes (optional)"></div>
        </form>
    </div>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Date</th><th>Amount</th><th>Mode</th><th>Reference</th><th>Notes</th></tr></thead>
        <tbody>
        @forelse($payments as $p)
            <tr><td class="text-secondary">{{ $loop->iteration }}</td><td>{{ $p->payment_date->format('d M Y') }}</td>
                <td>{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                <td>{{ $p->payment_mode }}</td><td><code>{{ $p->reference_number }}</code></td>
                <td class="text-secondary">{{ $p->notes }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-secondary">No payments recorded.</td></tr>
        @endforelse
        </tbody>
    </table></div></div>

@elseif($tab === 'roles')
    @php($isSuper = auth()->user()->isSuperadmin())
    <p class="text-secondary small">Roles &amp; the permission matrix for this tenant. System roles
        (<code>admin / hr / manager / employee</code>) are locked. The HRM reads these via
        <code>role_permissions</code> once its users carry a <code>role_id</code>.</p>

    @forelse($roles as $role)
        @php($rolePerms = $role->permissions->groupBy('module')->map(fn($g) => $g->pluck('action')->all()))
        @php($rolePermScopes = $role->permissions->keyBy(fn($p) => $p->module.':'.$p->action)->map(fn($p) => $p->scope ?? 'company'))
        <div class="card mb-3">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <span>
                    <strong>{{ $role->name }}</strong> <code>{{ $role->slug }}</code>
                    @if($role->is_system)<span class="badge bg-secondary">system</span>@else<span class="badge bg-info text-dark">custom</span>@endif
                    <span class="text-secondary small ms-2">{{ $roleUserCounts[$role->id] ?? 0 }} user(s)</span>
                </span>
                @if($isSuper && !$role->is_system)
                    <div class="d-flex gap-1">
                        <form method="POST" action="{{ route('tenants.roles.clone', [$tenant, $role]) }}" class="d-flex gap-1">@csrf
                            <select name="target_tenant_id" class="form-select form-select-sm" style="width:150px">
                                <option value="">clone to…</option>
                                @foreach($otherTenants as $id => $name)<option value="{{ $id }}">{{ $name }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-outline-secondary">Clone</button>
                        </form>
                        <form method="POST" action="{{ route('tenants.roles.destroy', [$tenant, $role]) }}"
                              onsubmit="return confirm('Delete role {{ $role->name }}?')">@csrf @method('DELETE')
                            <button class="btn btn-sm btn-outline-danger">Delete</button></form>
                    </div>
                @endif
            </div>
            <div class="card-body p-0">
                <form method="POST" action="{{ route('tenants.roles.permissions', [$tenant, $role]) }}">@csrf
                <div class="table-responsive"><table class="table table-sm mb-0 text-center align-middle">
                    <thead><tr><th class="text-start">Module</th>@foreach($actions as $a)<th>{{ $a }}</th>@endforeach</tr></thead>
                    <tbody>
                    @foreach($modules as $mkey => $mlabel)
                        <tr>
                            <td class="text-start">{{ $mlabel }} <code class="small">{{ $mkey }}</code></td>
                            @foreach($actions as $a)
                                @php($granted = in_array($a, $rolePerms[$mkey] ?? []))
                                @php($currentScope = $rolePermScopes[$mkey.':'.$a] ?? 'company')
                                <td>
                                    <div class="d-flex flex-column align-items-center gap-1">
                                        <input type="checkbox" name="perm[{{ $mkey }}][{{ $a }}]" value="1"
                                            class="perm-check" data-scope-select="scope-{{ $role->id }}-{{ $mkey }}-{{ $a }}"
                                            @checked($granted)
                                            {{ ($isSuper && !$role->is_system) ? '' : 'disabled' }}>
                                        <select name="scope[{{ $mkey }}][{{ $a }}]"
                                            id="scope-{{ $role->id }}-{{ $mkey }}-{{ $a }}"
                                            class="form-select form-select-sm" style="width:5.5rem; font-size:.65rem; padding:.1rem .3rem;"
                                            {{ ($isSuper && !$role->is_system) ? '' : 'disabled' }}>
                                            @foreach($scopes as $skey => $slabel)
                                                <option value="{{ $skey }}" @selected($currentScope === $skey)>{{ ucfirst($skey) }}</option>
                                            @endforeach
                                        </select>
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                    </tbody>
                </table></div>
                @if($isSuper && !$role->is_system)
                    <div class="p-2 border-top"><button class="btn btn-sm btn-primary">Save matrix</button></div>
                @endif
                </form>
            </div>
        </div>
    @empty
        <p class="text-secondary">No roles for this tenant yet.</p>
    @endforelse

    @if($isSuper)
        <div class="card"><div class="card-header bg-white fw-semibold">Add a custom role</div><div class="card-body">
            <form method="POST" action="{{ route('tenants.roles.store', $tenant) }}" class="row g-2">@csrf
                <div class="col-md-4"><input name="name" class="form-control form-control-sm" placeholder="Role name (e.g. HR Manager)" required></div>
                <div class="col-md-3"><input name="slug" class="form-control form-control-sm" placeholder="slug (optional)"></div>
                <div class="col-md-2"><button class="btn btn-sm btn-primary w-100">Create role</button></div>
            </form>
        </div></div>
    @endif

    <script>
        document.querySelectorAll('.perm-check').forEach(function (cb) {
            var sel = document.getElementById(cb.dataset.scopeSelect);
            if (!sel) return;
            var sync = function () { sel.disabled = !cb.checked || cb.disabled; };
            cb.addEventListener('change', sync);
            sync();
        });
    </script>

@elseif($tab === 'support')
    @php($canImpersonate = in_array(auth()->user()->role, ['superadmin','support'], true))
    <div class="row g-3">
        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-person-badge me-1 text-primary"></i> Impersonate a tenant user</div><div class="card-body">
                @if($canImpersonate)
                    <form method="POST" action="{{ route('tenants.impersonate', $tenant) }}" class="d-flex gap-2">@csrf
                        <select name="tenant_user_id" class="form-select form-select-sm" required>
                            <option value="">— pick a user —</option>
                            @foreach($tenantUsers as $u)
                                <option value="{{ $u->id }}" @disabled((string)$u->status !== '1')>
                                    {{ $u->name }} · {{ $u->role }} @if((string)$u->status !== '1')(inactive)@endif
                                </option>
                            @endforeach
                        </select>
                        <button class="btn btn-sm btn-primary text-nowrap">Impersonate <i class="bi bi-arrow-right"></i></button>
                    </form>
                    <p class="text-secondary small mt-2 mb-0">Opens the tenant's HRM as that user, with a banner. Session hard-expires after {{ config('platform.impersonation_ttl_minutes') }} min. All of it is logged.</p>
                @else
                    <p class="text-secondary small mb-0">Your role cannot impersonate.</p>
                @endif
            </div></div>

            <div class="card"><div class="card-header bg-white fw-semibold d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-1 text-primary"></i> Recent impersonations</span>
                    <span class="badge bg-light text-dark border">{{ count($impersonations) }}</span>
                </div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th style="width:2.6rem">Sr. No.</th><th>When</th><th>By</th><th>As user</th><th>State</th></tr></thead>
                    <tbody>
                    @forelse($impersonations as $s)
                        <tr><td class="text-secondary">{{ $loop->iteration }}</td><td class="text-secondary">{{ $s->started_at->format('d M H:i') }}</td>
                            <td>{{ $s->superAdmin->name ?? '#'.$s->super_admin_id }}</td>
                            <td>#{{ $s->tenant_user_id }}</td>
                            <td>@if($s->isLive())<span class="badge bg-success">live</span>@elseif($s->ended_at)<span class="badge bg-secondary">{{ $s->end_reason }}</span>@else<span class="badge bg-warning text-dark">expired</span>@endif</td></tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary text-center py-3"><i class="bi bi-inbox"></i> None yet.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>

        <div class="col-lg-6">
            <div class="card mb-3"><div class="card-header bg-white fw-semibold"><i class="bi bi-sticky me-1 text-primary"></i> Support notes</div><div class="card-body">
                <form method="POST" action="{{ route('tenants.notes.store', $tenant) }}" class="d-flex gap-2 mb-3">@csrf
                    <input name="body" class="form-control form-control-sm" placeholder="Add a note" required>
                    <button class="btn btn-sm btn-outline-primary text-nowrap">Add</button>
                </form>
                <div style="max-height:280px;overflow-y:auto">
                    @forelse($notes as $n)
                        <div class="d-flex gap-2 py-2 {{ !$loop->last ? 'border-bottom' : '' }}">
                            <div class="tenant-avatar" style="width:1.8rem;height:1.8rem;font-size:.68rem;flex:none">{{ strtoupper(substr($n->author->name ?? '?', 0, 1)) }}</div>
                            <div class="flex-grow-1">
                                <div class="small">{{ $n->body }}</div>
                                <div class="text-secondary" style="font-size:11px">
                                    {{ $n->author->name ?? 'unknown' }} · {{ $n->created_at->diffForHumans() }}
                                    <form method="POST" action="{{ route('tenants.notes.destroy', [$tenant, $n]) }}" class="d-inline">@csrf @method('DELETE')
                                        <button class="btn btn-link btn-sm p-0 text-danger ms-1" style="font-size:11px">delete</button></form>
                                </div>
                            </div>
                        </div>
                    @empty
                        <p class="text-secondary small mb-0 text-center py-3"><i class="bi bi-inbox"></i> No notes.</p>
                    @endforelse
                </div>
            </div></div>

            <div class="card"><div class="card-header bg-white fw-semibold"><i class="bi bi-box-arrow-in-right me-1 text-primary"></i> Recent logins</div>
                <div class="table-responsive"><table class="table table-sm mb-0">
                    <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Name</th><th class="text-end">Last login</th></tr></thead>
                    <tbody>
                    @forelse($recentLogins as $u)
                        <tr><td class="text-secondary">{{ $loop->iteration }}</td><td>{{ $u->name }}</td><td class="text-secondary text-end">{{ \Illuminate\Support\Carbon::parse($u->last_login_at)->diffForHumans() }}</td></tr>
                    @empty
                        <tr><td colspan="3" class="text-secondary text-center py-3"><i class="bi bi-inbox"></i> No login data.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>
        </div>
    </div>

@elseif($tab === 'audit')
    <form method="GET" action="{{ route('tenants.show', $tenant) }}" class="d-flex gap-2 mb-3" style="max-width:420px">
        <input type="hidden" name="tab" value="audit">
        <input type="search" name="audit_q" value="{{ $auditQuery }}" class="form-control form-control-sm"
               placeholder="Search action, entity or actor name…">
        <button class="btn btn-sm btn-outline-primary text-nowrap"><i class="bi bi-search"></i> Search</button>
        @if($auditQuery !== '')
            <a href="{{ route('tenants.show', [$tenant, 'tab' => 'audit']) }}" class="btn btn-sm btn-outline-secondary" title="Clear search">×</a>
        @endif
    </form>
    <div class="card"><div class="table-responsive"><table class="table mb-0">
        <thead><tr><th style="width:2.6rem">Sr. No.</th><th>When</th><th>Actor</th><th>Actor name</th><th>Action</th><th>Entity</th></tr></thead>
        <tbody>
        @forelse($logs as $log)
            @php($actorName = $log->actor_type === 'tenant_user' ? ($actorUserNames[$log->actor_id] ?? null) : ($actorAdminNames[$log->actor_id] ?? null))
            <tr><td class="text-secondary">{{ $logs->firstItem() + $loop->index }}</td><td class="text-secondary">{{ $log->created_at?->format('d M H:i') }}</td>
                <td><span class="badge bg-light text-dark border">{{ $log->actor_type }}</span> #{{ $log->actor_id }}</td>
                <td>{{ $actorName ?? '—' }}{{ $log->actor_type === 'tenant_user' ? ' (employee)' : '' }}</td>
                <td><code>{{ $log->action }}</code></td>
                <td>{{ $log->entity_type }} {{ $log->entity_id }}</td></tr>
        @empty
            <tr><td colspan="6" class="text-secondary text-center py-3"><i class="bi bi-inbox"></i> {{ $auditQuery !== '' ? 'No audit entries match your search.' : 'No audit entries.' }}</td></tr>
        @endforelse
        </tbody>
    </table></div></div>
    <div class="mt-3">{{ $logs->links() }}</div>
@endif
</div>

<div class="modal fade" id="suspendModal" tabindex="-1">
  <div class="modal-dialog"><div class="modal-content">
    <form method="POST" action="{{ route('tenants.suspend', $tenant) }}">@csrf
      <div class="modal-header"><h5 class="modal-title">Suspend {{ $tenant->company_name }}</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
      <div class="modal-body">
        <p class="small text-secondary">All tenant logins are blocked immediately (the HRM app rejects login when <code>tenant.status != active</code>). Recorded to the audit log.</p>
        <label class="form-label small">Reason</label>
        <select name="reason_code" class="form-select mb-2" required>
            @foreach(config('lifecycle.suspension_reasons') as $code => $label)
                <option value="{{ $code }}">{{ $label }}</option>
            @endforeach
        </select>
        <textarea name="note" class="form-control" rows="2" placeholder="Note (optional)"></textarea>
      </div>
      <div class="modal-footer"><button class="btn btn-danger">Suspend tenant</button></div>
    </form>
  </div></div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="tenantDrawer" aria-labelledby="tenantDrawerLabel"
     style="width:520px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom d-block" style="padding-top:.9rem;padding-bottom:.9rem">
        <div class="d-flex align-items-start justify-content-between">
            <h6 class="offcanvas-title mb-0" id="tenantDrawerLabel" style="font-size:.9rem">Tenant</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body px-4 py-3" id="tenantDrawerBody"></div>
</div>

<style>
    #tenantDrawer .offcanvas-body { padding-bottom: 0; }
    #tenantDrawer .pc-actions { padding-bottom: .9rem !important; }
    .btn-link-plain { border: 0; background: transparent; padding: 0; color: #1f2937; font-size: inherit;
        font-weight: 500; text-align: left; }
    .btn-link-plain:hover { color: #2563eb; text-decoration: underline; }

    /* Compact UI pass: smaller cards/fonts/margins/padding across every tab of this page. */
    .tenant-compact { font-size: .8125rem; }
    .tenant-compact h4 { font-size: 1.15rem; }
    .tenant-compact .row.g-3 { --bs-gutter-x: .75rem; --bs-gutter-y: .6rem; }
    .tenant-compact .card { margin-bottom: .6rem; border-radius: .55rem; border-color: #e5e7eb;
        box-shadow: 0 1px 2px rgba(16, 24, 40, .04); }
    .tenant-compact .card-header { padding: .45rem .75rem; font-size: .8rem; background: #f8fafc; border-bottom-color: #eef1f6; }
    .tenant-compact .card-body { padding: .65rem .75rem; }
    .tenant-compact .card-body.p-0 { padding: 0 !important; }
    .tenant-compact dl.row { margin-bottom: 0; }
    .tenant-compact dl.row dt, .tenant-compact dl.row dd { font-size: .78rem; margin-bottom: .3rem; padding-top: 0; padding-bottom: 0; }
    .tenant-compact dl.row dt { color: #6b7280; font-weight: 500; }
    .tenant-compact .table { font-size: .78rem; }
    .tenant-compact .table th, .tenant-compact .table td { padding: .35rem .5rem; }
    .tenant-compact .table th { color: #6b7280; font-weight: 600; text-transform: uppercase; font-size: .66rem;
        letter-spacing: .03em; border-bottom-color: #eef1f6; }
    .tenant-compact .table-sm th, .tenant-compact .table-sm td { padding: .3rem .4rem; }
    .tenant-compact .badge { font-size: .68rem; padding: .28em .55em; border-radius: 999px; font-weight: 600; }
    .tenant-compact .form-label { font-size: .72rem; margin-bottom: .2rem; }
    .tenant-compact .form-control-sm, .tenant-compact .form-select-sm { font-size: .78rem; padding: .25rem .5rem; min-height: calc(1.5em + .5rem + 2px); }
    .tenant-compact .form-control-sm:focus, .tenant-compact .form-select-sm:focus { border-color: #93c5fd; box-shadow: 0 0 0 .18rem rgba(37, 99, 235, .15); }
    .tenant-compact .btn-sm { font-size: .74rem; padding: .25rem .6rem; border-radius: .4rem; }
    .tenant-compact .btn-primary { background: #2563eb; border-color: #2563eb; }
    .tenant-compact .btn-primary:hover { background: #1d4ed8; border-color: #1d4ed8; }
    .tenant-compact .btn-outline-primary { color: #2563eb; border-color: #bfdbfe; }
    .tenant-compact .btn-outline-primary:hover { background: #2563eb; border-color: #2563eb; }
    .tenant-compact a { color: #2563eb; }
    .tenant-compact .form-check { margin-bottom: .15rem; }
    .tenant-compact .form-check-label { font-size: .75rem; }
    .tenant-compact .form-check-input:checked { background-color: #2563eb; border-color: #2563eb; }

    /* Header card */
    .tenant-header-card { border-top: 3px solid #2563eb; }
    .tenant-header-card .card-body { padding: .85rem 1rem; }
    .tenant-avatar { width: 2.75rem; height: 2.75rem; border-radius: .65rem; background: #eff6ff; color: #2563eb;
        display: flex; align-items: center; justify-content: center; font-size: 1.1rem; font-weight: 700; flex: none; }
    .tenant-meta-chip { display: inline-flex; align-items: center; gap: .35rem; background: #f8fafc;
        border: 1px solid #eef1f6; border-radius: .4rem; padding: .2rem .55rem; font-size: .72rem; color: #4b5563; }
    .tenant-meta-chip i { color: #93a0b4; font-size: .75rem; }

    /* Edit modules checklist: a strict 2-per-row grid so long labels never break the pairing */
    .module-check-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: .75rem; row-gap: .35rem; }
    .module-check-grid .form-check { margin-bottom: 0; }
    @media (max-width: 575.98px) { .module-check-grid { grid-template-columns: 1fr; } }

    /* Tabs: compact pill-style segmented control, single rounded outer border */
    .tenant-tabs { display: flex; flex-wrap: nowrap; align-items: stretch; gap: 3px; padding: 3px;
        background: #fff; border: 1px solid #e5e7eb; border-radius: 10px; box-shadow: 0 1px 2px rgba(16, 24, 40, .04);
        margin-bottom: .85rem !important; overflow-x: auto; }
    .tenant-tabs .nav-item { flex: 1 1 0; min-width: 0; margin: 0; }
    .tenant-tabs .nav-link { display: flex; align-items: center; justify-content: center; width: 100%;
        height: 2rem; padding: 0 .7rem; margin: 0; font-size: .78rem; font-weight: 600; line-height: 1;
        white-space: nowrap; color: #4b5563; background: #fff; border: 0; border-radius: 7px;
        transition: background .15s ease, color .15s ease; }
    .tenant-tabs .nav-link:hover:not(.active) { background: #f1f5fd; color: #2563eb; }
    .tenant-tabs .nav-link.active { background: #2563eb; color: #fff; }
    @media (max-width: 767.98px) { .tenant-tabs { flex-wrap: wrap; } .tenant-tabs .nav-item { flex: 1 1 calc(25% - 3px); } }
    .tenant-compact p.small, .tenant-compact .small { font-size: .76rem; }
    .tenant-compact code { font-size: .74rem; color: #7c3aed; background: #f5f3ff; padding: .1rem .3rem; border-radius: .3rem; }
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

    // Features tab: the switch saves the override in place, no page reload.
    document.querySelectorAll('.js-feature-toggle').forEach(function (form) {
        var sw = form.querySelector('[role="switch"]');
        var action = form.querySelector('input[name="action"]');
        var row = form.closest('tr');

        form.addEventListener('submit', function (e) { e.preventDefault(); });

        sw.addEventListener('change', function () {
            var wantOn = sw.checked;
            action.value = wantOn ? 'enable' : 'disable';
            sw.disabled = true;

            // getAttribute: form.action is shadowed by the <input name="action">.
            fetch(form.getAttribute('action'), {
                method: 'POST',
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: new FormData(form)
            })
                .then(function (r) {
                    return r.json().then(function (data) { if (!r.ok) throw data; return data; });
                })
                .then(function (data) {
                    row.querySelector('.js-effective').innerHTML = data.effective
                        ? '<span class="badge bg-success">enabled</span>'
                        : '<span class="badge bg-secondary">disabled</span>';
                    row.querySelector('.js-source').innerHTML =
                        '<span class="badge src-' + data.source + '">' + data.source + '</span>';
                    row.querySelector('.js-clear-override').classList.remove('d-none');
                    action.value = data.effective ? 'disable' : 'enable';
                    saToast(data.message, 'success');
                })
                .catch(function (err) {
                    sw.checked = !wantOn;
                    saToast((err && err.message) || 'Could not update the feature.', 'error');
                })
                .finally(function () { sw.disabled = false; });
        });
    });
</script>
@endsection
