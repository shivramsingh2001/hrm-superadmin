@extends('layouts.app')
@section('title', $inquiry->company_name)

@section('content')
<style>
    .eq-wrap { font-family: -apple-system, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
    .eq-head { background: #fff; border: 1px solid #e5e7eb; border-radius: .5rem; padding: 1rem 1.1rem; margin-bottom: .9rem;
        display: flex; align-items: flex-start; justify-content: space-between; gap: 1rem; flex-wrap: wrap; }
    .eq-head .avatar { width: 3rem; height: 3rem; border-radius: .6rem; background: #eff6ff; color: #2563eb;
        display: inline-flex; align-items: center; justify-content: center; font-size: 1.2rem; font-weight: 700; flex: none; }
    .eq-head h4 { font-size: 1.05rem; font-weight: 600; margin: 0; color: #1f2937; }
    .eq-contact-row { display: flex; flex-wrap: wrap; gap: .9rem; margin-top: .3rem; font-size: .78rem; color: #6b7280; }
    .eq-contact-row a { color: #6b7280; text-decoration: none; }
    .eq-contact-row a:hover { color: #2563eb; }
    .eq-contact-row .item { display: inline-flex; align-items: center; gap: .35rem; }
    .eq-wrap .card-header { background: #fff !important; font-size: .8rem; font-weight: 600; color: #1f2937; }
    .eq-wrap label.form-label { font-size: .68rem; font-weight: 600; text-transform: uppercase; letter-spacing: .03em; color: #9ca3af; }
    .status-pill { font-size: .7rem; font-weight: 600; padding: .22rem .6rem; border-radius: .3rem; text-transform: capitalize; }
    .status-new { background: #e5e7eb; color: #374151; }
    .status-contacted { background: #dbeafe; color: #1d4ed8; }
    .status-negotiating { background: #fef9c3; color: #854d0e; }
    .status-payment_sent { background: #ede9fe; color: #6d28d9; }
    .status-paid { background: #dcfce7; color: #166534; }
    .status-provisioned { background: #ccfbf1; color: #0f766e; }
    .status-lost { background: #fee2e2; color: #991b1b; }
</style>

<div class="eq-wrap">
    <div class="eq-head">
        <div class="d-flex align-items-start gap-3">
            <span class="avatar">{{ strtoupper(substr($inquiry->company_name, 0, 1)) }}</span>
            <div>
                <h4>{{ $inquiry->company_name }} <span class="status-pill status-{{ $inquiry->status }}">{{ str_replace('_',' ',$inquiry->status) }}</span></h4>
                <div class="eq-contact-row">
                    <span class="item"><i class="bi bi-person"></i> {{ $inquiry->contact_name }}</span>
                    @if($inquiry->work_email)<a href="mailto:{{ $inquiry->work_email }}" class="item"><i class="bi bi-envelope"></i> {{ $inquiry->work_email }}</a>@endif
                    @if($inquiry->phone)<a href="tel:{{ $inquiry->phone }}" class="item"><i class="bi bi-telephone"></i> {{ $inquiry->phone }}</a>@endif
                    <span class="item"><i class="bi bi-geo-alt"></i> {{ $inquiry->country ?: '—' }}</span>
                </div>
            </div>
        </div>
        @if($inquiry->status === 'paid')
            <a href="{{ route('tenants.create', ['inquiry' => $inquiry->id]) }}" class="btn btn-success align-self-center">Create tenant →</a>
        @elseif($inquiry->status === 'provisioned')
            <span class="status-pill status-provisioned align-self-center">Provisioned</span>
        @endif
    </div>

    <div class="row g-3">
        <div class="col-lg-5">
            <div class="card mb-3"><div class="card-header">Enquiry details</div><div class="card-body">
                <dl class="row mb-0 small">
                    <dt class="col-5">Plan interest</dt><dd class="col-7">{{ $inquiry->plan_interest ?? '—' }}</dd>
                    <dt class="col-5">Employees</dt><dd class="col-7">{{ $inquiry->employee_count ?? '?' }}</dd>
                    <dt class="col-5">Received</dt><dd class="col-7">{{ $inquiry->created_at->format('d M Y H:i') }}</dd>
                    <dt class="col-5">Last contact</dt><dd class="col-7">{{ optional($inquiry->last_contacted_at)->diffForHumans() ?? 'never' }}</dd>
                </dl>
                @if($inquiry->message)<hr><div class="small"><strong>Message:</strong><br>{{ $inquiry->message }}</div>@endif
            </div></div>

            <div class="card mb-3"><div class="card-header">Assign</div><div class="card-body">
                <label class="form-label">Assigned to</label>
                <form method="POST" action="{{ route('enquiries.assign', $inquiry) }}" class="d-flex gap-2">@csrf
                    <select name="assigned_admin_id" class="form-select form-select-sm">
                        <option value="">— unassigned —</option>
                        @foreach($admins as $id => $name)<option value="{{ $id }}" @selected($inquiry->assigned_admin_id==$id)>{{ $name }}</option>@endforeach
                    </select>
                    <button class="btn btn-sm btn-outline-primary">Set</button>
                </form>
            </div></div>

            <div class="card"><div class="card-header">Status</div><div class="card-body">
                @if($nextStatuses)
                    <label class="form-label">Move to</label>
                    <form method="POST" action="{{ route('enquiries.status', $inquiry) }}">@csrf
                        <div class="d-flex gap-2 mb-2">
                            <select name="status" class="form-select form-select-sm">
                                @foreach($nextStatuses as $s)<option value="{{ $s }}">{{ str_replace('_',' ',$s) }}</option>@endforeach
                            </select>
                            <button class="btn btn-sm btn-primary">Move</button>
                        </div>
                        <input name="note" class="form-control form-control-sm" placeholder="note (optional)">
                    </form>
                @else
                    <span class="text-secondary small">No further transitions from <code>{{ $inquiry->status }}</code>.</span>
                @endif
            </div></div>
        </div>

        <div class="col-lg-7">
            <div class="card mb-3"><div class="card-header">Record payment</div><div class="card-body">
                <form method="POST" action="{{ route('enquiries.payment', $inquiry) }}" class="row g-2">@csrf
                    <div class="col-md-3"><label class="form-label">Amount</label>
                        <input name="amount" type="number" step="0.01" class="form-control form-control-sm" placeholder="0.00" required></div>
                    <div class="col-md-2"><label class="form-label">Currency</label>
                        <input name="currency" class="form-control form-control-sm" value="INR" required></div>
                    <div class="col-md-3"><label class="form-label">Mode</label>
                        <select name="payment_mode" class="form-select form-select-sm">
                            <option value="upi">UPI</option><option value="bank_transfer">Bank transfer</option>
                            <option value="cheque">Cheque</option><option value="cash">Cash</option><option value="card">Card</option>
                        </select></div>
                    <div class="col-md-4"><label class="form-label">Reference</label>
                        <input name="reference_number" class="form-control form-control-sm" placeholder="Reference / UTR" required></div>
                    <div class="col-md-4"><label class="form-label">Date</label>
                        <input name="payment_date" type="date" class="form-control form-control-sm" value="{{ date('Y-m-d') }}" required></div>
                    <div class="col-md-6"><label class="form-label">Notes</label>
                        <input name="notes" class="form-control form-control-sm" placeholder="Notes (optional)"></div>
                    <div class="col-md-2 d-flex align-items-end"><button class="btn btn-sm btn-primary w-100">Save</button></div>
                </form>
                <p class="text-secondary small mt-2 mb-0">Saving marks the enquiry <code>paid</code> and unlocks "Create tenant".</p>
            </div></div>

            <div class="card mb-3"><div class="card-header">Payments</div>
                <div class="table-responsive"><table class="table mb-0">
                    <thead><tr><th style="width:2.6rem">Sr. No.</th><th>Date</th><th>Amount</th><th>Mode</th><th>Reference</th></tr></thead>
                    <tbody>
                    @forelse($payments as $p)
                        <tr><td class="text-secondary">{{ $loop->iteration }}</td><td>{{ $p->payment_date->format('d M Y') }}</td>
                            <td>{{ $p->currency }} {{ number_format($p->amount, 2) }}</td>
                            <td>{{ $p->payment_mode }}</td><td><code>{{ $p->reference_number }}</code></td></tr>
                    @empty
                        <tr><td colspan="5" class="text-secondary">No payments.</td></tr>
                    @endforelse
                    </tbody>
                </table></div>
            </div>

            <div class="card"><div class="card-header">Notes</div><div class="card-body">
                <pre class="small mb-2" style="white-space:pre-wrap">{{ $inquiry->notes ?: 'No notes yet.' }}</pre>
                <form method="POST" action="{{ route('enquiries.note', $inquiry) }}" class="d-flex gap-2">@csrf
                    <input name="note" class="form-control form-control-sm" placeholder="Add a note" required>
                    <button class="btn btn-sm btn-outline-primary">Add</button>
                </form>
            </div></div>
        </div>
    </div>
</div>
@endsection
