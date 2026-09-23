<?php

namespace App\Services;

use App\Models\Company;
use App\Models\PaymentLog;
use App\Models\SubscriptionPlan;
use App\Models\Tenant;
use App\Models\TenantSubscription;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Post-provisioning tenant operations. Every method audits and (where relevant)
 * busts the feature cache. Callable from controllers and scheduled commands
 * (jobs pass actorId = null so the audit actor is 'system').
 */
class TenantLifecycleService
{
    public function __construct(private FeatureService $features)
    {
    }

    /**
     * Move a tenant to a new plan. Ends the current subscription and opens a new
     * one with a fresh feature snapshot. `effectiveFrom` null = now.
     */
    public function changePlan(Tenant $tenant, SubscriptionPlan $plan, ?Carbon $effectiveFrom = null, ?string $note = null): TenantSubscription
    {
        return DB::transaction(function () use ($tenant, $plan, $effectiveFrom, $note) {
            $current = TenantSubscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial'])->orderByDesc('id')->first();

            $start = $effectiveFrom ?: now();

            if ($current) {
                $current->update([
                    'end_date' => $start->copy()->subDay()->toDateString(),
                    'status' => 'expired',
                ]);
            }

            $new = TenantSubscription::create([
                'tenant_id' => $tenant->id,
                'plan_id' => $plan->id,
                'features_snapshot' => $plan->features ?? [],
                'start_date' => $start->toDateString(),
                'end_date' => null,
                'trial_ends_at' => null,
                'status' => 'active',
                'created_by' => Auth::id(),
            ]);

            $tenant->update([
                'subscription_plan' => $plan->slug,
                'subscription_plan_id' => $plan->id,
                'max_employees' => $tenant->max_employees ?: ($plan->max_employees ?: 100),
            ]);

            $this->features->bust($tenant->id);

            AuditLogger::record('tenant.plan_changed', 'tenants', $tenant->id,
                ['plan_id' => $current?->plan_id],
                ['plan_id' => $plan->id, 'effective_from' => $start->toDateString(), 'note' => $note],
                $tenant->id);

            return $new;
        });
    }

    /** Adjust the seat limit. `override` writes tenant_subscriptions.max_employees_override. */
    public function updateLimits(Tenant $tenant, ?int $maxEmployees, ?int $override): void
    {
        $old = ['max_employees' => $tenant->max_employees];
        if ($maxEmployees !== null) {
            $tenant->update(['max_employees' => $maxEmployees]);
        }
        if ($override !== null || $override === 0) {
            TenantSubscription::where('tenant_id', $tenant->id)
                ->whereIn('status', ['active', 'trial'])->orderByDesc('id')
                ->limit(1)->update(['max_employees_override' => $override ?: null]);
        }

        AuditLogger::record('tenant.limits_updated', 'tenants', $tenant->id, $old,
            ['max_employees' => $maxEmployees, 'max_employees_override' => $override], $tenant->id);
    }

    /** Set the location-tracking (GPS) add-on's master switch + purchased seat count. */
    public function updateTracking(Tenant $tenant, bool $enabled, int $seats): void
    {
        $old = ['field_tracking_enabled' => $tenant->field_tracking_enabled, 'field_tracking_seats' => $tenant->field_tracking_seats];
        $tenant->update(['field_tracking_enabled' => $enabled, 'field_tracking_seats' => $seats]);

        AuditLogger::record('tenant.tracking_updated', 'tenants', $tenant->id, $old,
            ['field_tracking_enabled' => $enabled, 'field_tracking_seats' => $seats], $tenant->id);
    }

    /** Set the current subscription's end date directly, without recording a payment. */
    public function setDuration(Tenant $tenant, Carbon $endDate): void
    {
        $sub = TenantSubscription::where('tenant_id', $tenant->id)->orderByDesc('id')->first();
        $old = ['end_date' => (string) $sub?->end_date];
        $sub?->update(['end_date' => $endDate->toDateString()]);

        AuditLogger::record('tenant.duration_set', 'tenants', $tenant->id, $old,
            ['end_date' => $endDate->toDateString()], $tenant->id);
    }

