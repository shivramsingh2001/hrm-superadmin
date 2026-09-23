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

    .tc-compact .pc-actions { position: sticky; bottom: 0; background: #fff; padding: .6rem 0 .2rem;
        margin-top: .7rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; }

    .tc-logo-drop { border: 1px dashed #d1d5db; border-radius: .5rem; padding: .6rem; cursor: pointer; background: #fafbfc;
        display: flex; align-items: center; gap: .6rem; }
    .tc-logo-drop:hover { border-color: #93c5fd; background: #f0f7ff; }
    .tc-logo-preview, .tc-logo-fallback { width: 48px; height: 48px; border-radius: .5rem; flex: none; }
    .tc-logo-preview { object-fit: cover; border: 1px solid #e5e7eb; }
    .tc-logo-fallback { background: #eef2ff; color: #4338ca; font-size: 1.1rem; font-weight: 700;
        display: inline-flex; align-items: center; justify-content: center; }
</style>

<div class="tc-compact">

<form method="POST" action="{{ route('tenants.update', $tenant) }}" enctype="multipart/form-data" id="tenantEditForm">
    @csrf @method('PUT')

    <div class="pc-sec">Company logo</div>
    <label class="tc-logo-drop" for="logoInput">
        @if($tenant->logo)
            <img src="{{ asset('storage/' . $tenant->logo) }}" class="tc-logo-preview" id="logoPreview" alt="Current logo">
            <span class="tc-logo-fallback d-none" id="logoFallback">{{ strtoupper(substr($tenant->company_name, 0, 1)) }}</span>
        @else
            <img src="" class="tc-logo-preview d-none" id="logoPreview" alt="Logo preview">
            <span class="tc-logo-fallback" id="logoFallback">{{ strtoupper(substr($tenant->company_name, 0, 1)) }}</span>
        @endif
        <span class="text-secondary" id="logoDropText" style="font-size:.75rem">Click to replace the logo (PNG/JPG, up to 2MB)</span>
    </label>
    <input type="file" name="logo" accept="image/*" class="d-none" id="logoInput">
    @error('logo')<div class="text-danger small mt-1">{{ $message }}</div>@enderror

    <div class="pc-sec">Company details</div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Company name *</label>
            <input name="company_name" value="{{ old('company_name', $tenant->company_name) }}" class="form-control form-control-sm" placeholder="e.g. Acme Technologies Pvt Ltd" required></div>
        <div class="col-6"><label class="form-label">Display name</label>
            <input name="display_name" value="{{ old('display_name', $tenant->display_name) }}" class="form-control form-control-sm" placeholder="Short name shown in the app"></div>
        <div class="col-6"><label class="form-label">Legal name</label>
            <input name="legal_name" value="{{ old('legal_name', $company->legal_name ?? $tenant->company_name) }}" class="form-control form-control-sm" placeholder="Registered legal entity name"></div>
        <div class="col-6"><label class="form-label">Email *</label>
            <input type="email" name="email" value="{{ old('email', $tenant->email) }}" class="form-control form-control-sm" placeholder="company@example.com" required></div>
        <div class="col-6"><label class="form-label">Contact no.</label>
            <input name="phone" value="{{ old('phone', $tenant->phone) }}" class="form-control form-control-sm" placeholder="10-digit phone number"></div>
        <div class="col-6"><label class="form-label">Pincode</label>
            <input name="pincode" value="{{ old('pincode', $tenant->pincode) }}" class="form-control form-control-sm" placeholder="e.g. 400001"></div>
    </div>

    <div class="pc-sec">Locale</div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">Country *</label>
            <input name="country" value="{{ old('country', $tenant->country) }}" class="form-control form-control-sm" placeholder="e.g. India" required></div>
        <div class="col-6"><label class="form-label">Timezone *</label>
            <input name="timezone" value="{{ old('timezone', $tenant->timezone) }}" class="form-control form-control-sm" placeholder="e.g. Asia/Kolkata" required></div>
        <div class="col-6"><label class="form-label">Currency *</label>
            <input name="currency" value="{{ old('currency', $tenant->currency) }}" class="form-control form-control-sm" placeholder="INR" required></div>
        <div class="col-6"><label class="form-label">Symbol</label>
            <input name="currency_symbol" value="{{ old('currency_symbol', $tenant->currency_symbol) }}" class="form-control form-control-sm" placeholder="₹"></div>
    </div>

    <div class="pc-sec">Registration &amp; address</div>
    <div class="row g-2">
        <div class="col-6"><label class="form-label">GST number</label>
            <input name="gst_number" value="{{ old('gst_number', $tenant->gst_number) }}" class="form-control form-control-sm" placeholder="15-character GSTIN"></div>
        <div class="col-6"><label class="form-label">PAN number</label>
            <input name="pan_number" value="{{ old('pan_number', $tenant->pan_number) }}" class="form-control form-control-sm" placeholder="10-character PAN"></div>
        <div class="col-6"><label class="form-label">City</label>
            <input name="city" value="{{ old('city', $tenant->city) }}" class="form-control form-control-sm" placeholder="e.g. Mumbai"></div>
        <div class="col-6"><label class="form-label">State</label>
            <input name="state" value="{{ old('state', $tenant->state) }}" class="form-control form-control-sm" placeholder="e.g. Maharashtra"></div>
        <div class="col-12"><label class="form-label">Address</label>
            <input name="address" value="{{ old('address', $tenant->address) }}" class="form-control form-control-sm" placeholder="Street address"></div>
    </div>

    <div class="pc-sec">Read-only</div>
    <dl class="row mb-0" style="font-size:.78rem">
        <dt class="col-5 text-secondary fw-normal">Subdomain</dt><dd class="col-7"><code>{{ $tenant->subdomain }}</code></dd>
        <dt class="col-5 text-secondary fw-normal">Status</dt><dd class="col-7"><span class="badge badge-status-{{ $tenant->status }}">{{ $tenant->status }}</span></dd>
        <dt class="col-5 text-secondary fw-normal">Created</dt><dd class="col-7">{{ optional($tenant->created_at)->format('d M Y') }}</dd>
    </dl>
    <p class="text-secondary mb-0" style="font-size:.7rem">Plan, employee limits and feature modules are managed from the tenant's Lifecycle and Features tabs.</p>

    <div class="pc-actions">
        <a href="{{ route('tenants.show', $tenant) }}" class="btn btn-sm btn-link">Cancel</a>
        <button class="btn btn-sm btn-primary">Save changes</button>
    </div>
</form>

</div>

<script>
(function () {
    const form = document.getElementById('tenantEditForm');
    if (!form || form.dataset.tcInit) return;
    form.dataset.tcInit = '1';

    const input = document.getElementById('logoInput');
    const preview = document.getElementById('logoPreview');
    const fallback = document.getElementById('logoFallback');
    const text = document.getElementById('logoDropText');
    input.addEventListener('change', function () {
        const file = input.files[0];
        if (!file) return;
        const reader = new FileReader();
        reader.onload = function (e) {
            preview.src = e.target.result;
            preview.classList.remove('d-none');
            fallback.classList.add('d-none');
            text.textContent = file.name;
        };
        reader.readAsDataURL(file);
    });
})();
</script>
