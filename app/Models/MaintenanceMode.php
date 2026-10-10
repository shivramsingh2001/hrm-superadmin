<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Shared hrm_22_04.maintenance_modes — one row, the platform-wide maintenance
 * switch. The HRM app owns the table (migration 2026_10_10_000001 there),
 * serves it at GET /api/maintenance and enforces it with CheckMaintenanceMode.
 *
 * This panel runs in UTC but the HRM reads every timestamp in its own zone
 * (platform.hrm_timezone), so start/end and created/updated are written as
 * HRM wall-clock time — see freshTimestamp() and the helpers below.
 */
class MaintenanceMode extends Model
{
    protected $table = 'maintenance_modes';

    protected $fillable = [
        'is_enabled', 'title', 'message', 'start_time', 'end_time',
        'allowed_ips', 'allowed_users', 'enabled_by',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'allowed_ips' => 'array',
        'allowed_users' => 'array',
    ];

    public static function tz(): string
    {
        return (string) config('platform.hrm_timezone', 'Asia/Kolkata');
    }

    public static function row(): self
    {
        return static::query()->orderBy('id')->first()
            ?? static::create([
                'is_enabled' => false,
                'title' => 'Under Maintenance',
                'message' => 'We are currently performing scheduled maintenance. Please check back soon.',
                'allowed_ips' => [],
                'allowed_users' => [],
            ]);
    }

    public function freshTimestamp()
    {
        return Carbon::now(self::tz());
    }

    public function startAt(): ?Carbon
    {
        return $this->start_time ? Carbon::parse($this->start_time, self::tz()) : null;
    }

    public function endAt(): ?Carbon
    {
        return $this->end_time ? Carbon::parse($this->end_time, self::tz()) : null;
    }

    /** off | scheduled | live | ended — same rule as the HRM's isActive(). */
    public function state(): string
    {
        if (! $this->is_enabled) {
            return 'off';
        }
        $now = Carbon::now(self::tz());
        if ($this->startAt() && $now->lt($this->startAt())) {
            return 'scheduled';
        }
        if ($this->endAt() && $now->gte($this->endAt())) {
            return 'ended';
        }

        return 'live';
    }

    public function enabledBy()
    {
        return $this->belongsTo(SuperAdmin::class, 'enabled_by');
    }
}
