@extends('layouts.app')
@section('title', 'Feature Templates')

@section('content')
@php($isSuper = auth()->user()->isSuperadmin())

<style>
    .ft-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .ft-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .ft-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .ft-head .sub { font-size: .72rem; color: #9ca3af; max-width: 640px; }
    .ft-head .new-hint { font-size: .68rem; color: #adb3ba; margin-top: .15rem; }
    table.ft-table { font-size: .8rem; margin: 0; }
    table.ft-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.ft-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.ft-table tbody tr:hover { background: #fafbfc; }
    table.ft-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    table.ft-table .desc { max-width: 16rem; font-size: .72rem; color: #9ca3af; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    table.ft-table .mod-badge { font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; padding: .1rem .4rem; border-radius: .3rem; }
    table.ft-table .btn-icon { border: 0; background: transparent; color: #6b7280; padding: .2rem .4rem; line-height: 1; }
    table.ft-table .btn-icon:hover { color: #2563eb; }
    table.ft-table .btn-icon.danger:hover { color: #dc2626; }
</style>

<div class="ft-wrap">
    <div class="ft-head">
        <div>
            <h2>Feature Templates</h2>
            <span class="sub">{{ $templates->total() }} template(s) — a named map of feature overrides you can push onto many tenants at once. Each application writes <code style="font-size:.68rem">tenant_feature_overrides</code> rows and busts the tenant's cache; one audit row records every tenant touched.</span>
        </div>
        @if($isSuper)
            <div class="text-end">
                <button type="button" class="btn btn-sm btn-primary"
                        data-drawer-target="template" data-drawer-url="{{ route('feature-templates.create') }}?drawer=1" data-drawer-title="New template">
                    <i class="bi bi-plus-lg"></i> New Template
                </button>
                <div class="new-hint">A reusable feature-override recipe you can apply in bulk.</div>
            </div>
        @endif
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table ft-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Name</th>
                    <th>Description</th>
                    <th>Modules</th>
                    <th>Created</th>
                    <th class="text-end">Action</th>
                </tr>
            </thead>
            <tbody>
            @forelse($templates as $t)
                @php($onCount = count(array_filter($t->map)))
                <tr>
                    <td class="sr">{{ $templates->firstItem() + $loop->index }}</td>
                    <td class="fw-semibold">{{ $t->name }}</td>
                    <td><div class="desc" title="{{ $t->description }}">{{ $t->description ?: '—' }}</div></td>
                    <td>
                        <span class="mod-badge" title="{{ implode(', ', array_keys(array_filter($t->map))) }}">
                            {{ $onCount }} on / {{ count($t->map) - $onCount }} off
                        </span>
                    </td>
                    <td class="text-secondary">{{ optional($t->created_at)->format('d M Y') }}</td>
                    <td class="text-end">
                        @if($isSuper)
                            <button type="button" class="btn-icon" title="Apply to tenants"
                                    data-drawer-target="apply" data-drawer-url="{{ route('feature-templates.apply-form', $t) }}" data-drawer-title="Apply">
                                <i class="bi bi-send fs-6"></i>
                            </button>
                            <form method="POST" action="{{ route('feature-templates.destroy', $t) }}" class="d-inline"
                                  onsubmit="return confirm('Delete template {{ $t->name }}?')">
                                @csrf @method('DELETE')
                                <button type="submit" class="btn-icon danger" title="Delete"><i class="bi bi-trash fs-6"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="6" class="text-secondary">No templates yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $templates->links() }}</div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="templateDrawer" aria-labelledby="templateDrawerLabel" style="width:520px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom d-block" style="padding-top:.9rem;padding-bottom:.9rem">
        <div class="d-flex align-items-start justify-content-between">
            <h6 class="offcanvas-title mb-0" id="templateDrawerLabel" style="font-size:.9rem">Template</h6>
            <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
        </div>
    </div>
    <div class="offcanvas-body px-4 py-3" id="templateDrawerBody"></div>
</div>

<div class="offcanvas offcanvas-end" tabindex="-1" id="applyDrawer" aria-labelledby="applyDrawerLabel" style="width:480px;max-width:96vw">
    <div class="offcanvas-header px-4 border-bottom" style="padding-top:.9rem;padding-bottom:.9rem">
        <h6 class="offcanvas-title mb-0" id="applyDrawerLabel" style="font-size:.9rem">Apply</h6>
        <button type="button" class="btn-close" data-bs-dismiss="offcanvas" aria-label="Close"></button>
    </div>
    <div class="offcanvas-body px-4 py-3" id="applyDrawerBody"></div>
</div>

<style>
    #templateDrawer .offcanvas-body, #applyDrawer .offcanvas-body { padding-bottom: 0; }
    #templateDrawer .pc-actions, #applyDrawer .pc-actions { padding-bottom: .9rem !important; }
</style>
@endsection

@section('scripts')
<script>
    (function () {
        // Both drawers share the same fetch-and-inject mechanism, keyed by data-drawer-target
        // (template|apply) so nothing is ever pre-rendered/left sitting in the page itself.
        var drawers = {
            template: {
                el: document.getElementById('templateDrawer'),
                body: document.getElementById('templateDrawerBody'),
                label: document.getElementById('templateDrawerLabel'),
            },
            apply: {
                el: document.getElementById('applyDrawer'),
                body: document.getElementById('applyDrawerBody'),
                label: document.getElementById('applyDrawerLabel'),
            },
        };

        Object.keys(drawers).forEach(function (key) {
            drawers[key].oc = bootstrap.Offcanvas.getOrCreateInstance(drawers[key].el);
        });

        document.querySelectorAll('[data-drawer-target]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                var d = drawers[btn.dataset.drawerTarget];
                if (!d) return;
                d.label.textContent = btn.dataset.drawerTitle || '';
                d.body.innerHTML = '<div class="text-secondary small p-2">Loading…</div>';
                d.oc.show();
                fetch(btn.dataset.drawerUrl, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                    .then(function (r) { return r.text(); })
                    .then(function (html) {
                        d.body.innerHTML = html;
                        d.body.querySelectorAll('script').forEach(function (oldScript) {
                            var newScript = document.createElement('script');
                            Array.from(oldScript.attributes).forEach(function (attr) { newScript.setAttribute(attr.name, attr.value); });
                            newScript.textContent = oldScript.textContent;
                            oldScript.parentNode.replaceChild(newScript, oldScript);
                        });
                    })
                    .catch(function () { d.body.innerHTML = '<div class="text-danger small p-2">Failed to load the form.</div>'; });
            });
        });
    })();
</script>
@endsection
