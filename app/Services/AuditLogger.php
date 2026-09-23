<?php

namespace App\Services;

use App\Models\AuditLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Writes one immutable audit_logs row per super-admin mutation (SRS §4.8) plus
 * a tamper-evident HMAC in the side table sa_audit_signatures (Phase 7.4).
 */
class AuditLogger
{
    public static function record(
        string $action,
        ?string $entityType = null,
        int|string|null $entityId = null,
        ?array $old = null,
        ?array $new = null,
        ?int $tenantId = null,
    ): void {
        $actor = Auth::user();
        $req = request();

        $row = AuditLog::create([
            'actor_type' => 'super_admin',
            'actor_id' => $actor?->getKey(),
            'impersonating_user_id' => null,
            'tenant_id' => $tenantId,
            'action' => $action,
            'entity_type' => $entityType,
            'entity_id' => is_numeric($entityId) ? (int) $entityId : null,
            'old_values' => $old,
            'new_values' => $new,
            'ip_address' => $req?->ip(),
            'user_agent' => $req?->userAgent(),
            'created_at' => now(),
        ]);

        self::sign($row);
    }

    /**
     * Canonical string over the immutable fields, always read from the raw DB
     * row so the write-time signature matches what `audit:verify` recomputes.
     */
    public static function canonical(object $r): string
    {
        return implode('|', [
            $r->id,
            $r->actor_type,
            $r->actor_id ?? '',
            $r->impersonating_user_id ?? '',
            $r->tenant_id ?? '',
            $r->action,
            $r->entity_type ?? '',
            $r->entity_id ?? '',
            $r->old_values ?? '',
            $r->new_values ?? '',
            (string) $r->created_at,
        ]);
    }

    public static function hmac(object $rawRow): string
    {
        return hash_hmac('sha256', self::canonical($rawRow), (string) config('platform.audit_hmac_key'));
    }

    public static function sign(object $model): void
    {
        if (! config('platform.audit_hmac_key')) {
            return;
        }
        $raw = DB::table('audit_logs')->find($model->id);
        if (! $raw) {
            return;
        }
        DB::table('sa_audit_signatures')->updateOrInsert(
            ['audit_log_id' => $raw->id],
            ['hmac' => self::hmac($raw), 'created_at' => now()],
        );
    }
}