    public function suspend(Tenant $tenant, string $reasonCode, ?string $note = null, ?int $actorId = null): void
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'suspended']);

        AuditLogger::record('tenant.suspended', 'tenants', $tenant->id, $old, [
            'status' => 'suspended',
            'reason_code' => $reasonCode,
            'reason' => config("lifecycle.suspension_reasons.$reasonCode", $reasonCode),
            'note' => $note,
            'by' => $actorId ? "sa:$actorId" : 'system',
        ], $tenant->id);

        NotificationService::broadcast('tenant_suspended', 'Tenant suspended: ' . $tenant->company_name,
            config("lifecycle.suspension_reasons.$reasonCode", $reasonCode) . ($note ? " — {$note}" : ''),
            ['tenant_id' => $tenant->id]);
    }

    public function activate(Tenant $tenant, ?Carbon $extendEndDate = null): void
    {
        $old = ['status' => $tenant->status];
        $tenant->update(['status' => 'active']);

        if ($extendEndDate) {
            TenantSubscription::where('tenant_id', $tenant->id)
                ->orderByDesc('id')->limit(1)
                ->update(['end_date' => $extendEndDate->toDateString(), 'status' => 'active']);
        }

        AuditLogger::record('tenant.activated', 'tenants', $tenant->id, $old,
            ['status' => 'active', 'extended_to' => $extendEndDate?->toDateString()], $tenant->id);
    }

    /** Record a renewal payment and push the active subscription's end_date out. */
    public function renew(Tenant $tenant, array $payment, Carbon $newEndDate): PaymentLog
    {
        return DB::transaction(function () use ($tenant, $payment, $newEndDate) {
            $log = PaymentLog::create($payment + ['tenant_id' => $tenant->id, 'collected_by' => Auth::id()]);

            $sub = TenantSubscription::where('tenant_id', $tenant->id)
                ->orderByDesc('id')->first();
            if ($sub) {
                $sub->update([
                    'end_date' => $newEndDate->toDateString(),
                    'status' => 'active',
                    'payment_log_id' => $log->id,
                ]);
            }
            if ($tenant->status !== 'active') {
                $tenant->update(['status' => 'active']);
            }

            AuditLogger::record('tenant.renewed', 'tenants', $tenant->id, null,
                ['payment_log_id' => $log->id, 'end_date' => $newEndDate->toDateString()], $tenant->id);

            return $log;
        });
    }

    public function extendTrial(Tenant $tenant, Carbon $newEnd): void
    {
        $old = ['trial_ends_at' => (string) $tenant->trial_ends_at];
        $tenant->update(['trial_ends_at' => $newEnd]);
        TenantSubscription::where('tenant_id', $tenant->id)->where('status', 'trial')
            ->update(['trial_ends_at' => $newEnd]);

        AuditLogger::record('tenant.trial_extended', 'tenants', $tenant->id, $old,
            ['trial_ends_at' => $newEnd->toDateTimeString()], $tenant->id);
    }

    /** Convert a trial to a paid subscription (records a payment). */
    public function convertTrial(Tenant $tenant, array $payment, Carbon $endDate): PaymentLog
    {
        return DB::transaction(function () use ($tenant, $payment, $endDate) {
            $log = PaymentLog::create($payment + ['tenant_id' => $tenant->id, 'collected_by' => Auth::id()]);

            TenantSubscription::where('tenant_id', $tenant->id)->where('status', 'trial')
                ->update(['status' => 'active', 'trial_ends_at' => null, 'end_date' => $endDate->toDateString(), 'payment_log_id' => $log->id]);

            $tenant->update(['status' => 'active', 'trial_ends_at' => null]);

            AuditLogger::record('tenant.trial_converted', 'tenants', $tenant->id, null,
                ['payment_log_id' => $log->id, 'end_date' => $endDate->toDateString()], $tenant->id);

            return $log;
        });
    }

    public function requestDeletion(Tenant $tenant, string $reason): void
    {
        $tenant->update([
            'deletion_requested_at' => now(),
            'deletion_requested_by' => Auth::id(),
        ]);
        AuditLogger::record('tenant.deletion_requested', 'tenants', $tenant->id, null,
            ['reason' => $reason, 'executes_after' => now()->addDays((int) config('lifecycle.deletion_grace_days'))->toDateString()],
            $tenant->id);
        NotificationService::broadcast('tenant_deletion_requested', 'Deletion requested: ' . $tenant->company_name,
            "Hard-delete in " . config('lifecycle.deletion_grace_days') . " days unless cancelled. Reason: {$reason}",
            ['tenant_id' => $tenant->id]);
    }

    public function cancelDeletion(Tenant $tenant): void
    {
        $tenant->update(['deletion_requested_at' => null, 'deletion_requested_by' => null]);
        AuditLogger::record('tenant.deletion_cancelled', 'tenants', $tenant->id, null, null, $tenant->id);
    }

    /**
     * Hard-step: soft-delete the tenant, anonymise PII, block logins. Keeps
     * audit_logs. Called by the scheduled executor after the grace period.
     */
    public function executeDeletion(Tenant $tenant): void
    {
        DB::transaction(function () use ($tenant) {
            $tok = config('lifecycle.anonymise_token');

            User::where('tenant_id', $tenant->id)->update([
                'name' => $tok,
                'email' => DB::raw("CONCAT('{$tok}+', id, '@invalid.local')"),
                'contact' => null,
                'password' => bcrypt(str()->random(40)),
                'status' => '0',
                'remember_token' => null,
                'fcm_tokens' => null,
            ]);

            Company::where('tenant_id', $tenant->id)->update([
                'email' => null, 'phone' => null, 'gstnumber' => null, 'pannumber' => null,
                'address' => null, 'status' => 0,
            ]);

            $tenant->update([
                'status' => 'inactive',
                'email' => "{$tok}+{$tenant->id}@invalid.local",
                'phone' => null,
                'custom_domain' => null,
            ]);
            $tenant->delete(); // soft delete

            AuditLogger::record('tenant.deleted', 'tenants', $tenant->id, null,
                ['anonymised' => true, 'soft_deleted_at' => now()->toDateTimeString()], $tenant->id);
        });
    }
}
