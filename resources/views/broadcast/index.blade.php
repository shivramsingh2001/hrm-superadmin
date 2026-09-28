@extends('layouts.app')
@section('title', 'Broadcast Notifications')

@section('content')
@php($canSend = auth()->user()->isSuperadmin() || auth()->user()->isSupport())
<style>
    /* ==================== Stat cards — same STYLE/anatomy as hrm (3)'s
       Broadcast Notifications page (.bcast-stat-card: white card, blue
       gradient top accent bar that appears on hover, icon-wrapper chip,
       hover lift+shadow), colors unchanged (this app's existing blue
       #eff6ff/#2563eb, not hrm (3)'s --primary/--primary-light tokens,
       since this app has no shared/centralized stylesheet — each page
       declares its own copy, matching that existing convention). ==================== */
    .bc-stat-card {
        background: #fff;
        border-radius: .55rem;
        padding: .7rem .8rem;
        height: 100%;
        display: flex;
        align-items: center;
        gap: .65rem;
        border: 1px solid #e5e7eb;
        box-shadow: 0 1px 2px rgba(16,24,40,.04), 0 1px 3px rgba(16,24,40,.06);
        position: relative;
        overflow: hidden;
        transition: all .25s cubic-bezier(.4,0,.2,1);
    }
    .bc-stat-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 3px;
        background: linear-gradient(90deg, #1e3a8a, #2563eb);
        opacity: 0;
        transition: opacity .25s ease;
    }
    .bc-stat-card:hover::before { opacity: 1; }
    .bc-stat-card:hover {
        transform: translateY(-3px);
        box-shadow: 0 8px 20px -8px rgba(0,0,0,.12);
        border-color: #c7d2fe;
    }
    .bc-stat-icon {
        width: 1.9rem; height: 1.9rem; border-radius: .5rem; background: #eff6ff; color: #2563eb;
        display: inline-flex; align-items: center; justify-content: center; font-size: .85rem; flex: none;
        transition: transform .25s ease;
    }
    .bc-stat-card:hover .bc-stat-icon { transform: scale(1.06); }
    .bc-stat-content { flex: 1; min-width: 0; }
    .bc-stat-value { font-size: 1.05rem; font-weight: 700; color: #111827; line-height: 1.1; }
    .bc-stat-label { font-size: .58rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-top: .1rem; }

    .bc-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .bc-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }

    /* ==================== Filter bar ==================== */
    .bc-filter-bar { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .6rem .8rem; margin-bottom: .75rem; }
    .bc-filter-row { display: flex; flex-wrap: wrap; gap: .5rem; align-items: center; }
    .bc-filter-input { border: 1px solid #dee2e6; border-radius: .4rem; padding: .35rem .6rem; font-size: .78rem; height: 32px; }
    .bc-filter-btn { background: #f4f6fb; color: #475569; border: 1px solid #dee2e6; border-radius: .4rem; padding: .35rem .7rem; font-size: .78rem; font-weight: 600; text-decoration: none; }
    .bc-filter-btn:hover { background: #eff6ff; color: #2563eb; }

    table.bc-table { font-size: .8rem; margin: 0; }
    table.bc-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.bc-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    .bc-status { font-size: .65rem; font-weight: 700; text-transform: uppercase; padding: .15rem .5rem; border-radius: 1rem; }
    .bc-status-sending { background: #fef9c3; color: #854d0e; }
    .bc-status-sent { background: #dcfce7; color: #166534; }
    .bc-status-failed { background: #fee2e2; color: #991b1b; }
    .bc-audience { font-size: .68rem; font-weight: 600; background: #eef2ff; color: #4338ca; padding: .1rem .4rem; border-radius: .3rem; }

    /* ==================== Composer drawer (moved from the old
       standalone create.blade.php page) ==================== */
    .bc-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1.1rem 1.3rem; margin-bottom: 1rem; }
    .bc-card h6 { font-size: .82rem; font-weight: 600; color: #1f2937; margin-bottom: .8rem; }
    .bc-label { font-size: .72rem; font-weight: 600; color: #6b7280; margin-bottom: .25rem; display: block; }
    .bc-mode { border: 1px solid #e5e7eb; border-radius: .5rem; padding: .6rem .8rem; margin-bottom: .5rem; cursor: pointer; display: block; }
    .bc-mode:hover { background: #fafbfc; }
    .bc-mode.active { border-color: #2563eb; background: #eff6ff; }
    .bc-mode input { margin-right: .4rem; }
    .bc-mode .title { font-size: .8rem; font-weight: 600; color: #1f2937; }
    .bc-mode .desc { font-size: .7rem; color: #9ca3af; }
    .bc-mode-fields { display: none; margin-top: .6rem; padding-top: .6rem; border-top: 1px dashed #e5e7eb; }
    .bc-mode.active .bc-mode-fields { display: block; }
    .bc-role-check { display: inline-flex; align-items: center; gap: .3rem; padding: .3rem .6rem; border: 1px solid #e5e7eb; border-radius: 1rem; font-size: .74rem; margin: 0 .3rem .3rem 0; cursor: pointer; }
    .bc-count-chip { display: inline-flex; align-items: center; gap: .4rem; background: #eff6ff; color: #2563eb; font-weight: 700; font-size: .82rem; padding: .4rem .9rem; border-radius: 1rem; }
    .bc-submit-bar { display: flex; align-items: center; justify-content: space-between; }
</style>

<div class="bc-head">
    <div>
        <h2>Broadcast Notifications</h2>
        <span class="text-secondary" style="font-size:.72rem;">Targeted messages to superadmin staff, tenant admins/managers, tenant employees, selected tenants, or everyone.</span>
    </div>
    @if ($canSend)
        <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#sendBroadcastDrawer">
            <i class="bi bi-send"></i> Send Broadcast
        </button>
    @endif
</div>

<!-- Stats Cards -->
<div class="row g-2 mb-3">
    <div class="col-6 col-lg-3">
        <div class="bc-stat-card">
            <div class="bc-stat-icon"><i class="bi bi-broadcast"></i></div>
            <div class="bc-stat-content">
                <div class="bc-stat-value">{{ $stats['total'] }}</div>
                <div class="bc-stat-label">Total Broadcasts</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bc-stat-card">
            <div class="bc-stat-icon"><i class="bi bi-check-circle"></i></div>
            <div class="bc-stat-content">
                <div class="bc-stat-value">{{ $stats['sent'] }}</div>
                <div class="bc-stat-label">Sent</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bc-stat-card">
            <div class="bc-stat-icon"><i class="bi bi-hourglass-split"></i></div>
            <div class="bc-stat-content">
                <div class="bc-stat-value">{{ $stats['sending'] }}</div>
                <div class="bc-stat-label">Sending</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-lg-3">
        <div class="bc-stat-card">
            <div class="bc-stat-icon"><i class="bi bi-people"></i></div>
            <div class="bc-stat-content">
                <div class="bc-stat-value">{{ number_format($stats['recipients']) }}</div>
                <div class="bc-stat-label">Recipients Reached</div>
            </div>
        </div>
    </div>
</div>

<!-- Filters -->
<div class="bc-filter-bar">
    <form method="GET" action="{{ route('broadcast.index') }}" id="broadcastFilterForm">
        <div class="bc-filter-row">
            <select name="status" class="bc-filter-input bc-auto-submit">
                <option value="">All Status</option>
                @foreach ($statuses as $s)
                    <option value="{{ $s }}" @selected(($filters['status'] ?? '') === $s)>{{ ucfirst($s) }}</option>
                @endforeach
            </select>
            <select name="audience_type" class="bc-filter-input bc-auto-submit">
                <option value="">All Audiences</option>
                @foreach ($audienceTypes as $a)
                    <option value="{{ $a }}" @selected(($filters['audience_type'] ?? '') === $a)>{{ ucfirst(str_replace('_', ' ', $a)) }}</option>
                @endforeach
            </select>
            <input type="date" name="from_date" class="bc-filter-input" value="{{ $filters['from_date'] ?? '' }}" title="From date">
            <input type="date" name="to_date" class="bc-filter-input" value="{{ $filters['to_date'] ?? '' }}" title="To date">
            <input type="text" name="search" class="bc-filter-input" style="width:180px" placeholder="Search title…" value="{{ $filters['search'] ?? '' }}">
            <button type="submit" class="bc-filter-btn"><i class="bi bi-search"></i> View</button>
            <a href="{{ route('broadcast.index') }}" class="bc-filter-btn"><i class="bi bi-arrow-clockwise"></i> Reset</a>
        </div>
    </form>
</div>

<div class="card"><div class="table-responsive">
    <table class="table bc-table align-middle mb-0">
        <thead>
            <tr>
                <th>Sr.No</th>
                <th>Title</th>
                <th>Audience</th>
                <th>Status</th>
                <th>Recipients</th>
                <th>Read</th>
                <th>Sent</th>
                <th class="text-end">Action</th>
            </tr>
        </thead>
        <tbody>
        @forelse ($broadcasts as $b)
            <tr>
                <td>{{ $broadcasts->firstItem() + $loop->index }}</td>
                <td class="fw-semibold">{{ $b->title }}</td>
                <td><span class="bc-audience">{{ str_replace('_', ' ', $b->audience_type) }}</span></td>
                <td><span class="bc-status bc-status-{{ $b->status }}">{{ $b->status }}</span></td>
                <td>{{ $b->total_recipients }}</td>
                <td>{{ $b->read_count }}</td>
                <td class="text-secondary">{{ optional($b->sent_at)->format('d M Y, H:i') ?? '—' }}</td>
                <td class="text-end"><a href="{{ route('broadcast.show', $b->id) }}" class="btn-icon"><i class="bi bi-eye fs-6"></i></a></td>
            </tr>
        @empty
            <tr><td colspan="8" class="text-secondary">No broadcasts found for this selection.</td></tr>
        @endforelse
        </tbody>
    </table>
</div></div>
<div class="mt-3">{{ $broadcasts->links() }}</div>

@include('broadcast._send-drawer')
@endsection

@section('scripts')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
<script>
(function () {
    // ---------- filter bar ----------
    document.querySelectorAll('#broadcastFilterForm .bc-auto-submit').forEach(function (el) {
        el.addEventListener('change', function () { document.getElementById('broadcastFilterForm').submit(); });
    });

    // ---------- send-broadcast drawer ----------
    var modes = document.querySelectorAll('#sendBroadcastDrawer .bc-mode');
    var form = document.getElementById('broadcastForm');
    var countUrl = "{{ route('broadcast.preview-count') }}";
    var countValue = document.getElementById('bcCountValue');
    var debounceTimer = null;

    modes.forEach(function (modeEl) {
        modeEl.addEventListener('click', function (e) {
            if (e.target.tagName === 'INPUT' && e.target.type !== 'radio') return;
            modes.forEach(function (m) { m.classList.remove('active'); });
            modeEl.classList.add('active');
            var radio = modeEl.querySelector('input[type=radio]');
            radio.checked = true;
            refreshCount();
        });
    });

    function audiencePayload() {
        var checkedMode = form.querySelector('input[name="audience_type"]:checked');
        var params = new URLSearchParams();
        params.set('_token', '{{ csrf_token() }}');
        params.set('audience_type', checkedMode ? checkedMode.value : '');

        form.querySelectorAll('input[name="role[]"]:checked').forEach(function (el) { params.append('role[]', el.value); });

        var activeMode = document.querySelector('#sendBroadcastDrawer .bc-mode.active');
        if (activeMode) {
            var select = activeMode.querySelector('select[name="tenant_ids[]"]');
            if (select) {
                Array.from(select.selectedOptions).forEach(function (opt) { params.append('tenant_ids[]', opt.value); });
            }
        }

        return params;
    }

    function refreshCount() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            var checkedMode = form.querySelector('input[name="audience_type"]:checked');
            if (!checkedMode) { countValue.textContent = '0'; return; }

            fetch(countUrl, {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: audiencePayload(),
            })
            .then(function (r) { return r.json(); })
            .then(function (data) { countValue.textContent = data.recipient_count ?? 0; })
            .catch(function () { countValue.textContent = '?'; });
        }, 350);
    }

    form.addEventListener('change', function (e) {
        if (e.target.classList.contains('bc-audience-input')) refreshCount();
    });

    function initSelect2() {
        if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.select2) {
            return setTimeout(initSelect2, 50);
        }
        window.jQuery('#sendBroadcastDrawer .bc-tenant-select').select2({
            placeholder: 'Search tenants…',
            width: '100%',
            dropdownParent: window.jQuery('#sendBroadcastDrawer'),
        });
        window.jQuery('#sendBroadcastDrawer .bc-tenant-select').on('change', refreshCount);
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

    // Form submits as a plain POST (not AJAX) — on success the browser
    // navigates to the new broadcast's detail page, same as before this
    // was a drawer. If server-side validation fails, store() redirects
    // back here with $errors/old() flashed — auto-reopen the drawer so
    // the admin sees their input and the error, instead of a validation
    // error silently sitting behind a closed drawer.
    @if ($errors->any())
        document.addEventListener('DOMContentLoaded', function () {
            var el = document.getElementById('sendBroadcastDrawer');
            bootstrap.Offcanvas.getOrCreateInstance(el).show();
        });
    @endif
})();
</script>
@endsection
