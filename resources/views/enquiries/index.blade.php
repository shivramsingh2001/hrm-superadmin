@extends('layouts.app')
@section('title', 'Enquiries')

@section('content')
@php
    $newCount = $enquiries->total() ? \App\Models\Inquiry::where('status', 'new')->count() : 0;
@endphp

<style>
    .enq-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .enq-head { display: flex; align-items: center; justify-content: space-between;
        padding: .55rem .9rem; background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; margin-bottom: .75rem; }
    .enq-head h2 { font-size: .92rem; font-weight: 600; margin: 0; color: #1f2937; }
    .enq-head .sub { font-size: .72rem; color: #9ca3af; }
    .enq-filters { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: .7rem .9rem; margin-bottom: .75rem; }
    .enq-filters label { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em; color: #9ca3af; margin-bottom: .2rem; display: block; }
    .enq-filters .form-control, .enq-filters .form-select { font-size: .82rem; }
    table.enq-table { font-size: .8rem; margin: 0; }
    table.enq-table thead th { font-size: .66rem; font-weight: 600; text-transform: uppercase; letter-spacing: .04em;
        color: #9ca3af; background: #fafbfc; border-bottom: 1px solid #e5e7eb; padding: .55rem .7rem; white-space: nowrap; }
    table.enq-table tbody td { padding: .55rem .7rem; vertical-align: middle; color: #374151; }
    table.enq-table tbody tr:hover { background: #fafbfc; }
    table.enq-table .plan-badge { font-size: .7rem; font-weight: 600; background: #eef2ff; color: #4338ca; padding: .1rem .4rem; border-radius: .3rem; }
    table.enq-table .sr { color: #adb3ba; width: 2.6rem; white-space: nowrap; }
    .status-pill { font-size: .68rem; font-weight: 600; padding: .18rem .5rem; border-radius: .3rem; text-transform: capitalize; }
    .status-new { background: #e5e7eb; color: #374151; }
    .status-contacted { background: #dbeafe; color: #1d4ed8; }
    .status-negotiating { background: #fef9c3; color: #854d0e; }
    .status-payment_sent { background: #ede9fe; color: #6d28d9; }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-provisioned { background: #ccfbf1; color: #0f766e; }
    .status-lost { background: #fee2e2; color: #991b1b; }
</style>

<div class="enq-wrap">
    <div class="enq-head">
        <div>
            <h2>Enquiries</h2>
            <span class="sub">{{ $enquiries->total() }} total · {{ $newCount }} new</span>
        </div>
        <div class="d-flex gap-2">
            <a href="{{ route('enquiries.export', request()->query()) }}" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-download"></i> Export CSV
            </a>
            <button type="button" class="btn btn-sm btn-primary" data-bs-toggle="offcanvas" data-bs-target="#enqCreateDrawer" aria-controls="enqCreateDrawer">
                <i class="bi bi-plus-lg"></i> New enquiry
            </button>
        </div>
    </div>

    <div class="enq-filters">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-3">
                <label>Search</label>
                <input name="search" value="{{ request('search') }}" class="form-control form-control-sm" placeholder="Company, contact, email or phone">
            </div>
            <div class="col-md-2">
                <label>Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">Any status</option>
                    @foreach($statuses as $s)<option value="{{ $s }}" @selected(request('status')===$s)>{{ ucfirst(str_replace('_',' ',$s)) }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Plan interest</label>
                <select name="plan" class="form-select form-select-sm">
                    <option value="">Any plan</option>
                    @foreach($plans as $slug => $name)<option value="{{ $slug }}" @selected(request('plan')===$slug)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label>Assignee</label>
                <select name="assigned" class="form-select form-select-sm">
                    <option value="">Any assignee</option>
                    @foreach($admins as $id => $name)<option value="{{ $id }}" @selected(request('assigned')==$id)>{{ $name }}</option>@endforeach
                </select>
            </div>
            <div class="col-md-1">
                <label>From</label>
                <input type="date" name="from" value="{{ request('from') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1">
                <label>To</label>
                <input type="date" name="to" value="{{ request('to') }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-1 d-flex gap-2">
                <button class="btn btn-sm btn-primary" title="Filter"><i class="bi bi-funnel"></i></button>
                <a href="{{ route('enquiries.index') }}" class="btn btn-sm btn-outline-secondary" title="Reset"><i class="bi bi-x-lg"></i></a>
            </div>
        </form>
    </div>

    <div class="card"><div class="table-responsive">
        <table class="table enq-table align-middle mb-0">
            <thead>
                <tr>
                    <th class="sr">Sr. No.</th>
                    <th>Company</th>
                    <th>Contact</th>
                    <th>Email</th>
                    <th>Mobile</th>
                    <th>Plan interest</th>
                    <th>Emp</th>
                    <th>Status</th>
                    <th>Last contact</th>
                    <th>Created</th>
                </tr>
            </thead>
            <tbody>
            @forelse($enquiries as $e)
                @php($hrs = $e->last_contacted_at ? $e->last_contacted_at->diffInHours(now()) : $e->created_at->diffInHours(now()))
                <tr>
                    <td class="sr">{{ $enquiries->firstItem() + $loop->index }}</td>
                    <td><a href="{{ route('enquiries.show', $e) }}" class="fw-semibold text-decoration-none">{{ $e->company_name }}</a></td>
                    <td>{{ $e->contact_name }}</td>
                    <td class="text-secondary">
                        @if($e->work_email)<a href="mailto:{{ $e->work_email }}" class="text-decoration-none text-secondary">{{ $e->work_email }}</a>@else —@endif
                    </td>
                    <td class="text-secondary">
                        @if($e->phone)<a href="tel:{{ $e->phone }}" class="text-decoration-none text-secondary">{{ $e->phone }}</a>@else —@endif
                    </td>
                    <td>@if($e->plan_interest)<span class="plan-badge">{{ $e->plan_interest }}</span>@else —@endif</td>
                    <td>{{ $e->employee_count ?? '?' }}</td>
                    <td><span class="status-pill status-{{ $e->status }}">{{ str_replace('_',' ',$e->status) }}</span></td>
                    <td>
                        @if(in_array($e->status, ['provisioned','lost','paid']))
                            <span class="text-secondary">—</span>
                        @elseif($hrs >= 48)
                            <span class="badge bg-danger">{{ round($hrs) }}h</span>
                        @elseif($hrs >= 24)
                            <span class="badge bg-warning text-dark">{{ round($hrs) }}h</span>
                        @else
                            <span class="text-secondary">{{ round($hrs) }}h</span>
                        @endif
                    </td>
                    <td class="text-secondary">{{ $e->created_at->format('d M Y') }}</td>
                </tr>
            @empty
                <tr><td colspan="10" class="text-secondary">No enquiries match.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div></div>
    <div class="mt-3">{{ $enquiries->links() }}</div>
</div>

@include('enquiries._create-drawer')
@endsection
