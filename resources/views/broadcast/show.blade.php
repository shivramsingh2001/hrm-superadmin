@extends('layouts.app')
@section('title', 'Broadcast Details')

@section('content')
<style>
    .bc-detail-card { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1.1rem 1.3rem; margin-bottom: 1rem; }
    .bc-kpi-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(140px, 1fr)); gap: .7rem; margin-bottom: 1rem; }
    .bc-kpi { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .7rem .9rem; }
    .bc-kpi .n { font-size: 1.3rem; font-weight: 700; color: #1f2937; }
    .bc-kpi .l { font-size: .68rem; color: #9ca3af; text-transform: uppercase; letter-spacing: .03em; }
</style>

<div style="max-width: 760px">
    <div class="mb-3"><a href="{{ route('broadcast.index') }}" class="text-decoration-none" style="font-size:.78rem;"><i class="bi bi-arrow-left"></i> Broadcast Notifications</a></div>

    <div class="bc-kpi-grid">
        <div class="bc-kpi"><div class="n">{{ $stats['total'] }}</div><div class="l">Recipients</div></div>
        <div class="bc-kpi"><div class="n">{{ $stats['delivered'] }}</div><div class="l">Delivered</div></div>
        <div class="bc-kpi"><div class="n">{{ $stats['read'] }}</div><div class="l">Read</div></div>
    </div>

    <div class="bc-detail-card">
        <h5 class="mb-1" style="font-size:1rem;">{{ $broadcast->title }}</h5>
        <p class="text-secondary mb-3" style="font-size:.85rem;">{{ $broadcast->body }}</p>

        <div class="row g-3">
            <div class="col-md-4">
                <div class="text-secondary" style="font-size:.68rem;">Audience</div>
                <div class="fw-semibold text-capitalize" style="font-size:.82rem;">{{ str_replace('_', ' ', $broadcast->audience_type) }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary" style="font-size:.68rem;">Status</div>
                <div class="fw-semibold text-capitalize" style="font-size:.82rem;">{{ $broadcast->status }}</div>
            </div>
            <div class="col-md-4">
                <div class="text-secondary" style="font-size:.68rem;">Sent</div>
                <div class="fw-semibold" style="font-size:.82rem;">{{ optional($broadcast->sent_at)->format('d M Y, H:i') ?? '—' }}</div>
            </div>
            @if ($broadcast->action_url)
                <div class="col-12">
                    <div class="text-secondary" style="font-size:.68rem;">Action link</div>
                    <a href="{{ $broadcast->action_url }}" target="_blank" style="font-size:.82rem;">{{ $broadcast->action_label ?: $broadcast->action_url }}</a>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
