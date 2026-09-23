<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Thin cross-app caller for the HRM's /internal/superadmin/* endpoints.
 * No-ops when no shared secret is configured (HRM then relies on its TTL).
 */
class PlatformClient
{
    public static function bust(array $payload): void
    {
        $token = config('platform.internal_token');
        if (! $token) {
            return;
        }

        try {
            Http::timeout(3)
                ->withHeaders(['X-Internal-Token' => $token])
                ->post(rtrim(config('platform.hrm_internal_url'), '/') . '/internal/superadmin/feature-cache/bust', $payload);
        } catch (\Throwable $e) {
            Log::warning('PlatformClient bust failed: ' . $e->getMessage() . ' payload=' . json_encode($payload));
        }
    }

    public static function bustRole(int $roleId): void
    {
        self::bust(['role_id' => $roleId]);
    }
}
