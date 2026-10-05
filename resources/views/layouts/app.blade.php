<!DOCTYPE html>
<html lang="en" data-bs-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Super Admin') · HRM Platform</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <style>
        html, body { height:100%; }
        body { background:#f4f6fa; margin:0; overflow:hidden; }

        /* App shell: only the main content area scrolls — the sidebar stays put. */
        .sa-shell { height:100vh; }
        .sa-main { flex:1; min-width:0; display:flex; flex-direction:column; height:100vh; overflow-y:auto; }

        /* ---- Sidebar: single light color family (no dark-panel + bright-accent split) ---- */
        .sa-sidebar { width:246px; height:100vh; overflow-y:auto; flex:none; background:#fff; border-right:1px solid #e5e7eb; }
        .sa-sidebar .brand { display:flex; align-items:center; gap:.55rem; padding:.4rem .6rem 1rem; }
        .sa-sidebar .brand .mark { width:2rem; height:2rem; border-radius:.5rem; background:#eff6ff; color:#2563eb;
            display:inline-flex; align-items:center; justify-content:center; font-size:1rem; flex:none; }
        .sa-sidebar .brand .text { line-height:1.15; }
        .sa-sidebar .brand .text strong { display:block; font-size:.86rem; color:#111827; }
        .sa-sidebar .brand .text span { display:block; font-size:.66rem; color:#9ca3af; }
        .sa-sidebar a { color:#4b5563; padding:.5rem .7rem; border-radius:.5rem; display:flex; gap:.65rem; align-items:center;
            font-size:.85rem; font-weight:500; margin:.1rem .3rem; position:relative; text-decoration:none; }
        .sa-sidebar a i { font-size:.95rem; width:1.1rem; text-align:center; color:#9ca3af; flex:none; }
        .sa-sidebar a:hover { background:#f3f4f6; color:#111827; text-decoration:none; }
        .sa-sidebar a:hover i { color:#6b7280; }
        .sa-sidebar a.active { background:#eff6ff; color:#2563eb; font-weight:600; }
        .sa-sidebar a.active i { color:#2563eb; }
        .sa-sidebar a .badge-count { margin-left:auto; font-size:.65rem; }
        .sa-sidebar .sidebar-foot { font-size:.68rem; color:#adb3ba; padding:.6rem .9rem; border-top:1px solid #f1f2f4; margin-top:.5rem; }

        /* ---- Header ---- */
        .sa-header { background:#fff; border-bottom:1px solid #e5e7eb; padding:.65rem 1.5rem; }
        .sa-header .title { font-size:1rem; font-weight:600; color:#111827; }
        .sa-header .subtitle { font-size:.72rem; color:#9ca3af; }
        .sa-header .icon-btn { width:2.1rem; height:2.1rem; border-radius:.5rem; display:inline-flex; align-items:center;
            justify-content:center; color:#4b5563; background:transparent; }
        .sa-header .icon-btn:hover { background:#f3f4f6; color:#111827; }
        .sa-header .avatar { width:2rem; height:2rem; border-radius:50%; background:#eff6ff; color:#2563eb;
            display:inline-flex; align-items:center; justify-content:center; font-size:.74rem; font-weight:700; flex:none; }
        .sa-header .user-name { font-size:.82rem; font-weight:600; color:#1f2937; line-height:1.1; }
        .role-badge { font-size:.63rem; font-weight:600; text-transform:uppercase; letter-spacing:.03em; padding:.15rem .4rem; border-radius:.3rem; }
        .role-badge-superadmin { background:#eff6ff; color:#2563eb; }
        .role-badge-support { background:#f5f3ff; color:#7c3aed; }
        .role-badge-billing { background:#ecfdf5; color:#059669; }
        .logout-btn { display:inline-flex; align-items:center; gap:.3rem; font-size:.72rem; font-weight:600; color:#6b7280;
            background:transparent; border:1px solid #e5e7eb; border-radius:.5rem; padding:.32rem .6rem; line-height:1; }
        .logout-btn:hover { background:#fef2f2; border-color:#fecaca; color:#dc2626; }

        .card { border:1px solid #e5e7eb; }

        /* Notification bell dropdown */
        .sa-bell-badge { position:absolute; top:-3px; right:-4px; min-width:16px; height:16px; padding:0 4px; border-radius:999px;
            background:#2563eb; color:#fff; font-size:.58rem; font-weight:700; line-height:16px; text-align:center; border:2px solid #fff; box-sizing:content-box; }
        .sa-bell-menu { width:340px; border:1px solid #e5e7eb; border-radius:.6rem; box-shadow:0 10px 30px rgba(15,23,42,.12); overflow:hidden; }
        .sa-bell-head { display:flex; align-items:center; justify-content:space-between; padding:.55rem .8rem; border-bottom:1px solid #f1f2f4;
            font-size:.8rem; font-weight:700; color:#111827; }
        .sa-bell-head a { font-size:.7rem; font-weight:600; color:#2563eb; text-decoration:none; }
        .sa-bell-unread { font-weight:400; color:#9ca3af; font-size:.7rem; }
        .sa-bell-list { max-height:380px; overflow-y:auto; }
        .sa-bell-item { display:flex; gap:.6rem; padding:.55rem .8rem; border-bottom:1px solid #f1f2f4; text-decoration:none; color:inherit; cursor:pointer; }
        .sa-bell-item:hover { background:#f8fafc; }
        .sa-bell-item.unread { background:#eff6ff; }
        .sa-bell-item .ic { width:1.8rem; height:1.8rem; flex:none; border-radius:.45rem; display:inline-flex; align-items:center; justify-content:center;
            background:#eff6ff; color:#2563eb; font-size:.85rem; }
        .sa-bell-item.unread .ic { background:#2563eb; color:#fff; }
        .sa-bell-item .t { font-size:.76rem; font-weight:600; color:#111827; line-height:1.3; }
        .sa-bell-item .b { font-size:.7rem; color:#6b7280; overflow:hidden; text-overflow:ellipsis; display:-webkit-box; -webkit-line-clamp:2; -webkit-box-orient:vertical; }
        .sa-bell-item .w { font-size:.64rem; color:#9ca3af; margin-top:.1rem; }
        .sa-bell-empty { padding:1.4rem .8rem; text-align:center; color:#9ca3af; font-size:.76rem; }
        .sa-bell-foot { display:block; text-align:center; padding:.5rem; font-size:.74rem; font-weight:600; color:#2563eb; text-decoration:none; border-top:1px solid #f1f2f4; }
        .sa-bell-foot:hover { background:#f8fafc; }
        .kpi { font-size:1.7rem; font-weight:700; }
        .badge-status-active { background:#dcfce7; color:#166534; }
        .badge-status-suspended { background:#fee2e2; color:#991b1b; }
        .badge-status-trial { background:#fef9c3; color:#854d0e; }
        .src-plan { background:#e0e7ff; color:#3730a3; }
        .src-override { background:#fef3c7; color:#92400e; }
        .src-default { background:#e5e7eb; color:#374151; }
        table { font-size:.88rem; }
    </style>
</head>
<body>
@php($unread = \App\Models\SuperAdminNotification::visibleTo(auth()->id())->whereNull('read_at')->count()
    + \App\Models\BroadcastRecipient::where('recipient_type', 'super_admin')->where('super_admin_id', auth()->id())->whereNull('read_at')->count())
<div class="sa-shell d-flex">
    <nav class="sa-sidebar p-3 d-flex flex-column">
        <div class="brand">
            <span class="mark"><i class="bi bi-shield-lock"></i></span>
            <span class="text"><strong>HRM Super Admin</strong><span>Platform console</span></span>
        </div>
        @php($r = request()->route()?->getName())
        <a href="{{ route('dashboard') }}" class="{{ $r === 'dashboard' ? 'active' : '' }}"><i class="bi bi-speedometer2"></i> Dashboard</a>
        <a href="{{ route('enquiries.index') }}" class="{{ str_starts_with($r ?? '', 'enquiries.') ? 'active' : '' }}"><i class="bi bi-inbox"></i> Enquiries</a>
        <a href="{{ route('tenants.index') }}" class="{{ str_starts_with($r ?? '', 'tenants.') ? 'active' : '' }}"><i class="bi bi-building"></i> Tenants</a>
        <a href="{{ route('plans.index') }}" class="{{ str_starts_with($r ?? '', 'plans.') ? 'active' : '' }}"><i class="bi bi-tags"></i> Subscription Plans</a>
        <a href="{{ route('feature-registry.index') }}" class="{{ str_starts_with($r ?? '', 'feature-registry.') ? 'active' : '' }}"><i class="bi bi-toggles"></i> Feature Registry</a>
        <a href="{{ route('feature-templates.index') }}" class="{{ str_starts_with($r ?? '', 'feature-templates.') ? 'active' : '' }}"><i class="bi bi-collection"></i> Feature Templates</a>
        <a href="{{ route('broadcast.index') }}" class="{{ str_starts_with($r ?? '', 'broadcast.') ? 'active' : '' }}"><i class="bi bi-broadcast"></i> Broadcast Notifications</a>
        <a href="{{ route('impersonation.index') }}" class="{{ str_starts_with($r ?? '', 'impersonation.') ? 'active' : '' }}"><i class="bi bi-person-badge"></i> Impersonation</a>
        <a href="{{ route('health.index') }}" class="{{ str_starts_with($r ?? '', 'health.') ? 'active' : '' }}"><i class="bi bi-heart-pulse"></i> Tenant Health</a>
        <a href="{{ route('audit-logs.index') }}" class="{{ str_starts_with($r ?? '', 'audit-logs.') ? 'active' : '' }}"><i class="bi bi-journal-text"></i> Audit Logs</a>
        <a href="{{ route('api-console') }}" class="{{ $r === 'api-console' ? 'active' : '' }}"><i class="bi bi-terminal"></i> API Console</a>
        @if(auth()->user()->isSuperadmin())
            <a href="{{ route('superadmin-users.index') }}" class="{{ str_starts_with($r ?? '', 'superadmin-users.') ? 'active' : '' }}"><i class="bi bi-people"></i> Users</a>
            <a href="{{ route('superadmin-roles.index') }}" class="{{ str_starts_with($r ?? '', 'superadmin-roles.') ? 'active' : '' }}"><i class="bi bi-key"></i> Roles</a>
        @endif

        <div class="sidebar-foot mt-auto">v0.9 · Phases 1–9</div>
    </nav>

    <div class="sa-main">
        <header class="sa-header d-flex align-items-center justify-content-between">
            <div>
                <div class="title">@yield('title', 'Super Admin')</div>
                @hasSection('subtitle')<div class="subtitle">@yield('subtitle')</div>@endif
            </div>
            <div class="d-flex align-items-center gap-2">
                {{-- Page-specific header controls (e.g. the dashboard's period filter) --}}
                @hasSection('header-actions')
                    <div class="d-flex align-items-center gap-2 pe-2 me-1 border-end">@yield('header-actions')</div>
                @endif
                {{-- Notification bell — dropdown like the HRM app: latest 10, mark read, mark all read, View all --}}
                <div class="dropdown" id="saBell">
                    <button type="button" class="icon-btn position-relative border-0" data-bs-toggle="dropdown" data-bs-auto-close="outside"
                        aria-expanded="false" title="Notifications" aria-label="Notifications" id="saBellToggle">
                        <i class="bi bi-bell fs-6"></i>
                        <span class="sa-bell-badge" id="saBellBadge" @if(! $unread) style="display:none" @endif>{{ $unread > 99 ? '99+' : $unread }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end sa-bell-menu p-0">
                        <div class="sa-bell-head">
                            <span>Notifications <span class="sa-bell-unread" id="saBellUnreadText">{{ $unread ? '(' . $unread . ' unread)' : '' }}</span></span>
                            <a href="#" id="saBellReadAll">Mark all read</a>
                        </div>
                        <div class="sa-bell-list" id="saBellList"><div class="sa-bell-empty">Loading…</div></div>
                        <a href="{{ route('notifications.index') }}" class="sa-bell-foot">View all</a>
                    </div>
                </div>
                <div class="d-flex align-items-center gap-2 ms-1 pe-2 border-end">
                    <span class="avatar">{{ strtoupper(substr(auth()->user()->name, 0, 1)) }}</span>
                    <div>
                        <div class="user-name">{{ auth()->user()->name }}</div>
                        <span class="role-badge role-badge-{{ auth()->user()->role }}">{{ auth()->user()->role }}</span>
                    </div>
                </div>
                <form method="POST" action="{{ route('logout') }}">@csrf
                    <button type="submit" class="logout-btn" title="Logout"><i class="bi bi-box-arrow-right"></i> Logout</button>
                </form>
            </div>
        </header>

        <main class="p-4">
            @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<div class="toast-container position-fixed top-0 end-0 p-3" id="saToasts" style="z-index:1090"></div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
    // saToast('Saved.', 'success' | 'error') — app-wide toast; flash 'success' messages show through it.
    function saToast(message, type) {
        var ok = type !== 'error';
        var el = document.createElement('div');
        el.className = 'toast align-items-center border-0 text-white ' + (ok ? 'bg-success' : 'bg-danger');
        el.setAttribute('role', ok ? 'status' : 'alert');
        el.innerHTML = '<div class="d-flex"><div class="toast-body"><i class="bi ' + (ok ? 'bi-check-circle' : 'bi-exclamation-triangle')
            + ' me-2"></i></div><button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button></div>';
        el.querySelector('.toast-body').appendChild(document.createTextNode(message));
        document.getElementById('saToasts').appendChild(el);
        el.addEventListener('hidden.bs.toast', function () { el.remove(); });
        new bootstrap.Toast(el, { delay: 4000 }).show();
    }
    @if(session('success'))
        saToast(@json(session('success')), 'success');
    @endif

    // Notification bell: load the latest on open, mark read on click, poll the count every minute.
    (function () {
        const csrf = @json(csrf_token());
        const latestUrl = @json(route('notifications.latest'));
        const countUrl = @json(route('notifications.unread-count'));
        const readAllUrl = @json(route('notifications.read-all'));
        const list = document.getElementById('saBellList');
        const badge = document.getElementById('saBellBadge');
        const unreadText = document.getElementById('saBellUnreadText');
        if (!list) return;

        const esc = s => { const d = document.createElement('div'); d.textContent = s || ''; return d.innerHTML; };
        const post = url => fetch(url, { method: 'POST', headers: { 'X-CSRF-TOKEN': csrf, 'Accept': 'application/json' } });

        function setCount(n) {
            badge.style.display = n > 0 ? '' : 'none';
            badge.textContent = n > 99 ? '99+' : n;
            unreadText.textContent = n > 0 ? '(' + n + ' unread)' : '';
        }

        function load() {
            fetch(latestUrl + '?limit=10', { headers: { 'Accept': 'application/json' } })
                .then(r => r.json())
                .then(res => {
                    setCount(res.unread_count || 0);
                    const items = res.data || [];
                    if (!items.length) { list.innerHTML = '<div class="sa-bell-empty">No notifications yet</div>'; return; }
                    list.innerHTML = items.map(n => `
                        <div class="sa-bell-item ${n.is_read ? '' : 'unread'}" data-read="${esc(n.read_url)}" data-url="${esc(n.url || '')}">
                            <span class="ic"><i class="bi ${esc(n.icon)}"></i></span>
                            <span style="min-width:0">
                                <span class="t d-block">${esc(n.title)}</span>
                                ${n.body ? `<span class="b">${esc(n.body)}</span>` : ''}
                                <span class="w d-block">${esc(n.when)} · ${esc(n.type)}</span>
                            </span>
                        </div>`).join('');
                    list.querySelectorAll('.sa-bell-item').forEach(el => el.addEventListener('click', function () {
                        const go = () => { if (this.dataset.url) window.location.href = this.dataset.url; };
                        if (this.classList.contains('unread')) {
                            post(this.dataset.read).then(() => { this.classList.remove('unread'); refreshCount(); go(); });
                        } else { go(); }
                    }));
                })
                .catch(() => { list.innerHTML = '<div class="sa-bell-empty">Could not load notifications</div>'; });
        }

        function refreshCount() {
            fetch(countUrl, { headers: { 'Accept': 'application/json' } }).then(r => r.json()).then(d => setCount(d.unread_count || 0)).catch(() => {});
        }

        document.getElementById('saBellToggle').addEventListener('click', load);
        document.getElementById('saBellReadAll').addEventListener('click', function (e) {
            e.preventDefault();
            post(readAllUrl).then(load);
        });
        setInterval(refreshCount, 60000);
    })();
</script>
@yield('scripts')
</body>
</html>
