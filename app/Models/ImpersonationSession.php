<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ImpersonationSession extends Model
{
    protected $table = 'impersonation_sessions';

    protected $guarded = [];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }

    public function tenantUser()
    {
        return $this->belongsTo(User::class, 'tenant_user_id');
    }

    public function scopeLive(Builder $q): Builder
    {
        return $q->whereNull('ended_at')->where('expires_at', '>', now());
    }

    public function isLive(): bool
    {
        return $this->ended_at === null && $this->expires_at && $this->expires_at->isFuture();
    }
}
