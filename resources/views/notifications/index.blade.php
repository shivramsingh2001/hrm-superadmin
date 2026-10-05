@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<style>
    .ntf-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .ntf-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .ntf-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .ntf-head .sub { font-size: .72rem; color: #9ca3af; }
    .ntf-tabs { display: flex; gap: 6px; margin-bottom: .75rem; }
    .ntf-tabs a { font-size: .74rem; font-weight: 600; padding: .3rem .85rem; border-radius: 999px; text-decoration: none;
        color: #475569; background: #fff; border: 1px solid #e5e7eb; }
    .ntf-tabs a.active { background: #2563eb; color: #fff; border-color: #2563eb; }
    .ntf-tabs a:hover:not(.active) { color: #2563eb; border-color: #93c5fd; }
    .ntf-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; overflow: hidden; }
    .ntf-row { display: flex; gap: .75rem; align-items: flex-start; padding: .7rem .95rem; border-bottom: 1px solid #f1f2f4; }
    .ntf-row:last-child { border-bottom: 0; }
    .ntf-row.unread { background: #eff6ff; }
    .ntf-row.clickable { cursor: pointer; }
    .ntf-row.clickable:hover { background: #f8fafc; }
    .ntf-row.unread.clickable:hover { background: #e3eefe; }
    .ntf-ic { width: 2rem; height: 2rem; flex: none; border-radius: .5rem; display: inline-flex; align-items: center; justify-content: center;
        background: #eff6ff; color: #2563eb; }
    .ntf-row.unread .ntf-ic { background: #2563eb; color: #fff; }
    .ntf-main { flex: 1; min-width: 0; }
    .ntf-title { font-size: .82rem; font-weight: 600; color: #111827; }
    .ntf-body { font-size: .76rem; color: #4b5563; margin-top: .1rem; }
    .ntf-meta { font-size: .68rem; color: #9ca3af; margin-top: .2rem; }
    .ntf-chip { display: inline-block; font-size: .62rem; font-weight: 600; padding: .05rem .4rem; border-radius: 999px; background: #eff6ff; color: #1d4ed8; text-transform: capitalize; }
    .ntf-actions { display: flex; align-items: center; gap: .4rem; flex: none; }
    .ntf-empty { padding: 2.5rem 1rem; text-align: center; color: #9ca3af; font-size: .82rem; }
</style>

<div class="ntf-wrap">
    <div class="ntf-head">
        <div>
            <h2>Notifications</h2>
            <span class="sub">{{ $notifications->total() }} {{ $status ? $status : 'total' }} · {{ $unreadCount }} unread — enquiries, tenants, payments, security and broadcasts</span>
        </div>
        @if($unreadCount > 0)
            <form method="POST" action="{{ route('notifications.read-all') }}" class="m-0">@csrf
                <button class="btn btn-sm btn-primary"><i class="bi bi-check2-all"></i> Mark all read ({{ $unreadCount }})</button>
            </form>
        @endif
    </div>

    <div class="ntf-tabs">
        <a href="{{ route('notifications.index') }}" class="{{ $status === null ? 'active' : '' }}">All</a>
        <a href="{{ route('notifications.index', ['status' => 'unread']) }}" class="{{ $status === 'unread' ? 'active' : '' }}">Unread ({{ $unreadCount }})</a>
        <a href="{{ route('notifications.index', ['status' => 'read']) }}" class="{{ $status === 'read' ? 'active' : '' }}">Read</a>
    </div>

    <div class="ntf-card">
        @forelse($notifications as $n)
            <div class="ntf-row {{ $n['is_read'] ? '' : 'unread' }} {{ $n['url'] ? 'clickable' : '' }}"
                 data-url="{{ $n['url'] }}" data-read="{{ $n['is_read'] ? '' : $n['read_url'] }}">
                <span class="ntf-ic"><i class="bi {{ $n['icon'] }}"></i></span>
                <div class="ntf-main">
                    <div class="ntf-title">{{ $n['title'] }}</div>
                    @if($n['body'])<div class="ntf-body">{{ $n['body'] }}</div>@endif
                    <div class="ntf-meta">
                        <span class="ntf-chip">{{ $n['type'] }}</span>
                        · {{ $n['created_at']?->format('d M Y, h:i A') }} · {{ $n['when'] }}
                    </div>
                </div>
                <div class="ntf-actions">
                    @if($n['url'])<i class="bi bi-chevron-right text-secondary"></i>@endif
                    @unless($n['is_read'])
                        <form method="POST" action="{{ $n['read_url'] }}" class="m-0" onclick="event.stopPropagation()">@csrf
                            <button class="btn btn-sm btn-outline-primary py-0 px-2" style="font-size:.7rem">Mark read</button>
                        </form>
                    @endunless
                </div>
            </div>
        @empty
            <div class="ntf-empty"><i class="bi bi-bell-slash d-block fs-3 mb-2"></i>No notifications here.</div>
        @endforelse
    </div>
    <div class="mt-3">{{ $notifications->links() }}</div>
</div>
@endsection

@section('scripts')
<script>
    // Clicking a row opens what it is about (tenant / enquiry / broadcast link), marking it read first.
    document.querySelectorAll('.ntf-row.clickable').forEach(function (row) {
        row.addEventListener('click', function () {
            const go = () => { window.location.href = row.dataset.url; };
            if (!row.dataset.read) return go();
            fetch(row.dataset.read, { method: 'POST', headers: { 'X-CSRF-TOKEN': @json(csrf_token()), 'Accept': 'application/json' } }).finally(go);
        });
    });
</script>
@endsection
