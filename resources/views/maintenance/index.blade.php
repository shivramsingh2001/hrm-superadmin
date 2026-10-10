@extends('layouts.app')
@section('title', 'Maintenance Mode')

@section('content')
@php
    $isSuper = auth()->user()->isSuperadmin();
    $state = $mode->state();
    $badge = ['off' => ['secondary', 'Off'], 'scheduled' => ['warning text-dark', 'Scheduled'], 'live' => ['danger', 'Live'], 'ended' => ['secondary', 'Ended']][$state];
    $fmt = fn ($c) => $c ? $c->format('Y-m-d\TH:i') : '';
    $usersText = collect($mode->allowed_users ?? [])->map(fn ($id) => $users->get($id)?->email ?: $id)->implode("\n");
@endphp

<style>
    .mm-wrap { max-width: 860px; font-size: .82rem; }
    .mm-head { display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .mm-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .mm-head .sub { font-size: .72rem; color: #9ca3af; }
    .mm-wrap .sec { font-size: .7rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin: 1rem 0 .4rem; }
    .mm-wrap .sec:first-child { margin-top: 0; }
    .mm-wrap label.form-label { font-size: .7rem; font-weight: 500; color: #6b7280; margin-bottom: .15rem; }
    .mm-wrap .form-control { font-size: .8rem; }
    .mm-wrap .hint { font-size: .7rem; color: #9ca3af; margin-top: .2rem; }
    .mm-live { border: 1px solid #fecaca; background: #fef2f2; color: #991b1b; border-radius: .5rem; padding: .6rem .9rem;
        display: flex; align-items: center; justify-content: space-between; gap: .75rem; flex-wrap: wrap; margin-bottom: .75rem; }
    .mm-preview { border: 1px dashed #d1d5db; border-radius: .5rem; padding: 1rem; text-align: center; background: #f9fafb; }
    .mm-preview strong { display: block; font-size: .95rem; color: #1f2937; margin-bottom: .25rem; }
    .mm-preview span { color: #6b7280; white-space: pre-line; }
</style>

<div class="mm-wrap">
    <div class="mm-head">
        <div>
            <h2>Maintenance Mode <span class="badge bg-{{ $badge[0] }} ms-1">{{ $badge[1] }}</span></h2>
            <span class="sub">Puts the whole HRM (web panel + mobile app API) behind a maintenance screen. Mobile app reads <code>GET /api/maintenance</code>.</span>
        </div>
        @if($mode->updated_at)
            <span class="sub">Last changed {{ \Illuminate\Support\Carbon::parse($mode->updated_at, $tz)->format('d M Y, h:i A') }}@if($mode->enabledBy) by {{ $mode->enabledBy->name }}@endif</span>
        @endif
    </div>

    @if($state === 'live')
        <div class="mm-live">
            <div><i class="bi bi-exclamation-octagon"></i> <strong>The HRM is in maintenance right now.</strong>
                @if($mode->endAt()) Ends automatically at {{ $mode->endAt()->format('d M Y, h:i A') }}.@else No end time — stays on until you switch it off.@endif</div>
            @if($isSuper)
                <form method="POST" action="{{ route('maintenance.disable') }}">@csrf
                    <button class="btn btn-sm btn-danger"><i class="bi bi-power"></i> Turn off now</button></form>
            @endif
        </div>
    @endif

    <form method="POST" action="{{ route('maintenance.update') }}" class="card"><div class="card-body">
        @csrf
        <fieldset @disabled(! $isSuper)>
        <div class="sec">Status</div>
        <div class="form-check form-switch mb-1">
            <input type="hidden" name="is_enabled" value="0">
            <input class="form-check-input" type="checkbox" role="switch" id="is_enabled" name="is_enabled" value="1" @checked(old('is_enabled', $mode->is_enabled))>
            <label class="form-check-label fw-semibold" for="is_enabled">Enable maintenance mode</label>
        </div>
        <div class="hint">With no start/end time it takes effect immediately and stays on until switched off.</div>

        <div class="sec">Message shown to users</div>
        <div class="row g-2">
            <div class="col-12">
                <label class="form-label" for="title">Title</label>
                <input class="form-control" id="title" name="title" maxlength="255" required value="{{ old('title', $mode->title) }}">
            </div>
            <div class="col-12">
                <label class="form-label" for="message">Message</label>
                <textarea class="form-control" id="message" name="message" rows="3" required>{{ old('message', $mode->message) }}</textarea>
            </div>
        </div>

        <div class="sec">Schedule (optional · {{ $tz }})</div>
        <div class="row g-2">
            <div class="col-sm-6">
                <label class="form-label" for="start_time">Start</label>
                <input type="datetime-local" class="form-control" id="start_time" name="start_time" value="{{ old('start_time', $fmt($mode->startAt())) }}">
                <div class="hint">Empty = from now.</div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="end_time">End</label>
                <input type="datetime-local" class="form-control" id="end_time" name="end_time" value="{{ old('end_time', $fmt($mode->endAt())) }}">
                <div class="hint">Empty = until switched off. After this time the HRM opens again by itself.</div>
            </div>
        </div>

        <div class="sec">Who can still get in</div>
        <div class="row g-2">
            <div class="col-sm-6">
                <label class="form-label" for="allowed_ips">Allowed IPs</label>
                <textarea class="form-control font-monospace" id="allowed_ips" name="allowed_ips" rows="4" placeholder="203.0.113.10&#10;10.0.0.0/24">{{ old('allowed_ips', implode("\n", $mode->allowed_ips ?? [])) }}</textarea>
                <div class="hint">One IP or CIDR range per line. Your IP: <code>{{ $myIp }}</code></div>
            </div>
            <div class="col-sm-6">
                <label class="form-label" for="allowed_users">Allowed HRM users</label>
                <textarea class="form-control font-monospace" id="allowed_users" name="allowed_users" rows="4" placeholder="hr@company.com&#10;1234">{{ old('allowed_users', $usersText) }}</textarea>
                <div class="hint">One email or user ID per line. They can log in and use the HRM normally.</div>
            </div>
        </div>

        <div class="sec">Preview</div>
        <div class="mm-preview"><strong id="pv-title">{{ $mode->title }}</strong><span id="pv-msg">{{ $mode->message }}</span></div>
        </fieldset>

        @if($isSuper)
            <div class="d-flex justify-content-end gap-2 mt-3">
                <button class="btn btn-primary btn-sm"><i class="bi bi-check2"></i> Save</button>
            </div>
        @else
            <div class="hint mt-3">Only superadmins can change maintenance mode.</div>
        @endif
    </div></form>
</div>

<script>
    ['title', 'message'].forEach(function (f) {
        var el = document.getElementById(f), pv = document.getElementById(f === 'title' ? 'pv-title' : 'pv-msg');
        el && el.addEventListener('input', function () { pv.textContent = el.value; });
    });
</script>
@endsection
