@php
    // preserveKeys: the inner loop's $key must stay the feature key (features[attendance]),
    // not a 0..n index — the server reads features.{key}.
    $featureGroups = collect($features)->groupBy('group', true);
@endphp

<style>
    .tc-compact { font-size: .8rem; }
    .tc-compact .pc-sec { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #6b7280; margin: 1rem 0 .4rem; }
    .tc-compact .pc-sec:first-child { margin-top: 0; }
    .tc-compact label.form-label { font-size: .7rem; font-weight: 500; color: #6b7280; margin-bottom: .2rem; }
    .tc-compact .form-control, .tc-compact .form-select {
        font-size: .85rem; padding: .3rem .75rem; height: auto; }
    .tc-compact .row.g-2 { --bs-gutter-x: 1.5rem; }
    .tc-compact .row.g-2 > [class*="col"] { margin-bottom: .55rem; }

    .tc-steps { display: flex; align-items: center; gap: .4rem; margin-bottom: 1rem; }
    .tc-steps .tc-dot { display: flex; align-items: center; gap: .4rem; font-size: .7rem; color: #adb3ba; }
    .tc-steps .tc-dot .n { width: 1.35rem; height: 1.35rem; border-radius: 50%; background: #f1f2f4; color: #9ca3af;
        font-size: .68rem; font-weight: 700; display: inline-flex; align-items: center; justify-content: center; flex: none; }
    .tc-steps .tc-dot.active .n { background: #2563eb; color: #fff; }
    .tc-steps .tc-dot.done .n { background: #dcfce7; color: #166534; }
    .tc-steps .tc-dot.active, .tc-steps .tc-dot.done { color: #374151; font-weight: 600; }
    .tc-steps .tc-line { flex: 1; height: 1px; background: #e5e7eb; }

    /* Feature set grid — matches the plan-editor drawer */
    .tc-compact .feat-grid { display: grid; grid-template-columns: 1fr 1fr; column-gap: 1.4rem; row-gap: .15rem; }
    .tc-compact .feat-grid .pc-group { grid-column: 1 / -1; font-size: .66rem; font-weight: 600; text-transform: uppercase;
        letter-spacing: .04em; color: #9ca3af; margin: .55rem 0 .1rem; padding-top: .3rem; border-top: 1px solid #eef0f2; }
    .tc-compact .feat-grid .pc-group:first-child { border-top: 0; margin-top: 0; padding-top: 0; }
    .tc-compact .fi { display: flex; gap: .45rem; align-items: center; padding: .2rem 0; margin: 0; cursor: pointer;
        border-radius: .3rem; transition: background .15s; }
    .tc-compact .fi > input { flex: 0 0 15px; width: 15px; height: 15px; }
    .tc-compact .fi .fn { font-size: .77rem; font-weight: 400; color: #374151; line-height: 1.25; }
    @media (max-width: 640px) { .tc-compact .feat-grid { grid-template-columns: 1fr; } }

    .tc-logo-drop { border: 1px dashed #d1d5db; border-radius: .5rem; padding: .8rem; text-align: center; cursor: pointer; background: #fafbfc; }
    .tc-logo-drop:hover { border-color: #93c5fd; background: #f0f7ff; }
    .tc-logo-preview { width: 56px; height: 56px; border-radius: .5rem; object-fit: cover; border: 1px solid #e5e7eb; }

    .tc-compact .pc-actions { position: sticky; bottom: 0; background: #fff; padding: .6rem 0 .2rem;
        margin-top: .7rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }
</style>

<div class="tc-compact">

@if($inquiry)
    <div class="alert alert-info py-1 px-2 mb-2" style="font-size:.72rem">
        Provisioning from enquiry #{{ $inquiry->id }} — <strong>{{ $inquiry->company_name }}</strong>.
    </div>
@endif

<div class="tc-steps">
    <div class="tc-dot active" data-step-dot="1"><span class="n">1</span> Company basics</div>
    <div class="tc-line"></div>
    <div class="tc-dot" data-step-dot="2"><span class="n">2</span> Plan &amp; modules</div>
</div>

<form method="POST" action="{{ route('tenants.store') }}" enctype="multipart/form-data" id="tenantCreateForm">
    @csrf
    <input type="hidden" name="inquiry_id" value="{{ $inquiry->id ?? '' }}">

    {{-- STEP 1 — Company basics --}}
    <div class="tc-panel" data-step="1">
        <div class="pc-sec">Company</div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">Company name *</label>
                <input name="company_name" value="{{ old('company_name', $prefill['company_name'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. Acme Technologies Pvt Ltd" required></div>
            <div class="col-6"><label class="form-label">Display name</label>
                <input name="display_name" value="{{ old('display_name') }}" class="form-control form-control-sm" placeholder="Short name shown in the app"></div>
            <div class="col-6"><label class="form-label">Company code * <span class="text-secondary" style="text-transform:none">(careers page &amp; mobile app login)</span></label>
                <input name="subdomain" value="{{ old('subdomain', $prefill['subdomain'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. acme-tech" required pattern="[a-z0-9-]{3,50}"></div>
            <div class="col-6"><label class="form-label">Legal name</label>
                <input name="legal_name" value="{{ old('legal_name') }}" class="form-control form-control-sm" placeholder="Registered legal entity name"></div>
        </div>

        <div class="pc-sec">Admin user</div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">Admin name *</label>
                <input name="admin_name" value="{{ old('admin_name', $prefill['admin_name'] ?? '') }}" class="form-control form-control-sm" placeholder="e.g. Priya Sharma" required></div>
            <div class="col-6"><label class="form-label">Admin email *</label>
                <input type="email" name="admin_email" value="{{ old('admin_email', $prefill['admin_email'] ?? '') }}" class="form-control form-control-sm" placeholder="admin@company.com" required></div>
            <div class="col-6"><label class="form-label">Admin contact (10 digits)</label>
                <input name="admin_contact" value="{{ old('admin_contact') }}" class="form-control form-control-sm" placeholder="e.g. 9876543210" maxlength="10"></div>
            <div class="col-6"><label class="form-label">Company phone</label>
                <input name="phone" value="{{ old('phone', $prefill['phone'] ?? '') }}" class="form-control form-control-sm" placeholder="Company landline / phone"></div>
        </div>

        <div class="pc-sec">Locale</div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">Country *</label>
                <input name="country" value="{{ old('country', $prefill['country'] ?? 'India') }}" class="form-control form-control-sm" placeholder="e.g. India" required></div>
            <div class="col-6"><label class="form-label">Timezone *</label>
                <input name="timezone" value="{{ old('timezone', 'Asia/Kolkata') }}" class="form-control form-control-sm" placeholder="e.g. Asia/Kolkata" required></div>
            <div class="col-6"><label class="form-label">Currency *</label>
                <input name="currency" value="{{ old('currency', 'INR') }}" class="form-control form-control-sm" placeholder="INR" required></div>
            <div class="col-6"><label class="form-label">Symbol</label>
                <input name="currency_symbol" value="{{ old('currency_symbol', '₹') }}" class="form-control form-control-sm" placeholder="₹"></div>
        </div>

        <div class="pc-sec">Registration &amp; address</div>
        <div class="row g-2">
            <div class="col-6"><label class="form-label">GST number</label>
                <input name="gst_number" value="{{ old('gst_number') }}" class="form-control form-control-sm" placeholder="15-character GSTIN"></div>
            <div class="col-6"><label class="form-label">PAN number</label>
                <input name="pan_number" value="{{ old('pan_number') }}" class="form-control form-control-sm" placeholder="10-character PAN"></div>
            <div class="col-6"><label class="form-label">Pincode</label>
                <input name="pincode" value="{{ old('pincode') }}" class="form-control form-control-sm" placeholder="e.g. 400001"></div>
            <div class="col-6"><label class="form-label">City</label>
                <input name="city" value="{{ old('city') }}" class="form-control form-control-sm" placeholder="e.g. Mumbai"></div>
            <div class="col-6"><label class="form-label">State</label>
                <input name="state" value="{{ old('state') }}" class="form-control form-control-sm" placeholder="e.g. Maharashtra"></div>
            <div class="col-12"><label class="form-label">Address</label>
                <input name="address" value="{{ old('address') }}" class="form-control form-control-sm" placeholder="Street address"></div>
        </div>

        <div class="pc-sec">Company logo</div>
        <label class="tc-logo-drop d-flex align-items-center gap-3" for="logoInput" id="logoDrop">
            <img src="" class="tc-logo-preview d-none" id="logoPreview" alt="">
            <i class="bi bi-building fs-4 text-secondary" id="logoPlaceholderIcon"></i>
            <span class="text-secondary" style="font-size:.75rem" id="logoDropText">Click to upload a logo (PNG/JPG, up to 2MB)</span>
        </label>
        <input type="file" name="logo" accept="image/*" class="d-none" id="logoInput">
        @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
    </div>

    {{-- STEP 2 — Plan + Feature modules --}}
    <div class="tc-panel" data-step="2" style="display:none">
        <div class="pc-sec">Subscription</div>
        <div class="row g-2">
            <div class="col-12"><label class="form-label">Plan *</label>
                <select name="plan_id" id="plan_id" class="form-select form-select-sm" required>
                    @foreach($plans as $p)
                        <option value="{{ $p->id }}" data-features='@json($p->features)' data-max="{{ $p->max_employees }}"
                            @selected(old('plan_id', $plans->first()->id)==$p->id)>{{ $p->name }} ({{ $p->slug }})</option>
                    @endforeach
                </select></div>
            <div class="col-6"><label class="form-label">Employee limit override</label>
                <input name="max_employees" type="number" value="{{ old('max_employees', $prefill['max_employees'] ?? '') }}" class="form-control form-control-sm" placeholder="Blank = plan default"></div>
            <div class="col-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="is_trial" value="1" class="form-check-input" id="is_trial" @checked(old('is_trial'))>
                    <label for="is_trial" class="form-check-label" style="font-size:.78rem">Start as a trial (plan's trial days)</label>
                </div>
            </div>
            <div class="col-6"><label class="form-label">Subscription duration</label>
                <select name="duration_months" class="form-select form-select-sm">
                    <option value="" @selected(old('duration_months')=='')>Open-ended</option>
                    <option value="1" @selected(old('duration_months')=='1')>1 month</option>
                    <option value="3" @selected(old('duration_months')=='3')>3 months</option>
                    <option value="6" @selected(old('duration_months')=='6')>6 months</option>
                    <option value="12" @selected(old('duration_months')=='12')>12 months</option>
                </select></div>
            <div class="col-6"><label class="form-label">…or a custom end date</label>
                <input name="end_date" type="date" value="{{ old('end_date') }}" class="form-control form-control-sm"></div>
        </div>

        <div class="pc-sec">Location tracking (GPS add-on)</div>
        <div class="row g-2">
            <div class="col-6 d-flex align-items-end">
                <div class="form-check">
                    <input type="checkbox" name="field_tracking_enabled" value="1" class="form-check-input" id="field_tracking_enabled" @checked(old('field_tracking_enabled'))>
                    <label for="field_tracking_enabled" class="form-check-label" style="font-size:.78rem">Enable location tracking</label>
                </div>
            </div>
            <div class="col-6"><label class="form-label">Seats purchased</label>
                <input name="field_tracking_seats" type="number" min="0" value="{{ old('field_tracking_seats', 0) }}" class="form-control form-control-sm"></div>
        </div>

        <div class="pc-sec d-flex justify-content-between align-items-center">
            <span>Feature modules <span class="text-secondary fw-normal" style="text-transform:none;font-size:.68rem">(amber = differs from plan default)</span></span>
            <button type="button" class="btn btn-sm btn-outline-secondary py-0" style="font-size:.68rem" id="resetFeatures">Reset to plan</button>
        </div>
        <div class="feat-grid">
            @foreach($featureGroups as $groupName => $groupFeatures)
                <div class="pc-group">{{ $groupName }}</div>
                @foreach($groupFeatures as $key => $meta)
                    <label class="fi feat-item" title="{{ $meta['description'] }}">
                        <input type="checkbox" name="features[{{ $key }}]" value="1" class="feat" data-key="{{ $key }}"
                               data-default="{{ ($meta['default'] ?? false) ? '1' : '0' }}" id="feat_{{ $key }}">
                        <span class="fn">{{ $meta['name'] }}</span>
                    </label>
                @endforeach
            @endforeach
        </div>
    </div>

    <div class="pc-actions">
        <button type="button" class="btn btn-sm btn-outline-secondary" id="tcBack" style="visibility:hidden">← Back</button>
        <div>
            <a href="{{ route('tenants.index') }}" class="btn btn-sm btn-link">Cancel</a>
            <button type="button" class="btn btn-sm btn-primary" id="tcNext">Next →</button>
            <button type="submit" class="btn btn-sm btn-primary d-none" id="tcSubmit">Provision tenant</button>
        </div>
    </div>
</form>

</div>

<script>
(function () {
    const form = document.getElementById('tenantCreateForm');
    if (!form || form.dataset.tcInit) return; // guard against double-init if the drawer partial is fetched twice
    form.dataset.tcInit = '1';

    const panels = Array.from(form.querySelectorAll('.tc-panel'));
    const dots = Array.from(document.querySelectorAll('[data-step-dot]'));
    const backBtn = document.getElementById('tcBack');
    const nextBtn = document.getElementById('tcNext');
    const submitBtn = document.getElementById('tcSubmit');
    let step = 1;

    function showStep(n) {
        step = n;
        panels.forEach(p => { p.style.display = (+p.dataset.step === n) ? '' : 'none'; });
        dots.forEach(d => {
            const dn = +d.dataset.stepDot;
            d.classList.toggle('active', dn === n);
            d.classList.toggle('done', dn < n);
        });
        backBtn.style.visibility = n === 1 ? 'hidden' : 'visible';
        nextBtn.classList.toggle('d-none', n === 2);
        submitBtn.classList.toggle('d-none', n !== 2);
    }
    function stepValid(n) {
        const panel = panels.find(p => +p.dataset.step === n);
        const fields = panel.querySelectorAll('[required]');
        for (const f of fields) {
            if (!f.reportValidity()) return false;
        }
        return true;
    }
    nextBtn.addEventListener('click', function () {
        if (stepValid(step)) showStep(Math.min(2, step + 1));
    });
    backBtn.addEventListener('click', function () {
        showStep(Math.max(1, step - 1));
    });
    showStep(1);

    // Plan -> feature checkbox pre-fill, with amber diff highlight.
    const sel = document.getElementById('plan_id');

    function planFeatures() {
        try { return JSON.parse(sel.selectedOptions[0].dataset.features || '{}'); } catch (e) { return {}; }
    }
    function markDiffs() {
        form.querySelectorAll('.feat').forEach(cb => {
            const wrap = cb.closest('.feat-item');
            wrap.style.background = (cb.checked ? '1' : '0') !== cb.dataset.plan ? '#fef3c7' : '';
        });
    }
    function applyPlan() {
        const f = planFeatures();
        form.querySelectorAll('.feat').forEach(cb => {
            const k = cb.dataset.key;
            // A plan snapshot that doesn't mention a key isn't "off" — it just wasn't
            // captured when the plan was saved. Fall back to that feature's own
            // config default, matching FeatureService::matrixForTenant on the server.
            const planOn = Object.prototype.hasOwnProperty.call(f, k) ? !!f[k] : cb.dataset.default === '1';
            cb.checked = planOn;
            cb.dataset.plan = planOn ? '1' : '0';
        });
        markDiffs();
    }
    sel.addEventListener('change', applyPlan);
    document.getElementById('resetFeatures').addEventListener('click', applyPlan);
    form.querySelectorAll('.feat').forEach(cb => cb.addEventListener('change', markDiffs));
    applyPlan();

    // Logo upload preview
    const logoInput = document.getElementById('logoInput');
    const logoPreview = document.getElementById('logoPreview');
    const logoIcon = document.getElementById('logoPlaceholderIcon');
    const logoText = document.getElementById('logoDropText');
    logoInput.addEventListener('change', function () {
        const file = logoInput.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            logoPreview.src = e.target.result;
            logoPreview.classList.remove('d-none');
            logoIcon.classList.add('d-none');
            logoText.textContent = file.name;
        };
        reader.readAsDataURL(file);
    });
})();
</script>
