<?php

namespace App\Http\Controllers;

use App\Models\PlanVersion;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PlanController extends Controller
{
    public function index()
    {
        $plans = SubscriptionPlan::orderBy('sort_order')->orderBy('id')->get();
        // reorder(): active() sorts by start_date, which MySQL rejects in a grouped query (only_full_group_by).
        $counts = TenantSubscription::query()->active()->reorder()->selectRaw('plan_id, count(distinct tenant_id) c')
            ->groupBy('plan_id')->pluck('c', 'plan_id');

        return view('plans.index', compact('plans', 'counts'));
    }

    public function create(Request $request)
    {
        return view($this->formView($request), [
            'plan' => new SubscriptionPlan(['features' => [], 'is_active' => true]),
            'features' => config('features'),
            'tenantCount' => 0,
            'versions' => collect(),
        ]);
    }

    public function edit(Request $request, SubscriptionPlan $plan)
    {
        return view($this->formView($request), [
            'plan' => $plan,
            'features' => config('features'),
            'tenantCount' => Tenant::where('subscription_plan_id', $plan->id)->count(),
            'versions' => PlanVersion::where('plan_id', $plan->id)->orderByDesc('version')->get(),
        ]);
    }

    /** Bare partial (no layout) when opened in the side drawer, full page otherwise. */
    private function formView(Request $request): string
    {
        return $request->boolean('drawer') ? 'plans._form' : 'plans.form';
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['created_by'] = $request->user()->id;

        $plan = SubscriptionPlan::create($data);
        $this->snapshot($plan, $request->user()->id, 'Created');
        AuditLogger::record('plan.created', 'subscription_plans', $plan->id, null, $plan->toArray());

        return redirect()->route('plans.index')->with('success', "Plan '{$plan->name}' created.");
    }

    public function update(Request $request, SubscriptionPlan $plan)
    {
        $old = $plan->toArray();
        $oldFeatures = $plan->features ?? [];

        $plan->update($this->validated($request, $plan));
        $this->snapshot($plan, $request->user()->id, $request->input('note'));

        $diff = $this->featureDiff($oldFeatures, $plan->features ?? []);
        $affected = Tenant::where('subscription_plan_id', $plan->id)->count();

        AuditLogger::record('plan.updated', 'subscription_plans', $plan->id, $old,
            $plan->fresh()->toArray() + ['feature_diff' => $diff]);

        $msg = "Plan '{$plan->name}' updated.";
        if ($diff['added'] || $diff['removed']) {
            $msg .= " Feature changes apply to NEW subscriptions only — {$affected} existing tenant(s) keep their snapshot.";
        }

        return redirect()->route('plans.index')->with('success', $msg);
    }

    public function destroy(SubscriptionPlan $plan)
    {
        $onPlan = TenantSubscription::query()->active()->where('plan_id', $plan->id)->exists();
        if ($onPlan) {
            return back()->with('error', 'Cannot deactivate — tenants are still on this plan.');
        }

        $plan->update(['is_active' => false]);
        AuditLogger::record('plan.deactivated', 'subscription_plans', $plan->id, ['is_active' => true], ['is_active' => false]);

        return back()->with('success', "Plan '{$plan->name}' deactivated.");
    }

    /** Status switch on the plans table — flip active / inactive. */
    public function toggle(SubscriptionPlan $plan)
    {
        if ($plan->is_active) {
            if (TenantSubscription::query()->active()->where('plan_id', $plan->id)->exists()) {
                return back()->with('error', "Cannot deactivate '{$plan->name}' — tenants are still on this plan.");
            }
            $plan->update(['is_active' => false]);
            AuditLogger::record('plan.deactivated', 'subscription_plans', $plan->id, ['is_active' => true], ['is_active' => false]);

            return back()->with('success', "Plan '{$plan->name}' deactivated.");
        }

        $plan->update(['is_active' => true]);
        AuditLogger::record('plan.activated', 'subscription_plans', $plan->id, ['is_active' => false], ['is_active' => true]);

        return back()->with('success', "Plan '{$plan->name}' activated.");
    }

    /** Restore a plan's features/price/limits from a stored version. */
    public function revert(Request $request, SubscriptionPlan $plan, PlanVersion $version)
    {
        abort_unless($version->plan_id === $plan->id, 404);

        $old = $plan->toArray();
        $plan->update([
            'name' => $version->name,
            'price' => $version->price,
            'pricing_type' => $version->pricing_type,
            'price_per_employee' => $version->price_per_employee,
            'billing_cycle' => $version->billing_cycle,
            'max_employees' => $version->max_employees,
            'features' => $version->features,
        ]);
        $this->snapshot($plan, $request->user()->id, "Reverted to v{$version->version}");
        AuditLogger::record('plan.reverted', 'subscription_plans', $plan->id, $old,
            $plan->fresh()->toArray() + ['from_version' => $version->version]);

        return redirect()->route('plans.edit', $plan)->with('success', "Reverted to version {$version->version}.");
    }

    // ------------------------------------------------------------------

    private function snapshot(SubscriptionPlan $plan, ?int $userId, ?string $note): void
    {
        $next = (int) PlanVersion::where('plan_id', $plan->id)->max('version') + 1;
        PlanVersion::create([
            'plan_id' => $plan->id,
            'version' => $next,
            'name' => $plan->name,
            'price' => $plan->price,
            'pricing_type' => $plan->pricing_type,
            'price_per_employee' => $plan->price_per_employee,
            'billing_cycle' => $plan->billing_cycle,
            'max_employees' => $plan->max_employees,
            'features' => $plan->features ?? [],
            'note' => $note,
            'changed_by' => $userId,
            'created_at' => now(),
        ]);
    }

    /** @return array{added:string[], removed:string[]} */
    private function featureDiff(array $old, array $new): array
    {
        $onOld = array_keys(array_filter($old));
        $onNew = array_keys(array_filter($new));

        return [
            'added' => array_values(array_diff($onNew, $onOld)),
            'removed' => array_values(array_diff($onOld, $onNew)),
        ];
    }

    private function validated(Request $request, ?SubscriptionPlan $plan = null): array
    {
        $ignoreId = $plan && $plan->exists ? $plan->id : null;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['nullable', 'string', 'max:100', 'alpha_dash', 'unique:subscription_plans,slug' . ($ignoreId ? ",{$ignoreId}" : '')],
            'price' => ['nullable', 'numeric', 'min:0'],
            'pricing_type' => ['required', 'in:fixed,per_employee_per_day,per_employee_per_month'],
            'price_per_employee' => ['nullable', 'numeric', 'min:0'],
            'billing_cycle' => ['nullable', 'in:monthly,quarterly,yearly,daily'],
            'trial_days' => ['required', 'integer', 'min:0'],
            'description' => ['nullable', 'string'],
            'sort_order' => ['required', 'integer'],
            'is_active' => ['nullable', 'boolean'],
            'features' => ['array'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $features = [];
        foreach (array_keys(config('features')) as $key) {
            $features[$key] = (bool) ($request->input("features.{$key}"));
        }
        $data['features'] = $features;
        // The form no longer carries an "active" toggle — keep the plan's current
        // state (new plans default to active). Deactivation is a separate action.
        $data['is_active'] = $request->has('is_active')
            ? $request->boolean('is_active')
            : ($plan?->is_active ?? true);
        $data['price'] = $data['price'] ?? 0;

        // Employee limits are no longer edited on this form — keep the plan's
        // existing values, or the column defaults for a brand-new plan.
        $data['min_employees'] = $plan?->min_employees ?? 1;
        $data['max_employees'] = $plan?->max_employees ?? -1;
        $data['free_employees'] = $plan?->free_employees ?? 0;

        unset($data['note']);

        return $data;
    }
}
