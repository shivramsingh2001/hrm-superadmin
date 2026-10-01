<?php

namespace App\Http\Controllers;

use App\Models\Inquiry;
use App\Models\SubscriptionPlan;
use App\Services\NotificationService;
use App\Services\ProvisioningService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ProvisioningController extends Controller
{
    public function __construct(private ProvisioningService $provisioner)
    {
    }

    public function create(Request $request)
    {
        $inquiry = $request->filled('inquiry') ? Inquiry::find($request->input('inquiry')) : null;

        return view($this->formView($request), [
            'inquiry' => $inquiry,
            'plans' => SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get(),
            'features' => config('features'),
            'defaults' => config('provisioning.default_config'),
            'shiftDefaults' => config('provisioning.default_shift'),
            'prefill' => $inquiry ? [
                'company_name' => $inquiry->company_name,
                'admin_name' => $inquiry->contact_name,
                'admin_email' => $inquiry->work_email,
                'phone' => $inquiry->phone,
                'country' => $inquiry->country,
                'subdomain' => Str::slug($inquiry->company_name),
                'max_employees' => $inquiry->employee_count,
            ] : [],
        ]);
    }

    /** Bare partial (no layout) when opened in the side drawer, full page otherwise. */
    private function formView(Request $request): string
    {
        return $request->boolean('drawer') ? 'tenants._form' : 'tenants.form';
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'inquiry_id' => ['nullable', 'integer', 'exists:inquiries,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:50'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_contact' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'max_employees' => ['nullable', 'integer'],
            'is_trial' => ['nullable', 'boolean'],
            'duration_months' => ['nullable', 'integer', 'in:1,3,6,12'],
            'end_date' => ['nullable', 'date', 'after:today'],
            'field_tracking_enabled' => ['nullable', 'boolean'],
            'field_tracking_seats' => ['nullable', 'integer', 'min:0'],
            'features' => ['array'],
            'working_days' => ['array'],
            'weekoff_days' => ['array'],
            'working_hours_per_day' => ['nullable', 'numeric'],
            'grace_minutes' => ['nullable', 'integer'],
            'overtime_threshold_minutes' => ['nullable', 'integer'],
            'shift' => ['array'],
        ]);

        // Normalise the feature checkbox grid to key => bool for every registered key.
        $features = [];
        foreach (array_keys(config('features')) as $key) {
            $features[$key] = (bool) $request->input("features.{$key}");
        }
        $data['features'] = $features;
        $data['is_trial'] = $request->boolean('is_trial');
        $data['field_tracking_enabled'] = $request->boolean('field_tracking_enabled');
        if ($request->hasFile('logo')) {
            $data['logo'] = file_storage()->upload($request->file('logo'), 'tenant_logo')->path;
        }

        $inquiry = ! empty($data['inquiry_id']) ? Inquiry::find($data['inquiry_id']) : null;

        try {
            $result = $this->provisioner->provision($data, $inquiry);
        } catch (ValidationException $e) {
            throw $e;
        } catch (\Throwable $e) {
            NotificationService::broadcast('provisioning_failed',
                'Provisioning failed: ' . ($data['company_name'] ?? $data['subdomain']),
                \Illuminate\Support\Str::limit($e->getMessage(), 200),
                ['subdomain' => $data['subdomain'] ?? null, 'inquiry_id' => $data['inquiry_id'] ?? null]);

            return back()->withInput()->with('error', 'Provisioning failed: ' . $e->getMessage());
        }

        NotificationService::broadcast('tenant_provisioned', 'Tenant provisioned: ' . $result['tenant']->company_name,
            'Company ' . $result['tenant']->subdomain . ' is live.', ['tenant_id' => $result['tenant']->id]);

        return redirect()->route('tenants.show', $result['tenant'])
            ->with('success', 'Tenant provisioned.')
            ->with('provision_admin_password', $result['admin_password'])
            ->with('provision_admin_email', $data['admin_email']);
    }
}
