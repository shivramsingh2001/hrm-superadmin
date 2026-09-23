<?php

namespace App\Services;

use App\Mail\TenantWelcomeMail;
use App\Models\Company;
use App\Models\Inquiry;
use App\Models\LeaveType;
use App\Models\ProvisioningRun;
use App\Models\Role;
use App\Models\RolePermission;
use App\Models\Shift;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantDefaultConfig;
use App\Models\TenantFeatureOverride;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Turns a paid enquiry (or a manual form submission) into a fully working tenant.
 * Atomic (single DB transaction) and idempotent — every step is existence-checked
 * so a failed run can be retried from the start without creating duplicates.
 *
 * Returns ['tenant' => Tenant, 'admin_password' => string|null, 'run' => ProvisioningRun].
 * admin_password is the plaintext, shown to the operator ONCE (null on a re-run
 * that found the user already there).
 */
class ProvisioningService
{
    /** @param array<string,mixed> $input */
    public function provision(array $input, ?Inquiry $inquiry = null): array
    {
        $subdomain = Str::lower(trim($input['subdomain'] ?? ''));
        $this->assertSubdomain($subdomain);

        $plan = SubscriptionPlan::findOrFail($input['plan_id']);

        $run = ProvisioningRun::firstOrNew([
            'inquiry_id' => $inquiry?->id,
            'subdomain' => $subdomain,
        ]);
        $run->fill([
            'status' => 'running',
            'input' => $this->scrubInput($input),
            'created_by' => Auth::id(),
            'error' => null,
            'failed_step' => null,
        ])->save();

        // Idempotent short-circuit: already provisioned and the tenant still exists.
        if ($run->tenant_id && ($existing = Tenant::find($run->tenant_id))) {
            $run->update(['status' => 'done']);

            return ['tenant' => $existing, 'admin_password' => null, 'run' => $run];
        }

        $plainPassword = null;
        $steps = $run->steps ?? [];

        try {
            $tenant = DB::transaction(function () use ($input, $subdomain, $plan, $inquiry, &$steps, &$plainPassword) {

                // 1. tenant
                $tenant = Tenant::firstOrNew(['subdomain' => $subdomain]);
                $isTrial = (bool) ($input['is_trial'] ?? false);
                $tenant->fill([
                    'uuid' => $tenant->uuid ?: (string) Str::uuid(),
                    'company_name' => $input['company_name'],
                    'display_name' => $input['display_name'] ?? null,
                    'email' => $input['admin_email'],
                    'phone' => $input['phone'] ?? null,
                    'logo' => $input['logo'] ?? null,
                    'address' => $input['address'] ?? null,
                    'city' => $input['city'] ?? null,
                    'state' => $input['state'] ?? null,
                    'country' => $input['country'] ?? 'India',
                    'pincode' => $input['pincode'] ?? null,
                    'gst_number' => $input['gst_number'] ?? null,
                    'pan_number' => $input['pan_number'] ?? null,
                    'timezone' => $input['timezone'] ?? 'Asia/Kolkata',
                    'currency' => $input['currency'] ?? 'INR',
                    'currency_symbol' => $input['currency_symbol'] ?? '₹',
                    'status' => 'active',
                    'subscription_plan' => $plan->slug,
                    'subscription_plan_id' => $plan->id,
                    'max_employees' => (int) ($input['max_employees'] ?? $plan->max_employees ?: 100),
                    'trial_ends_at' => $isTrial ? now()->addDays((int) ($plan->trial_days ?: 14)) : null,
                    'custom_shifts_enabled' => true,
                    'default_weekoff_days' => json_encode($input['weekoff_days'] ?? config('provisioning.default_config.weekoff_days')),
                    'field_tracking_enabled' => (bool) ($input['field_tracking_enabled'] ?? false),
                    'field_tracking_seats' => (int) ($input['field_tracking_seats'] ?? 0),
                    'created_by' => Auth::id(),
                ]);
                $tenant->save();
                $steps['tenant'] = $tenant->id;

                // 2. company
                $company = Company::firstOrNew(['tenant_id' => $tenant->id]);
                $company->fill([
                    'name' => $input['company_name'],
                    'legal_name' => $input['legal_name'] ?? $input['company_name'],
                    'email' => $input['admin_email'],
                    'phone' => $input['phone'] ?? null,
                    'logo' => $input['logo'] ?? null,
                    'subdomain' => $subdomain,
                    'address' => $input['address'] ?? null,
                    'city' => $input['city'] ?? null,
                    'state' => $input['state'] ?? null,
                    'country' => $input['country'] ?? 'India',
                    'pincode' => $input['pincode'] ?? null,
                    'gstnumber' => $input['gst_number'] ?? null,
                    'pannumber' => $input['pan_number'] ?? null,
                    'timezone' => $input['timezone'] ?? 'Asia/Kolkata',
                    'currency' => $input['currency'] ?? 'INR',
                    'currencysymbol' => $input['currency_symbol'] ?? '₹',
                    'isdefault' => 1,
                    'status' => 1,
                    'createdby' => Auth::id(),
                ]);
                $company->save();
                $steps['company'] = $company->id;

                // 3. default shift
                $sc = $input['shift'] ?? [];
                $shiftDefaults = config('provisioning.default_shift');
                $shiftName = $sc['name'] ?? $shiftDefaults['name'];
                $start = $sc['start_time'] ?? $shiftDefaults['start_time'];
                $end = $sc['end_time'] ?? $shiftDefaults['end_time'];
                $shift = Shift::firstOrNew(['tenant_id' => $tenant->id, 'name' => $shiftName]);
                $shift->fill([
                    'start_time' => $start,
                    'end_time' => $end,
                    'total_hours' => number_format($this->hoursBetween($start, $end), 2, '.', ''),
                    'grace_minutes' => (int) ($sc['grace_minutes'] ?? $shiftDefaults['grace_minutes']),
                    'break_time' => (int) ($sc['break_time'] ?? $shiftDefaults['break_time']),
                    'color_code' => $shiftDefaults['color_code'],
                    'status' => 1,
                    'created_by' => Auth::id() ?? 0,
                ]);
                $shift->save();
                $steps['shift'] = $shift->id;

                $tenant->update(['default_shift_id' => $shift->id]);

                // 4. tenant_default_config
                $dc = config('provisioning.default_config');
                TenantDefaultConfig::updateOrCreate(
                    ['tenant_id' => $tenant->id],
                    [
                        'default_shift_id' => $shift->id,
                        'working_days' => json_encode($input['working_days'] ?? $dc['working_days']),
                        'working_hours_per_day' => (float) ($input['working_hours_per_day'] ?? $dc['working_hours_per_day']),
                        'grace_minutes' => (int) ($input['grace_minutes'] ?? $dc['grace_minutes']),
                        'overtime_threshold_minutes' => (int) ($input['overtime_threshold_minutes'] ?? $dc['overtime_threshold_minutes']),
                    ],
                );
                $steps['default_config'] = true;

                // 5. subscription
                $sub = TenantSubscription::where('tenant_id', $tenant->id)
                    ->whereIn('status', ['active', 'trial'])->first();
                if (! $sub) {
                    $endDate = null;
                    if (! empty($input['end_date'])) {
                        $endDate = Carbon::parse($input['end_date'])->toDateString();
                    } elseif (! empty($input['duration_months'])) {
                        $endDate = now()->addMonths((int) $input['duration_months'])->toDateString();
                    }

                    $sub = TenantSubscription::create([
                        'tenant_id' => $tenant->id,
                        'plan_id' => $plan->id,
                        'features_snapshot' => $plan->features ?? [],   // model casts array -> json
                        'start_date' => now()->toDateString(),
                        'end_date' => $endDate,
                        'trial_ends_at' => $isTrial ? now()->addDays((int) ($plan->trial_days ?: 14)) : null,
                        'status' => $isTrial ? 'trial' : 'active',
                        'created_by' => Auth::id(),
                    ]);
                }
                $steps['subscription'] = $sub->id;

                // 6. feature overrides (only where the desired value differs from the plan default)
                $planFeatures = $plan->features ?? [];
                $desired = $input['features'] ?? [];
                $overrides = 0;
                foreach (array_keys(config('features')) as $key) {
                    if (! array_key_exists($key, $desired)) {
                        continue;
                    }
                    $want = (bool) $desired[$key];
                    $planVal = (bool) ($planFeatures[$key] ?? config("features.$key.default", false));
                    if ($want === $planVal) {
                        TenantFeatureOverride::where('tenant_id', $tenant->id)->where('feature_key', $key)->delete();

                        continue;
                    }
                    TenantFeatureOverride::updateOrCreate(
                        ['tenant_id' => $tenant->id, 'feature_key' => $key],
                        ['is_enabled' => $want, 'reason' => 'Set during provisioning', 'overridden_by' => Auth::id()],
                    );
                    $overrides++;
                }
                $steps['feature_overrides'] = $overrides;

                // 7. tenant-admin user
                $admin = User::where('email', $input['admin_email'])->where('tenant_id', $tenant->id)->first();
                if (! $admin) {
                    $plainPassword = Str::password(14);
                    $admin = new User();
                    $admin->forceFill([
                        'name' => $input['admin_name'],
                        'email' => $input['admin_email'],
                        'contact' => $input['admin_contact'] ?? null,
                        'role' => 'admin',
                        'status' => '1',
                        'tenant_id' => $tenant->id,
                        'password' => Hash::make($plainPassword),
                        'timezone' => $input['timezone'] ?? 'Asia/Kolkata',
                    ]);
                    $admin->save();
                    $admin->forceFill([
                        'employee_id' => config('provisioning.employee_id_prefix') . str_pad((string) $admin->id, 6, '0', STR_PAD_LEFT),
                    ])->save();
                }
                $steps['admin_user'] = $admin->id;

                // 8. leave types
                foreach (config('provisioning.leave_types') as $lt) {
                    LeaveType::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'name' => $lt['name']],
                        [
                            'company_id' => $company->id,
                            'credit_type' => $lt['credit_type'],
                            'credit_value' => $lt['credit_value'],
                            'description' => $lt['description'] ?? null,
                            'code' => $lt['code'] ?? null,
                            'status' => 1,
                        ],
                    );
                }
                $steps['leave_types'] = count(config('provisioning.leave_types'));

                // 9. roles + permissions
                $allActions = config('provisioning.all_actions');
                foreach (config('provisioning.roles') as $slug => $def) {
                    $role = Role::firstOrCreate(
                        ['tenant_id' => $tenant->id, 'slug' => $slug],
                        ['name' => $def['name'], 'is_system' => $def['is_system'] ? 1 : 0, 'description' => $def['name'] . ' role'],
                    );
                    foreach ($def['permissions'] as $module => $actions) {
                        $actions = $actions === 'all' ? $allActions : (array) $actions;
                        foreach ($actions as $action) {
                            RolePermission::firstOrCreate(['role_id' => $role->id, 'module' => $module, 'action' => $action]);
                        }
                    }
                }
                $steps['roles'] = count(config('provisioning.roles'));

                // 10. welcome email — best effort, never rolls back provisioning
                if ($plainPassword) {
                    try {
                        Mail::to($input['admin_email'])->send(new TenantWelcomeMail(
                            $tenant->company_name,
                            $subdomain,
                            $input['admin_name'],
                            $input['admin_email'],
                            $plainPassword,
                        ));
                        $steps['welcome_email'] = 'sent';
                    } catch (\Throwable $e) {
                        Log::warning('Provisioning welcome email failed: ' . $e->getMessage());
                        $steps['welcome_email'] = 'failed: ' . $e->getMessage();
                    }
                }

                // 11. audit + link the enquiry
                AuditLogger::record('tenant.provisioned', 'tenants', $tenant->id, null, [
                    'subdomain' => $subdomain,
                    'plan' => $plan->slug,
                    'trial' => $isTrial,
                    'feature_overrides' => $steps['feature_overrides'] ?? 0,
                    'inquiry_id' => $inquiry?->id,
                ], $tenant->id);

                if ($inquiry) {
                    $inquiry->update(['status' => 'provisioned']);
                }

                return $tenant;
            });
        } catch (\Throwable $e) {
            $run->update([
                'status' => 'failed',
                'failed_step' => $this->lastStep($steps),
                'error' => Str::limit($e->getMessage(), 1000),
                'steps' => $steps,
            ]);
            AuditLogger::record('tenant.provision_failed', 'sa_provisioning_runs', $run->id, null, [
                'subdomain' => $subdomain, 'error' => Str::limit($e->getMessage(), 300),
            ]);
            throw $e;
        }

        $run->update(['status' => 'done', 'tenant_id' => $tenant->id, 'steps' => $steps]);

        return ['tenant' => $tenant, 'admin_password' => $plainPassword, 'run' => $run];
    }

    private function assertSubdomain(string $sub): void
    {
        if (! preg_match('/^[a-z0-9](?:[a-z0-9-]{1,48}[a-z0-9])$/', $sub)) {
            throw ValidationException::withMessages(['subdomain' => 'Use 3–50 chars: lowercase letters, digits, hyphens; no leading/trailing hyphen.']);
        }
        if (in_array($sub, config('provisioning.subdomain_blocklist'), true)) {
            throw ValidationException::withMessages(['subdomain' => "'{$sub}' is a reserved subdomain."]);
        }
        if (Tenant::withTrashed()->where('subdomain', $sub)->exists()
            && ! ProvisioningRun::where('subdomain', $sub)->whereNotNull('tenant_id')->exists()) {
            throw ValidationException::withMessages(['subdomain' => "Subdomain '{$sub}' is already taken."]);
        }
    }

    private function hoursBetween(string $start, string $end): float
    {
        $s = Carbon::parse($start);
        $e = Carbon::parse($end);
        if ($e->lessThanOrEqualTo($s)) {
            $e->addDay();
        }

        return round($s->diffInMinutes($e) / 60, 2);
    }

    /** @param array<string,mixed> $input */
    private function scrubInput(array $input): array
    {
        unset($input['admin_password'], $input['_token']);

        return $input;
    }

    /** @param array<string,mixed> $steps */
    private function lastStep(array $steps): ?string
    {
        return array_key_last($steps) ?: 'tenant';
    }
}
