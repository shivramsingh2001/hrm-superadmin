<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

/**
 * Platform staff. Maps to the shared hrm_22_04.super_admins table.
 * Roles: superadmin | support | billing.
 */
class SuperAdmin extends Authenticatable
{
    protected $table = 'super_admins';

    protected $guarded = [];

    protected $hidden = ['password', 'totp_secret'];

    protected $casts = [
        'allowed_ips' => 'array',
        'is_active' => 'boolean',
        'totp_secret' => 'encrypted',
        'totp_enabled_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function hasTotp(): bool
    {
        return $this->totp_enabled_at !== null && ! empty($this->totp_secret);
    }

    /** Per-account IP allowlist (super_admins.allowed_ips JSON), if set. */
    public function ipAllowed(string $ip): bool
    {
        $list = $this->allowed_ips;
        if (empty($list) || ! is_array($list)) {
            return true;
        }

        return in_array($ip, $list, true);
    }

    public function isSuperadmin(): bool
    {
        return $this->role === 'superadmin';
    }

    public function isSupport(): bool
    {
        return $this->role === 'support';
    }

    public function isBilling(): bool
    {
        return $this->role === 'billing';
    }

    public function panelRole()
    {
        return $this->belongsTo(SuperAdminRole::class, 'role_id');
    }

    /**
     * Phase 1: recorded and assignable, but not yet consulted by any route or
     * view — the base `role` ENUM (superadmin/support/billing) remains the
     * only thing actually enforced today. Foundation for a later phase.
     * (Deliberately not named `can()` — that's reserved by Laravel's
     * Authorizable contract and shouldn't be overridden here.)
     */
    public function hasPanelPermission(string $module, string $action): bool
    {
        if ($this->isSuperadmin()) {
            return true;
        }

        return $this->panelRole?->permissions()
            ->where('module', $module)->where('action', $action)->exists() ?? false;
    }
}
