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
                <a href="{{ route('notifications.index') }}" class="icon-btn position-relative" title="Notifications">
                    <i class="bi bi-bell fs-6"></i>
                    @if($unread)<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger" style="font-size:.6rem">{{ $unread }}</span>@endif
                </a>
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
            @if(session('success'))<div class="alert alert-success py-2">{{ session('success') }}</div>@endif
            @if(session('error'))<div class="alert alert-danger py-2">{{ session('error') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger py-2"><ul class="mb-0">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul></div>@endif
            @yield('content')
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
@yield('scripts')
</body>
</html>
