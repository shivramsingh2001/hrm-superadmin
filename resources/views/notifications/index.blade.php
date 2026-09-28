@extends('layouts.app')
@section('title', 'Notifications')

@section('content')
<div class="card"><div class="list-group list-group-flush">
    @forelse($notifications as $n)
        <div class="list-group-item {{ $n->read_at ? '' : 'bg-light' }}">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>{{ $n->title }}</strong> <span class="badge bg-secondary">{{ $n->type }}</span>
                    <div class="small text-secondary">{{ $n->body }}</div>
                    <div class="small text-secondary">{{ $n->created_at?->diffForHumans() }}</div>
                </div>
                @unless($n->read_at)
                    <form method="POST" action="{{ route('notifications.read', $n) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary">Mark read</button></form>
                @endunless
            </div>
        </div>
    @empty
        <div class="list-group-item text-secondary">No notifications.</div>
    @endforelse
</div></div>
<div class="mt-3">{{ $notifications->links() }}</div>

<h2 class="mt-4" style="font-size:.85rem;font-weight:600;color:#1f2937;">Broadcasts</h2>
<div class="card mt-2"><div class="list-group list-group-flush">
    @forelse($broadcastNotifications as $r)
        <div class="list-group-item {{ $r->read_at ? '' : 'bg-light' }}">
            <div class="d-flex justify-content-between">
                <div>
                    <strong>{{ $r->broadcast->title ?? 'Notification' }}</strong> <span class="badge bg-primary">broadcast</span>
                    <div class="small text-secondary">{{ $r->broadcast->body ?? '' }}</div>
                    @if (! empty($r->broadcast?->action_url))
                        <a href="{{ $r->broadcast->action_url }}" target="_blank" class="small">{{ $r->broadcast->action_label ?: 'View' }}</a>
                    @endif
                    <div class="small text-secondary">{{ $r->created_at?->diffForHumans() }}</div>
                </div>
                @unless($r->read_at)
                    <form method="POST" action="{{ route('notifications.broadcast-read', $r) }}">@csrf
                        <button class="btn btn-sm btn-outline-secondary">Mark read</button></form>
                @endunless
            </div>
        </div>
    @empty
        <div class="list-group-item text-secondary">No broadcasts.</div>
    @endforelse
</div></div>
<div class="mt-3">{{ $broadcastNotifications->links() }}</div>
@endsection
