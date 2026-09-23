<?php

namespace App\Http\Controllers\Api;

use App\Models\SubscriptionPlan;
use App\Models\TenantSubscription;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends ApiController
{
    public function index()
    {
        return $this->ok(SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get());
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;
        $plan = SubscriptionPlan::create($data);
        AuditLogger::record('plan.created', 'subscription_plans', $plan->id, null, $plan->toArray());

        return $this->ok($plan, 201);
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $old = $plan->toArray();
        $plan->update($this->validated($request, $plan->id));
        AuditLogger::record('plan.updated', 'subscription_plans', $plan->id, $old, $plan->fresh()->toArray());

        return $this->ok($plan->fresh());
    }

    public function destroy(SubscriptionPlan $plan)
    {
        if (TenantSubscription::query()->active()->where('plan_id', $plan->id)->exists()) {
            return $this->fail('plan_in_use', 'Tenants are still on this plan — cannot deactivate.', 409);
        }
        $plan->update(['is_active' => false]);
        AuditLogger::record('plan.deactivated', 'subscription_plans', $plan->id, ['is_active' => true], ['is_active' => false]);

        return $this->ok($plan->fresh());
    }

    private function validated(Request $request, ?int $ignore = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:subscription_plans,slug' . ($ignore ? ",{$ignore}" : '')],
            'price' => ['nullable', 'numeric', 'min:0'],
            'pricing_type' => ['required', 'in:fixed,per_employee_per_day,per_employee_per_month'],
            'price_per_employee' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'in:monthly,quarterly,yearly,daily'],
            'max_employees' => ['required', 'integer'],
            'min_employees' => ['required', 'integer', 'min:0'],
            'free_employees' => ['required', 'integer', 'min:0'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['array'],
        ]);
        $features = [];
        foreach (array_keys(config('features')) as $k) {
            $features[$k] = (bool) $request->input("features.$k");
        }
        $data['features'] = $features;
        $data['is_active'] = $request->boolean('is_active');
        $data['price'] = $data['price'] ?? 0;

        return $data;
    }
}
