@php
    $grouped = [];
    foreach ($features as $key => $meta) {
        $grouped[$meta['group'] ?? 'Other'][$key] = $meta;
    }
@endphp

<style>
    .plan-compact { font-size: .8rem; }
    .plan-compact .pc-sec { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #6b7280; margin: 1rem 0 .4rem; }
    .plan-compact .pc-sec:first-child { margin-top: 0; }
    .plan-compact label.form-label { font-size: .68rem; font-weight: 500; color: #6b7280; margin-bottom: .1rem; }
    .plan-compact .form-control, .plan-compact .form-select {
        font-size: .78rem; padding: .25rem .5rem; height: calc(1.5em + .5rem + 2px); }
    .plan-compact textarea.form-control { height: auto; }
    .plan-compact .row.g-2 > [class*="col"] { margin-bottom: .3rem; }

    /* Feature set — exactly two per row, flex rows so the checkbox always shows fully */
    .plan-compact .feat-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 1.4rem; row-gap: .15rem; }
    .plan-compact .feat-grid .pc-group { grid-column: 1 / -1; font-size: .66rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .04em; color: #9ca3af; margin: .55rem 0 .1rem; padding-top: .3rem; border-top: 1px solid #eef0f2; }
    .plan-compact .feat-grid .pc-group:first-child { border-top: 0; margin-top: 0; padding-top: 0; }
    .plan-compact .fi { display: flex; gap: .45rem; align-items: center; padding: .2rem 0; margin: 0; cursor: pointer; }
    .plan-compact .fi > input { flex: 0 0 15px; width: 15px; height: 15px; }
    .plan-compact .fi .fn { font-size: .77rem; font-weight: 400; color: #374151; line-height: 1.25; }

    .plan-compact .pc-actions { position: sticky; bottom: 0; background: #fff; padding: .6rem 0 .2rem;
        margin-top: .7rem; border-top: 1px solid #e5e7eb; }

    @media (max-width: 640px) { .plan-compact .feat-grid { grid-template-columns: 1fr; } }
</style>

<div class="plan-compact">

@if($plan->exists && $tenantCount)
    <div class="alert alert-info py-1 px-2 mb-2" style="font-size:.72rem">
        <strong>{{ $tenantCount }}</strong> tenant(s) on this plan — feature edits apply to <strong>new subscriptions only</strong>.
    </div>
@endif

<form method="POST" action="{{ $plan->exists ? route('plans.update', $plan) : route('plans.store') }}">
    @csrf
    @if($plan->exists) @method('PUT') @endif

    <div class="pc-sec">Plan</div>
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label">Name</label>
            <input name="name" value="{{ old('name', $plan->name) }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-6">
            <label class="form-label">Slug</label>
            <input name="slug" value="{{ old('slug', $plan->slug) }}" class="form-control form-control-sm" placeholder="auto from name">
        </div>
        <div class="col-6">
            <label class="form-label">Sort order</label>
            <input name="sort_order" type="number" value="{{ old('sort_order', $plan->sort_order ?? 0) }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-6">
            <label class="form-label">Trial days</label>
            <input name="trial_days" type="number" value="{{ old('trial_days', $plan->trial_days ?? 0) }}" class="form-control form-control-sm" required>
        </div>
        <div class="col-12">
            <label class="form-label">Description</label>
            <textarea name="description" class="form-control form-control-sm" rows="2">{{ old('description', $plan->description) }}</textarea>
        </div>
    </div>

    <div class="pc-sec">Pricing</div>
    <div class="row g-2">
        <div class="col-6">
            <label class="form-label">Pricing type</label>
            <select name="pricing_type" class="form-select form-select-sm">
                @foreach(['fixed','per_employee_per_day','per_employee_per_month'] as $pt)
                    <option value="{{ $pt }}" @selected(old('pricing_type', $plan->pricing_type)===$pt)>{{ str_replace('_',' ',$pt) }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="form-label">Billing cycle</label>
            <select name="billing_cycle" class="form-select form-select-sm">
                @foreach(['monthly','quarterly','yearly','daily'] as $bc)
                    <option value="{{ $bc }}" @selected(old('billing_cycle', $plan->billing_cycle)===$bc)>{{ $bc }}</option>
                @endforeach
            </select>
        </div>
        <div class="col-6">
            <label class="form-label">Fixed price</label>
            <input name="price" type="number" step="0.01" value="{{ old('price', $plan->price) }}" class="form-control form-control-sm">
        </div>
        <div class="col-6">
            <label class="form-label">Price / employee</label>
            <input name="price_per_employee" type="number" step="0.01" value="{{ old('price_per_employee', $plan->price_per_employee) }}" class="form-control form-control-sm">
        </div>
    </div>

    <div class="pc-sec">Feature set</div>
    <div class="feat-grid">
        @foreach($grouped as $groupName => $items)
            <div class="pc-group">{{ $groupName }}</div>
            @foreach($items as $key => $meta)
                <label class="fi">
                    <input type="checkbox" name="features[{{ $key }}]" value="1"
                        @checked(old("features.$key", $plan->features[$key] ?? $meta['default']))>
                    <span class="fn">{{ $meta['name'] }}</span>
                </label>
            @endforeach
        @endforeach
    </div>

    @if($plan->exists)
        <div class="mt-2">
            <label class="form-label">Change note</label>
            <input name="note" class="form-control form-control-sm" placeholder="What changed and why (optional)">
        </div>
    @endif

    <div class="pc-actions">
        <button class="btn btn-sm btn-primary">{{ $plan->exists ? 'Save changes' : 'Create plan' }}</button>
        <a href="{{ route('plans.index') }}" class="btn btn-sm btn-link">Cancel</a>
    </div>
</form>

@if($plan->exists && $versions->isNotEmpty())
    <details class="mt-2">
        <summary class="text-secondary" style="font-size:.72rem;cursor:pointer">Version history ({{ $versions->count() }})</summary>
        <div class="table-responsive mt-1">
            <table class="table table-sm mb-0" style="font-size:.72rem">
                <thead><tr><th>v</th><th>When</th><th>Name</th><th>Price</th><th>Feat.</th><th>Note</th><th></th></tr></thead>
                <tbody>
                @foreach($versions as $v)
                    <tr>
                        <td>{{ $v->version }}</td>
                        <td class="text-secondary">{{ optional($v->created_at)->format('d M y H:i') }}</td>
                        <td>{{ $v->name }}</td>
                        <td>{{ number_format($v->price, 2) }}</td>
                        <td>{{ count(array_filter($v->features)) }}</td>
                        <td class="text-secondary">{{ $v->note }}</td>
                        <td>
                            @if(!$loop->first)
                            <form method="POST" action="{{ route('plans.revert', [$plan, $v]) }}"
                                  onsubmit="return confirm('Revert plan to v{{ $v->version }}?')">
                                @csrf<button class="btn btn-sm btn-outline-secondary py-0" style="font-size:.68rem">Revert</button>
                            </form>
                            @endif
                        </td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    </details>
@endif

</div>
