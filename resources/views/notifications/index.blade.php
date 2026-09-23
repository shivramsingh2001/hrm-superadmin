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
@endsection
