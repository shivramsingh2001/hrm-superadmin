<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\SubscriptionPlan;
use App\Services\NotificationService;
use Illuminate\Http\Request;

/**
 * Public, unauthenticated lead-capture form. MVP spam protection = honeypot +
 * time-trap; reCAPTCHA v3 is a later add-on.
 */
class ContactController extends Controller
{
    public function show()
    {
        return view('contact', [
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->pluck('name', 'slug'),
        ]);
    }

    public function store(Request $request)
    {
        // Honeypot: bots fill hidden "website"; humans leave it blank.
        if (filled($request->input('website'))) {
            return redirect()->route('contact')->with('success', 'Thanks — we will be in touch.');
        }

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

        NotificationService::broadcast(
            'new_enquiry',
            'New enquiry: ' . $inquiry->company_name,
            "{$inquiry->contact_name} <{$inquiry->work_email}> · plan interest: " . ($inquiry->plan_interest ?: '—') .
                ' · ' . ($inquiry->employee_count ?: '?') . ' employees',
            ['inquiry_id' => $inquiry->id],
        );

        return redirect()->route('contact')->with('success', 'Thanks — our team will reach out shortly.');
    }
}
