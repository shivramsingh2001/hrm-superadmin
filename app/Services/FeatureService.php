<?php

namespace App\Services;

use App\Models\FeatureRegistry;
use App\Models\SubscriptionPlan;
use App\Models\TenantFeatureOverride;
use App\Models\TenantSubscription;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Two-layer feature resolution (SRS §9.1):
 *   tenant override  ->  active subscription snapshot  ->  config default  ->  false
 * Deprecated registry keys resolve as disabled, following replaced_by once.
 *
 * Milestone 1 only reads/manages state for the panel; the HRM app is not yet
 * wired to call this.
 */
class FeatureService
{
    private const TTL = 300;

    public function enabled(int $tenantId, string $key): bool
    {
        return (bool) Cache::remember(
            "feat:{$tenantId}:{$key}",
            self::TTL,
            fn () => $this->resolve($tenantId, $key, 0)
        );
    }

    private function resolve(int $tenantId, string $key, int $depth): bool
    {
        if ($depth > 3) {
            return false;
        }

        $reg = FeatureRegistry::find($key);
        if ($reg && $reg->isDeprecated()) {
            return $reg->replaced_by
                ? $this->resolve($tenantId, $reg->replaced_by, $depth + 1)
                : false;
        }

        $override = TenantFeatureOverride::where('tenant_id', $tenantId)
            ->where('feature_key', $key)->first();
        if ($override) {
            return (bool) $override->is_enabled;
        }

        $sub = TenantSubscription::query()->where('tenant_id', $tenantId)->active()->first();
        if ($sub && array_key_exists($key, $sub->features_snapshot ?? [])) {
            return (bool) $sub->features_snapshot[$key];
        }

        return (bool) config("features.{$key}.default", false);
    }

    /**
     * Per-key breakdown for the tenant-detail Features tab.
     *
     * @return array<string, array{name:string, description:string, module:string,
     *   plan_value:bool, effective:bool, source:string, override_reason:?string}>
     */
    public function matrixForTenant(int $tenantId): array
    {
        $sub = TenantSubscription::query()->where('tenant_id', $tenantId)->active()->first();
        $snapshot = $sub->features_snapshot ?? [];
        $plan = $sub ? (SubscriptionPlan::find($sub->plan_id)?->features ?? []) : [];

        $overrides = TenantFeatureOverride::where('tenant_id', $tenantId)
            ->get()->keyBy('feature_key');

        $out = [];
        foreach (config('features') as $key => $meta) {
            // "Plan default" column = what the plan itself offers; the snapshot holds
            // the tenant's actual feature set (kept in sync by syncSnapshot()).
            $planValue = array_key_exists($key, $plan)
                ? (bool) $plan[$key]
                : (bool) ($meta['default'] ?? false);

            $ov = $overrides->get($key);
            $effective = $ov
                ? (bool) $ov->is_enabled
                : (array_key_exists($key, $snapshot) ? (bool) $snapshot[$key] : (bool) ($meta['default'] ?? false));
            $source = $ov ? 'override' : (array_key_exists($key, $snapshot) ? 'plan' : 'default');

            $out[$key] = [
                'name' => $meta['name'],
                'description' => $meta['description'],
                'module' => $meta['module'],
                'plan_value' => $planValue,
                'effective' => $effective,
                'source' => $source,
                'override_reason' => $ov?->reason,
            ];
        }

        return $out;
    }

    /**
     * Drop every cached flag for a tenant (call after any override/subscription
     * write) and ask the HRM app to do the same so the change is visible there
     * immediately rather than after its own TTL.
     */
    /**
     * Keep the active subscription's features_snapshot equal to the tenant's
     * effective feature set: every registered key present, and every override
     * written into it. Overrides stay as the "differs from plan" record; the
     * snapshot is the one row that always shows what the tenant actually has.
     * Called from bust(), i.e. after every override/subscription write.
     */
    public function syncSnapshot(int $tenantId): void
    {
        $sub = TenantSubscription::query()->where('tenant_id', $tenantId)->active()->first();
        if (! $sub) {
            return;
        }

        $snapshot = $sub->features_snapshot ?? [];
        $synced = [];
        foreach (config('features') as $key => $meta) {
            $synced[$key] = array_key_exists($key, $snapshot)
                ? (bool) $snapshot[$key]
                : (bool) ($meta['default'] ?? false);
        }
        foreach (TenantFeatureOverride::where('tenant_id', $tenantId)->get() as $ov) {
            if (array_key_exists($ov->feature_key, $synced)) {
                $synced[$ov->feature_key] = (bool) $ov->is_enabled;
            }
        }

        if ($synced !== $snapshot) {
            $sub->update(['features_snapshot' => $synced]);
        }
    }

    /**
     * An override was cleared ("fall back to plan"): put the plan's own value
     * for that key back into the snapshot. Call before bust().
     */
    public function resetToPlan(int $tenantId, string $key): void
    {
        $this->writeSnapshot($tenantId, [$key => $this->planValue($tenantId, $key)]);
    }

    /** What the tenant's current plan itself offers for a key (plan JSON, else config default). */
    public function planValue(int $tenantId, string $key): bool
    {
        $sub = TenantSubscription::query()->where('tenant_id', $tenantId)->active()->first();
        $plan = $sub ? (SubscriptionPlan::find($sub->plan_id)?->features ?? []) : [];

        return array_key_exists($key, $plan)
            ? (bool) $plan[$key]
            : (bool) config("features.{$key}.default", false);
    }

    /** Merge key => bool values into the active subscription's snapshot. */
    public function writeSnapshot(int $tenantId, array $values): void
    {
        $sub = TenantSubscription::query()->where('tenant_id', $tenantId)->active()->first();
        if (! $sub || ! $values) {
            return;
        }

        $snapshot = $sub->features_snapshot ?? [];
        foreach ($values as $key => $on) {
            $snapshot[$key] = (bool) $on;
        }

        $sub->update(['features_snapshot' => $snapshot]);
    }

    public function bust(int $tenantId): void
    {
        $this->syncSnapshot($tenantId);

        foreach (array_keys(config('features')) as $key) {
            Cache::forget("feat:{$tenantId}:{$key}");
        }

        $token = config('platform.internal_token');
        if (! $token) {
            return; // no shared secret configured — HRM will pick it up on TTL
        }

        try {
            Http::timeout(3)
                ->withHeaders(['X-Internal-Token' => $token])
                ->post(rtrim(config('platform.hrm_internal_url'), '/') . '/internal/superadmin/feature-cache/bust', [
                    'tenant_id' => $tenantId,
                ]);
        } catch (\Throwable $e) {
            Log::warning("cross-app feature bust failed for tenant {$tenantId}: " . $e->getMessage());
        }
    }
}
