<?php

namespace App\Http\Controllers\Api;

use App\Models\ImpersonationSession;
use App\Models\Inquiry;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FeatureService;
use App\Services\ProvisioningService;
use App\Services\TenantLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class TenantController extends ApiController
{
    public function __construct(
        private FeatureService $features,
        private TenantLifecycleService $lifecycle,
        private ProvisioningService $provisioner,
    ) {
    }

    public function index(Request $request)
    {
        $q = Tenant::query()
            ->when($request->input('search'), fn ($w, $s) => $w->where(fn ($x) => $x
                ->where('company_name', 'like', "%{$s}%")->orWhere('subdomain', 'like', "%{$s}%")->orWhere('email', 'like', "%{$s}%")))
            ->when($request->input('status'), fn ($w, $s) => $w->where('status', $s))
            ->orderBy('company_name');

        return $this->ok($q->paginate(min(100, (int) $request->input('per_page', 25))));
    }

    public function show(Tenant $tenant)
    {
        return $this->ok([
            'tenant' => $tenant,
            'company' => $tenant->company,
            'subscription' => $tenant->activeSubscription(),
            'features' => $this->features->matrixForTenant($tenant->id),
            'user_count' => User::where('tenant_id', $tenant->id)->count(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'inquiry_id' => ['nullable', 'integer', 'exists:inquiries,id'],
            'company_name' => ['required', 'string', 'max:255'],
            'subdomain' => ['required', 'string', 'max:50'],
            'admin_name' => ['required', 'string', 'max:255'],
            'admin_email' => ['required', 'email', 'max:255'],
            'admin_contact' => ['nullable', 'string', 'max:20'],
            'country' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'is_trial' => ['nullable', 'boolean'],
            'max_employees' => ['nullable', 'integer'],
            'features' => ['array'],
            'working_days' => ['array'],
            'weekoff_days' => ['array'],
            'shift' => ['array'],
        ]);

        $features = [];
        foreach (array_keys(config('features')) as $k) {
            if ($request->has("features.$k")) {
                $features[$k] = (bool) $request->input("features.$k");
            }
        }
        $data['features'] = $features;
        $data['is_trial'] = $request->boolean('is_trial');
        $inquiry = ! empty($data['inquiry_id']) ? Inquiry::find($data['inquiry_id']) : null;

        try {
            $r = $this->provisioner->provision($data, $inquiry);
        } catch (ValidationException $e) {
            return $this->fail('validation', collect($e->errors())->flatten()->first(), 422, ['errors' => $e->errors()]);
        } catch (\Throwable $e) {
            return $this->fail('provisioning_failed', Str::limit($e->getMessage(), 300), 500);
        }

        return $this->ok([
            'tenant' => $r['tenant'],
            'admin_email' => $data['admin_email'],
            'admin_password' => $r['admin_password'], // shown once
        ], 201);
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason_code' => ['required', 'in:' . implode(',', array_keys(config('lifecycle.suspension_reasons')))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->lifecycle->suspend($tenant, $data['reason_code'], $data['note'] ?? null, $request->user()->id);

        return $this->ok($tenant->fresh());
    }

    public function activate(Tenant $tenant)
    {
        $this->lifecycle->activate($tenant);

        return $this->ok($tenant->fresh());
    }

    public function changePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'effective_from' => ['nullable', 'date'],
        ]);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $this->lifecycle->changePlan($tenant, $plan, isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : null);

        return $this->ok($tenant->fresh());
    }

    public function updateLimits(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'max_employees' => ['nullable', 'integer', 'min:-1'],
            'max_employees_override' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->lifecycle->updateLimits($tenant, $data['max_employees'] ?? null,
            array_key_exists('max_employees_override', $data) ? (int) $data['max_employees_override'] : null);

        return $this->ok($tenant->fresh());
    }

    public function features(Tenant $tenant)
    {
        return $this->ok($this->features->matrixForTenant($tenant->id));
    }

    public function updateFeatures(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'overrides' => ['required', 'array'],
            'overrides.*.feature_key' => ['required', 'in:' . implode(',', array_keys(config('features')))],
            'overrides.*.action' => ['required', 'in:enable,disable,clear'],
            'overrides.*.reason' => ['nullable', 'string', 'max:1000'],
        ]);

        foreach ($data['overrides'] as $o) {
            if ($o['action'] === 'clear') {
                TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', $o['feature_key'])->delete();
                $this->features->resetToPlan($tenant->id, $o['feature_key']);
            } else {
                TenantFeatureOverride::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_key' => $o['feature_key']],
                    ['is_enabled' => $o['action'] === 'enable', 'reason' => $o['reason'] ?? null, 'overridden_by' => $request->user()->id],
                );
            }
        }
        $this->features->bust($tenant->id);
        AuditLogger::record('feature_override.bulk', 'tenant_feature_overrides', null, null,
            ['count' => count($data['overrides'])], $tenant->id);

        return $this->ok($this->features->matrixForTenant($tenant->id));
    }

    public function impersonate(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['tenant_user_id' => ['required', 'integer']]);
        $target = User::where('id', $data['tenant_user_id'])->where('tenant_id', $tenant->id)->first();
        if (! $target || (string) $target->status !== '1') {
            return $this->fail('invalid_user', 'That user is not an active member of this tenant.');
        }

        $ttl = (int) config('platform.impersonation_ttl_minutes', 60);
        $token = Str::random(64);
        $session = ImpersonationSession::create([
            'super_admin_id' => $request->user()->id,
            'tenant_user_id' => $target->id,
            'tenant_id' => $tenant->id,
            'session_token' => $token,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
            'ip_address' => $request->ip(),
        ]);
        AuditLogger::record('impersonation.started', 'impersonation_sessions', $session->id, null,
            ['tenant_user_id' => $target->id], $tenant->id);

        return $this->ok([
            'session_id' => $session->id,
            'expires_at' => $session->expires_at,
            'consume_url' => rtrim(config('platform.hrm_web_url'), '/') . '/impersonate/consume?token=' . $token . '&tenant=' . $tenant->id,
        ], 201);
    }
}
