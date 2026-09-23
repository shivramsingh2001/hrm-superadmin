<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<style>
    .tc-compact { font-size: .8rem; }
    .tc-compact .pc-sec { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #6b7280; margin: 1rem 0 .4rem; }
    .tc-compact .pc-sec:first-child { margin-top: 0; }
    .tc-compact label.form-label { font-size: .7rem; font-weight: 500; color: #6b7280; margin-bottom: .2rem; }
    .tc-compact .form-control, .tc-compact .form-select {
        font-size: .85rem; padding: .3rem .75rem; height: auto; }
    .tc-compact .pc-actions { position: sticky; bottom: 0; background: #fff; padding: .6rem 0 .2rem;
        margin-top: .7rem; border-top: 1px solid #e5e7eb; display: flex; justify-content: space-between; align-items: center; gap: .6rem; }
    .select2-container { font-size: .82rem; width: 100% !important; }
    .select2-container--default .select2-selection--multiple { border-color: #dee2e6; min-height: calc(1.5em + .6rem + 2px); }
</style>

<div class="tc-compact">

    <div class="pc-sec">Feature map</div>
    <div class="mb-2">
        @foreach($template->map as $k => $on)
            <span class="badge {{ $on ? 'bg-success' : 'bg-secondary' }} mb-1">{{ $k }} {{ $on ? 'on' : 'off' }}</span>
        @endforeach
    </div>

    <form method="POST" action="{{ route('feature-templates.apply', $template) }}" id="templateApplyForm">
        @csrf

        <div class="pc-sec">Apply to plan</div>
        <select name="plan_id" class="form-select form-select-sm">
            <option value="">— none —</option>
            @foreach($plans as $id => $name)<option value="{{ $id }}">all on {{ $name }}</option>@endforeach
        </select>

        <div class="pc-sec">…and/or specific tenants</div>
        <select name="tenant_ids[]" class="form-select form-select-sm" id="applyTenantSelect" multiple style="width:100%">
            @foreach($tenants as $tn)<option value="{{ $tn->id }}">{{ $tn->company_name }}</option>@endforeach
        </select>

        <div class="pc-sec">Reason</div>
        <textarea name="reason" class="form-control form-control-sm" rows="3" placeholder="Reason (optional)"></textarea>

        <div class="pc-actions">
            <span class="text-secondary" style="font-size:.7rem">Writes an override for every mapped feature on each selected tenant.</span>
            <button type="submit" class="btn btn-sm btn-primary">Apply template</button>
        </div>
    </form>

</div>

<script>
(function () {
    var form = document.getElementById('templateApplyForm');
    if (!form || form.dataset.tcInit) return;
    form.dataset.tcInit = '1';

    function initSelect2() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
            return setTimeout(initSelect2, 50);
        }
        var $select = window.jQuery('#applyTenantSelect');
        $select.select2({
            dropdownParent: window.jQuery('#applyDrawerBody'),
            placeholder: 'Search tenants…',
            width: '100%',
        });
    }

    if (!window.jQuery) {
        var s = document.createElement('script');
        s.src = 'https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js';
        s.onload = function () {
            var s2 = document.createElement('script');
            s2.src = 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js';
            s2.onload = initSelect2;
            document.head.appendChild(s2);
        };
        document.head.appendChild(s);
    } else {
        initSelect2();
    }
})();
</script>
