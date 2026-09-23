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

    table.ft-new-table { font-size: .76rem; }
    table.ft-new-table td { vertical-align: middle; }
</style>

<div class="tc-compact">

<form method="POST" action="{{ route('feature-templates.store') }}" id="templateCreateForm">
    @csrf

    <div class="pc-sec">Template</div>
    <div class="row g-2">
        <div class="col-12"><label class="form-label">Name *</label>
            <input name="name" class="form-control form-control-sm" placeholder="e.g. Enterprise rollout" required></div>
        <div class="col-12"><label class="form-label">Description</label>
            <input name="description" class="form-control form-control-sm" placeholder="Description (optional)"></div>
    </div>

    <div class="pc-sec">Feature map <span class="text-secondary fw-normal" style="text-transform:none;font-size:.68rem">(skip = leave untouched wherever this template is applied)</span></div>
    <div class="table-responsive" style="max-height:360px;overflow-y:auto">
        <table class="table table-sm mb-2 ft-new-table"><tbody>
        @foreach($features as $key => $meta)
            <tr>
                <td>{{ $meta['name'] }}<br><code class="text-secondary" style="font-size:.65rem">{{ $key }}</code></td>
                <td class="text-nowrap text-end">
                    <label class="me-2"><input type="radio" name="map[{{ $key }}]" value="on"> on</label>
                    <label class="me-2"><input type="radio" name="map[{{ $key }}]" value="off"> off</label>
                    <label><input type="radio" name="map[{{ $key }}]" value="" checked> skip</label>
                </td>
            </tr>
        @endforeach
        </tbody></table>
    </div>

    <div class="pc-actions">
        <a href="{{ route('feature-templates.index') }}" class="btn btn-sm btn-link">Cancel</a>
        <button class="btn btn-sm btn-primary">Save template</button>
    </div>
</form>

</div>
