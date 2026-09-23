<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\PaymentLog;
use App\Models\SubscriptionPlan;
use App\Models\SuperAdmin;
use App\Services\AuditLogger;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

class EnquiryController extends Controller
{
    /** Allowed status transitions (SRS §14.2). */
    private const TRANSITIONS = [
        'new' => ['contacted', 'lost'],
        'contacted' => ['negotiating', 'payment_sent', 'lost'],
        'negotiating' => ['payment_sent', 'lost'],
        'payment_sent' => ['paid', 'negotiating'],
        'paid' => ['provisioned'],           // provisioned happens via the wizard
        'provisioned' => [],
        'lost' => ['new'],                   // re-open, superadmin only (enforced in method)
    ];

    public function index(Request $request)
    {
        $q = Inquiry::query()
            ->when($request->input('search'), function ($w, $v) {
                $w->where(fn ($x) => $x->where('company_name', 'like', "%{$v}%")
                    ->orWhere('contact_name', 'like', "%{$v}%")
                    ->orWhere('work_email', 'like', "%{$v}%")
                    ->orWhere('phone', 'like', "%{$v}%"));
            })
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->input('plan'), fn ($w, $v) => $w->where('plan_interest', $v))
            ->when($request->input('assigned'), fn ($w, $v) => $w->where('assigned_admin_id', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('created_at', '<=', $v))
            ->when($request->input('emp_min'), fn ($w, $v) => $w->where('employee_count', '>=', $v))
            ->when($request->input('emp_max'), fn ($w, $v) => $w->where('employee_count', '<=', $v))
            ->orderByDesc('created_at');

        return view('enquiries.index', [
            'enquiries' => $q->paginate(25)->withQueryString(),
            'admins' => SuperAdmin::orderBy('name')->pluck('name', 'id'),
            'plans' => SubscriptionPlan::orderBy('name')->pluck('name', 'slug'),
            'statuses' => array_keys(self::TRANSITIONS),
        ]);
    }

    public function show(Inquiry $inquiry)
    {
        return view('enquiries.show', [
            'inquiry' => $inquiry,
            'nextStatuses' => self::TRANSITIONS[$inquiry->status] ?? [],
            'payments' => PaymentLog::where('inquiry_id', $inquiry->id)->orderByDesc('payment_date')->get(),
            'admins' => SuperAdmin::orderBy('name')->pluck('name', 'id'),
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }

    public function updateStatus(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate([
            'status' => ['required', 'string'],
            'note' => ['nullable', 'string', 'max:2000'],
        ]);

        $allowed = self::TRANSITIONS[$inquiry->status] ?? [];
        if (! in_array($data['status'], $allowed, true)) {
            throw ValidationException::withMessages(['status' => "Cannot move from '{$inquiry->status}' to '{$data['status']}'."]);
        }
        if ($data['status'] === 'new' && ! $request->user()->isSuperadmin()) {
            abort(403, 'Only a superadmin can re-open a lost enquiry.');
        }

        $old = $inquiry->status;
        $note = $data['note'] ?? null;
        $patch = ['status' => $data['status']];
        if (in_array($data['status'], ['contacted', 'negotiating', 'payment_sent'], true)) {
            $patch['last_contacted_at'] = now();
        }
        if (filled($note)) {
            $patch['notes'] = $this->appendNote($inquiry, $note, $request->user()->name);
        }
        $inquiry->update($patch);

        AuditLogger::record('enquiry.status_changed', 'inquiries', $inquiry->id,
            ['status' => $old], ['status' => $data['status']]);

        return back()->with('success', "Status → {$data['status']}.");
    }

    public function addNote(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:2000']]);
        $inquiry->update(['notes' => $this->appendNote($inquiry, $data['note'], $request->user()->name)]);
        AuditLogger::record('enquiry.note_added', 'inquiries', $inquiry->id);

        return back()->with('success', 'Note added.');
    }

    public function assign(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate(['assigned_admin_id' => ['nullable', 'integer', 'exists:super_admins,id']]);
        $inquiry->update(['assigned_admin_id' => $data['assigned_admin_id'] ?: null]);
        AuditLogger::record('enquiry.assigned', 'inquiries', $inquiry->id, null, ['assigned_admin_id' => $data['assigned_admin_id']]);

        return back()->with('success', 'Assignment updated.');
    }

    public function recordPayment(Request $request, Inquiry $inquiry)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_mode' => ['required', 'in:upi,bank_transfer,cheque,cash,card'],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $log = PaymentLog::create($data + [
            'inquiry_id' => $inquiry->id,
            'collected_by' => $request->user()->id,
        ]);

        if (in_array($inquiry->status, ['new', 'contacted', 'negotiating', 'payment_sent'], true)) {
            $inquiry->update(['status' => 'paid']);
        }

        AuditLogger::record('enquiry.payment_recorded', 'payment_logs', $log->id, null, $log->toArray());
        NotificationService::broadcast('payment_confirmed', 'Payment recorded: ' . $inquiry->company_name,
            "{$data['currency']} {$data['amount']} via {$data['payment_mode']} (ref {$data['reference_number']})",
            ['inquiry_id' => $inquiry->id]);

        return back()->with('success', 'Payment recorded — enquiry marked paid. You can now create the tenant.');
    }

    public function export(Request $request)
    {
        $rows = Inquiry::query()
            ->when($request->input('status'), fn ($w, $v) => $w->where('status', $v))
            ->orderByDesc('created_at')->limit(10000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'company', 'contact', 'email', 'phone', 'country', 'plan_interest', 'employees', 'status', 'assigned_admin_id', 'last_contacted_at', 'created_at']);
            foreach ($rows as $r) {
                fputcsv($out, [$r->id, $r->company_name, $r->contact_name, $r->work_email, $r->phone, $r->country,
                    $r->plan_interest, $r->employee_count, $r->status, $r->assigned_admin_id, $r->last_contacted_at, $r->created_at]);
            }
            fclose($out);
        }, 'enquiries-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function appendNote(Inquiry $inquiry, string $note, string $author): string
    {
        $stamp = '[' . now()->format('d M Y H:i') . ' · ' . $author . '] ' . trim($note);

        return trim(($inquiry->notes ? $inquiry->notes . "\n" : '') . $stamp);
    }
}
