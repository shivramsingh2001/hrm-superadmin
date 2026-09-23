<?php

namespace App\Services;

use App\Models\FeatureRegistry;
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

        $overrides = TenantFeatureOverride::where('tenant_id', $tenantId)
            ->get()->keyBy('feature_key');

        $out = [];
        foreach (config('features') as $key => $meta) {
            $planValue = array_key_exists($key, $snapshot)
                ? (bool) $snapshot[$key]
                : (bool) ($meta['default'] ?? false);

            $ov = $overrides->get($key);
            $effective = $ov ? (bool) $ov->is_enabled : $planValue;
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
    public function bust(int $tenantId): void
    {
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
