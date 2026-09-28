<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\ImpersonationSession;
use App\Models\PaymentLog;
use App\Models\Role;
use App\Models\SuperAdmin;
use App\Models\TenantNote;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantFeatureOverride;
use App\Models\TenantSubscription;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\FeatureService;
use App\Services\TenantLifecycleService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class TenantController extends Controller
{
    public function __construct(
        private FeatureService $features,
        private TenantLifecycleService $lifecycle,
    ) {
    }

    public function index(Request $request)
    {
        $q = Tenant::query()
            ->when($request->input('search'), function ($w, $s) {
                $w->where(fn ($x) => $x->where('company_name', 'like', "%{$s}%")
                    ->orWhere('subdomain', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%")
                    ->orWhere('phone', 'like', "%{$s}%"));
            })
            ->when($request->input('status'), fn ($w, $s) => $w->where('status', $s))
            ->when($request->input('plan_id'), fn ($w, $s) => $w->where('subscription_plan_id', $s))
            ->orderBy('company_name');

        $tenants = $q->paginate(20)->withQueryString();

        $headcounts = User::selectRaw('tenant_id, count(*) c')->groupBy('tenant_id')->pluck('c', 'tenant_id');
        $allPlans = SubscriptionPlan::orderBy('sort_order')->get();
        $plans = $allPlans->pluck('name', 'id');

        // One-click "impersonate the admin" from this list — the earliest active admin-role user per tenant.
        $primaryAdmins = User::where('role', 'admin')->where('status', '1')
            ->whereIn('tenant_id', $tenants->pluck('id'))
            ->orderBy('id')->get(['id', 'tenant_id', 'name'])
            ->groupBy('tenant_id')->map(fn ($g) => $g->first());

        return view('tenants.index', compact('tenants', 'headcounts', 'plans', 'allPlans', 'primaryAdmins'));
    }

    public function show(Request $request, Tenant $tenant)
    {
        $tab = $request->input('tab', 'overview');
        $sub = $tenant->activeSubscription();
        $plan = $sub ? SubscriptionPlan::find($sub->plan_id) : SubscriptionPlan::find($tenant->subscription_plan_id);

        $data = [
            'tenant' => $tenant,
            'company' => $tenant->company,
            'sub' => $sub,
            'plan' => $plan,
            'tab' => $tab,
            'userCount' => User::where('tenant_id', $tenant->id)->count(),
            'payment' => $sub?->payment_log_id ? PaymentLog::find($sub->payment_log_id) : null,
            'trackingSeatsUsed' => DB::table('user_job_details')
                ->join('users', 'users.id', '=', 'user_job_details.user_id')
                ->where('users.tenant_id', $tenant->id)
                ->where('users.status', '1')
                ->where('user_job_details.location_tracking_enabled', 1)
                ->count(),
        ];

        if ($tab === 'features') {
            $data['matrix'] = $this->features->matrixForTenant($tenant->id);
        } elseif ($tab === 'users') {
            $data['users'] = User::where('tenant_id', $tenant->id)
                ->orderBy('name')->paginate(25, ['id', 'name', 'email', 'role', 'employee_id', 'status'])
                ->withQueryString();
        } elseif ($tab === 'billing') {
            $data['payments'] = PaymentLog::where('tenant_id', $tenant->id)->orderByDesc('payment_date')->get();
        } elseif ($tab === 'audit') {
            $q = trim((string) $request->input('audit_q', ''));
            $logsQuery = AuditLog::where('tenant_id', $tenant->id);

            if ($q !== '') {
                $matchingAdminIds = SuperAdmin::where('name', 'like', "%{$q}%")->pluck('id');
                $matchingUserIds = User::where('tenant_id', $tenant->id)->where('name', 'like', "%{$q}%")->pluck('id');
                $logsQuery->where(function ($w) use ($q, $matchingAdminIds, $matchingUserIds) {
                    $w->where('action', 'like', "%{$q}%")
                        ->orWhere('entity_type', 'like', "%{$q}%")
                        ->orWhere(fn ($x) => $x->whereIn('actor_type', ['super_admin', 'super_admin_impersonating'])->whereIn('actor_id', $matchingAdminIds))
                        ->orWhere(fn ($x) => $x->where('actor_type', 'tenant_user')->whereIn('actor_id', $matchingUserIds));
                });
            }

            $logs = $logsQuery->orderByDesc('created_at')->paginate(30)->withQueryString();
            $data['logs'] = $logs;
            $data['auditQuery'] = $q;

            $items = collect($logs->items());
            $adminIds = $items->whereIn('actor_type', ['super_admin', 'super_admin_impersonating'])->pluck('actor_id')->filter()->unique();
            $userIds = $items->where('actor_type', 'tenant_user')->pluck('actor_id')->filter()->unique();
            $data['actorAdminNames'] = SuperAdmin::whereIn('id', $adminIds)->pluck('name', 'id');
            $data['actorUserNames'] = User::whereIn('id', $userIds)->pluck('name', 'id');
        } elseif ($tab === 'roles') {
            $data['roles'] = Role::where('tenant_id', $tenant->id)->with('permissions')->orderByDesc('is_system')->orderBy('name')->get();
            $data['modules'] = config('rbac.modules');
            $data['actions'] = config('rbac.actions');
            $data['scopes'] = config('rbac.scopes');
            $data['otherTenants'] = Tenant::where('id', '!=', $tenant->id)->orderBy('company_name')->pluck('company_name', 'id');
            $data['roleUserCounts'] = User::where('tenant_id', $tenant->id)
                ->whereNotNull('role_id')->selectRaw('role_id, count(*) c')->groupBy('role_id')->pluck('c', 'role_id');
        } elseif ($tab === 'support') {
            $data['notes'] = TenantNote::where('tenant_id', $tenant->id)->with('author')->orderByDesc('id')->get();
            $data['tenantUsers'] = User::where('tenant_id', $tenant->id)->orderByRaw("FIELD(role,'admin','hr','manager') , name")
                ->get(['id', 'name', 'email', 'role', 'status']);
            $data['impersonations'] = ImpersonationSession::where('tenant_id', $tenant->id)
                ->with('superAdmin')->orderByDesc('started_at')->limit(15)->get();
            $data['recentLogins'] = User::where('tenant_id', $tenant->id)->whereNotNull('last_login_at')
                ->orderByDesc('last_login_at')->limit(10)->get(['id', 'name', 'last_login_at']);
        } elseif ($tab === 'lifecycle') {
            $data['plans'] = SubscriptionPlan::where('is_active', true)->orderBy('sort_order')->get();
            $data['subTimeline'] = TenantSubscription::where('tenant_id', $tenant->id)
                ->orderByDesc('id')->get();
            $data['reasons'] = config('lifecycle.suspension_reasons');
            $data['graceDays'] = (int) config('lifecycle.deletion_grace_days');
            $data['matrix'] = $this->features->matrixForTenant($tenant->id);
            $data['history'] = AuditLog::where('tenant_id', $tenant->id)
                ->whereIn('action', [
                    'tenant.renewed', 'tenant.plan_changed', 'tenant.updated',
                    'tenant.limits_updated', 'tenant.trial_converted', 'tenant.trial_extended',
                    'tenant.tracking_updated', 'tenant.duration_set',
                    'feature_override.applied', 'feature_override.bulk',
                ])
                ->orderByDesc('id')->limit(25)->get()
                ->map(fn ($h) => [
                    'when' => optional($h->created_at)->format('d M Y H:i'),
                    'label' => $this->historyLabel($h->action),
                    'detail' => $this->historyDetail($h->action, $h->new_values ?? []),
                ]);
        }

        return view('tenants.show', $data);
    }

    public function edit(Request $request, Tenant $tenant)
    {
        $view = $request->boolean('drawer') ? 'tenants._edit_form' : 'tenants.edit';

        return view($view, ['tenant' => $tenant, 'company' => $tenant->company]);
    }

    public function update(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'company_name' => ['required', 'string', 'max:255'],
            'display_name' => ['nullable', 'string', 'max:255'],
            'legal_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'city' => ['nullable', 'string', 'max:100'],
            'state' => ['nullable', 'string', 'max:100'],
            'country' => ['required', 'string', 'max:100'],
            'pincode' => ['nullable', 'string', 'max:20'],
            'gst_number' => ['nullable', 'string', 'max:50'],
            'pan_number' => ['nullable', 'string', 'max:50'],
            'timezone' => ['required', 'string', 'max:100'],
            'currency' => ['required', 'string', 'max:10'],
            'currency_symbol' => ['nullable', 'string', 'max:10'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ]);

        $old = $tenant->only(['company_name', 'display_name', 'email', 'phone', 'address', 'city',
            'state', 'country', 'pincode', 'gst_number', 'pan_number', 'timezone', 'currency', 'currency_symbol']);

        if ($request->hasFile('logo')) {
            // New logo stored first; the previous one is removed after the update commits.
            $data['logo'] = file_storage()->replace($tenant->logo, $request->file('logo'), 'tenant_logo')->path;
        }
        $legalName = $data['legal_name'] ?? $data['company_name'];
        unset($data['legal_name']);

        $tenant->update($data);

        if ($tenant->company) {
            $tenant->company->update([
                'name' => $data['company_name'],
                'legal_name' => $legalName,
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'address' => $data['address'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'country' => $data['country'],
                'pincode' => $data['pincode'] ?? null,
                'gstnumber' => $data['gst_number'] ?? null,
                'pannumber' => $data['pan_number'] ?? null,
                'timezone' => $data['timezone'],
                'currency' => $data['currency'],
                'currencysymbol' => $data['currency_symbol'] ?? $tenant->company->currencysymbol,
            ] + (isset($data['logo']) ? ['logo' => $data['logo']] : []));
        }

        AuditLogger::record('tenant.updated', 'tenants', $tenant->id, $old, $data, $tenant->id);

        return redirect()->route('tenants.show', $tenant)->with('success', 'Tenant details updated.');
    }

    public function updateFeature(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'feature_key' => ['required', 'string', 'in:' . implode(',', array_keys(config('features')))],
            'action' => ['required', 'in:enable,disable,clear'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $existing = TenantFeatureOverride::where('tenant_id', $tenant->id)
            ->where('feature_key', $data['feature_key'])->first();
        $old = $existing?->only(['is_enabled', 'reason']);

        if ($data['action'] === 'clear') {
            $existing?->delete();
            $new = null;
        } else {
            $row = TenantFeatureOverride::updateOrCreate(
                ['tenant_id' => $tenant->id, 'feature_key' => $data['feature_key']],
                [
                    'is_enabled' => $data['action'] === 'enable',
                    'reason' => $data['reason'] ?? null,
                    'overridden_by' => $request->user()->id,
                ],
            );
            $new = $row->only(['is_enabled', 'reason']);
        }

        $this->features->bust($tenant->id);
        AuditLogger::record('feature_override.applied', 'tenant_feature_overrides', $existing?->id,
            $old, ['feature_key' => $data['feature_key'], 'action' => $data['action']] + ($new ?? []),
            $tenant->id);

        return back()->with('success', "Feature '{$data['feature_key']}' override {$data['action']}d.");
    }

    public function recordPayment(Request $request, Tenant $tenant)
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
            'tenant_id' => $tenant->id,
            'collected_by' => $request->user()->id,
        ]);

        AuditLogger::record('payment.recorded', 'payment_logs', $log->id, null, $log->toArray(), $tenant->id);

        return back()->with('success', 'Payment recorded.');
    }

    public function suspend(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'reason_code' => ['required', 'string', 'in:' . implode(',', array_keys(config('lifecycle.suspension_reasons')))],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->lifecycle->suspend($tenant, $data['reason_code'], $data['note'] ?? null, $request->user()->id);

        return back()->with('success', 'Tenant suspended.');
    }

    public function activate(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['extend_to' => ['nullable', 'date', 'after:today']]);
        $this->lifecycle->activate($tenant, isset($data['extend_to']) ? Carbon::parse($data['extend_to']) : null);

        return back()->with('success', 'Tenant reactivated.');
    }

    public function changePlan(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'plan_id' => ['required', 'integer', 'exists:subscription_plans,id'],
            'effective_from' => ['nullable', 'date'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $plan = SubscriptionPlan::findOrFail($data['plan_id']);
        $this->lifecycle->changePlan($tenant, $plan,
            isset($data['effective_from']) ? Carbon::parse($data['effective_from']) : null,
            $data['note'] ?? null);

        return back()->with('success', "Plan changed to {$plan->name}.");
    }

    public function updateLimits(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'max_employees' => ['nullable', 'integer', 'min:-1'],
            'max_employees_override' => ['nullable', 'integer', 'min:0'],
        ]);
        $this->lifecycle->updateLimits($tenant,
            $data['max_employees'] ?? null,
            array_key_exists('max_employees_override', $data) ? (int) $data['max_employees_override'] : null);

        return back()->with('success', 'Employee limits updated.');
    }

    public function updateTracking(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'field_tracking_enabled' => ['nullable', 'boolean'],
            'field_tracking_seats' => ['required', 'integer', 'min:0'],
        ]);
        $this->lifecycle->updateTracking($tenant, $request->boolean('field_tracking_enabled'), (int) $data['field_tracking_seats']);

        return back()->with('success', 'Location tracking settings updated.');
    }

    public function setDuration(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'duration_months' => ['nullable', 'integer', 'in:1,3,6,12'],
            'end_date' => ['nullable', 'date', 'after:today'],
        ]);
        if (empty($data['duration_months']) && empty($data['end_date'])) {
            return back()->withErrors(['end_date' => 'Pick a duration or a custom date.']);
        }
        $end = ! empty($data['end_date']) ? Carbon::parse($data['end_date']) : now()->addMonths((int) $data['duration_months']);
        $this->lifecycle->setDuration($tenant, $end);

        return back()->with('success', 'Subscription duration updated.');
    }

    public function renew(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_mode' => ['required', 'in:upi,bank_transfer,cheque,cash,card'],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'new_end_date' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'features' => ['nullable', 'array'],
        ]);
        $end = Carbon::parse($data['new_end_date']);
        $features = $data['features'] ?? null;
        unset($data['new_end_date'], $data['features']);

        $this->lifecycle->renew($tenant, $data, $end);

        $changed = is_array($features)
            ? $this->applyFeatureDiff($request, $tenant, $features, 'renewal', 'Adjusted at renewal')
            : 0;

        $msg = 'Renewal recorded; subscription extended to ' . $end->toDateString() . '.';
        if ($changed > 0) {
            $msg .= " {$changed} module(s) adjusted.";
        }

        return back()->with('success', $msg);
    }

    /**
     * Turn modules on/off for a tenant right now, independent of any renewal
     * or payment — for reducing/adding modules on an existing plan without
     * touching the subscription term. Same bulk-diff logic `renew()` uses for
     * its own optional feature checklist.
     */
    public function updateModules(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['features' => ['required', 'array']]);

        $changed = $this->applyFeatureDiff($request, $tenant, $data['features'], 'manual', 'Adjusted directly (no renewal)');

        return back()->with('success', $changed > 0 ? "{$changed} module(s) updated." : 'No module changes to apply.');
    }

    /**
     * Diffs a posted `features[key] => bool` map against each key's current
     * plan-snapshot value: matching the plan value clears any stale override,
     * differing from it writes/updates one. Returns how many keys changed.
     */
    private function applyFeatureDiff(Request $request, Tenant $tenant, array $features, string $context, string $reason): int
    {
        $sub = $tenant->activeSubscription();
        $planFeatures = $sub?->features_snapshot ?? [];
        $changed = 0;

        foreach (array_keys(config('features')) as $key) {
            $want = (bool) ($features[$key] ?? false);
            $planVal = (bool) ($planFeatures[$key] ?? config("features.$key.default", false));
            $existing = TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', $key)->first();

            if ($want === $planVal) {
                if ($existing) {
                    $existing->delete();
                    $changed++;
                }

                continue;
            }
            if (! $existing || (bool) $existing->is_enabled !== $want) {
                TenantFeatureOverride::updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature_key' => $key],
                    ['is_enabled' => $want, 'reason' => $reason, 'overridden_by' => $request->user()->id],
                );
                $changed++;
            }
        }

        if ($changed > 0) {
            $this->features->bust($tenant->id);
            AuditLogger::record('feature_override.bulk', 'tenant_feature_overrides', null, null,
                ['count' => $changed, 'context' => $context], $tenant->id);
        }

        return $changed;
    }

    public function extendTrial(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['trial_ends_at' => ['required', 'date', 'after:today']]);
        $this->lifecycle->extendTrial($tenant, Carbon::parse($data['trial_ends_at']));

        return back()->with('success', 'Trial extended.');
    }

    public function convertTrial(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:0'],
            'currency' => ['required', 'string', 'max:10'],
            'payment_mode' => ['required', 'in:upi,bank_transfer,cheque,cash,card'],
            'reference_number' => ['required', 'string', 'max:255'],
            'payment_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after:today'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $end = Carbon::parse($data['end_date']);
        unset($data['end_date']);
        $this->lifecycle->convertTrial($tenant, $data, $end);

        return back()->with('success', 'Trial converted to a paid subscription.');
    }

    public function requestDeletion(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $this->lifecycle->requestDeletion($tenant, $data['reason']);

        return back()->with('success', 'Deletion requested — runs in ' . config('lifecycle.deletion_grace_days') . ' days unless cancelled.');
    }

    public function cancelDeletion(Tenant $tenant)
    {
        $this->lifecycle->cancelDeletion($tenant);

        return back()->with('success', 'Deletion request cancelled.');
    }

    public function addNote(Request $request, Tenant $tenant)
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        TenantNote::create([
            'tenant_id' => $tenant->id,
            'super_admin_id' => $request->user()->id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Note added.');
    }

    public function deleteNote(Tenant $tenant, TenantNote $note)
    {
        abort_unless((int) $note->tenant_id === (int) $tenant->id, 404);
        $note->delete();

        return back()->with('success', 'Note removed.');
    }

    private function historyLabel(string $action): string
    {
        return match ($action) {
            'tenant.renewed' => 'Renewed',
            'tenant.plan_changed' => 'Plan changed',
            'tenant.updated' => 'Profile edited',
            'tenant.limits_updated' => 'Limits updated',
            'tenant.trial_converted' => 'Trial converted',
            'tenant.trial_extended' => 'Trial extended',
            'tenant.tracking_updated' => 'Tracking updated',
            'tenant.duration_set' => 'Duration set',
            'feature_override.applied' => 'Module override',
            'feature_override.bulk' => 'Modules adjusted',
            default => $action,
        };
    }

    private function historyDetail(string $action, array $new): string
    {
        return match ($action) {
            'tenant.renewed' => 'New end date: ' . ($new['end_date'] ?? '—'),
            'tenant.plan_changed' => 'Plan #' . ($new['plan_id'] ?? '?') . ($new['note'] ? ' — ' . $new['note'] : ''),
            'tenant.updated' => count($new) . ' field(s) changed',
            'tenant.limits_updated' => 'Max employees: ' . ($new['max_employees'] ?? '—'),
            'tenant.tracking_updated' => 'Tracking ' . (($new['field_tracking_enabled'] ?? false) ? 'enabled' : 'disabled') . ', seats: ' . ($new['field_tracking_seats'] ?? '—'),
            'tenant.duration_set' => 'New end date: ' . ($new['end_date'] ?? '—'),
            'feature_override.bulk' => ($new['count'] ?? '?') . ' module(s)' . (isset($new['context']) ? ' (' . $new['context'] . ')' : ''),
            default => '',
        };
    }
}
