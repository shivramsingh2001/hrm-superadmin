<?php

namespace App\Http\Controllers\Api;

use App\Models\Inquiry;
use App\Models\PaymentLog;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class EnquiryController extends ApiController
{
    private const TRANSITIONS = [
        'new' => ['contacted', 'lost'],
        'contacted' => ['negotiating', 'payment_sent', 'lost'],
        'negotiating' => ['payment_sent', 'lost'],
        'payment_sent' => ['paid', 'negotiating'],
        'paid' => ['provisioned'],
        'provisioned' => [],
        'lost' => ['new'],
    ];

    public function index(Request $request)
    {
        $q = Inquiry::query()
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->input('plan'), fn ($w, $v) => $w->where('plan_interest', $v))
            ->orderByDesc('created_at');

        return $this->ok($q->paginate(min(100, (int) $request->input('per_page', 25))));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'work_email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['nullable', 'string', 'max:100'],
            'plan_interest' => ['nullable', 'string', 'max:100'],
            'employee_count' => ['nullable', 'integer', 'min:1', 'max:50000'],
            'message' => ['nullable', 'string', 'max:1000'],
        ]);
        $inquiry = Inquiry::create($data + ['status' => 'new']);
        NotificationService::broadcast('new_enquiry', 'New enquiry: ' . $inquiry->company_name,
            "{$inquiry->contact_name} <{$inquiry->work_email}>", ['inquiry_id' => $inquiry->id]);

        return $this->ok($inquiry, 201);
    }

    public function show(Inquiry $inquiry)
    {
        return $this->ok([
            'inquiry' => $inquiry,
            'next_statuses' => self::TRANSITIONS[$inquiry->status] ?? [],
            'payments' => PaymentLog::where('inquiry_id', $inquiry->id)->orderByDesc('payment_date')->get(),
        ]);
    }

    public function updateStatus(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['status' => ['required', 'string'], 'note' => ['nullable', 'string', 'max:2000']]);
        if (! in_array($data['status'], self::TRANSITIONS[$inquiry->status] ?? [], true)) {
            return $this->fail('invalid_transition', "Cannot move '{$inquiry->status}' -> '{$data['status']}'.");
        }
        if ($data['status'] === 'new' && $request->user()->role !== 'superadmin') {
            return $this->fail('insufficient_role', 'Only a superadmin can re-open a lost enquiry.', 403);
        }
        $old = $inquiry->status;
        $inquiry->update(['status' => $data['status']]
            + (in_array($data['status'], ['contacted', 'negotiating', 'payment_sent'], true) ? ['last_contacted_at' => now()] : []));
        AuditLogger::record('enquiry.status_changed', 'inquiries', $inquiry->id, ['status' => $old], ['status' => $data['status']]);

        return $this->ok($inquiry->fresh());
    }

    public function payment(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_mode' => ['required', 'in:upi,bank_transfer,cheque,cash,card'],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $log = PaymentLog::create($data + ['inquiry_id' => $inquiry->id, 'collected_by' => $request->user()->id]);
        if (in_array($inquiry->status, ['new', 'contacted', 'negotiating', 'payment_sent'], true)) {
            $inquiry->update(['status' => 'paid']);
        }
        AuditLogger::record('enquiry.payment_recorded', 'payment_logs', $log->id, null, ['amount' => $data['amount']]);
        NotificationService::broadcast('payment_confirmed', 'Payment recorded: ' . $inquiry->company_name,
            "{$data['currency']} {$data['amount']}", ['inquiry_id' => $inquiry->id]);

        return $this->ok(['inquiry' => $inquiry->fresh(), 'payment' => $log], 201);
    }
}
