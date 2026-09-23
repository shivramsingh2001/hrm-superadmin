<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class TenantSubscription extends Model
{
    protected $table = 'tenant_subscriptions';

    protected $guarded = [];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'trial_ends_at' => 'datetime',
    ];

    /** Always read as an array; tolerate legacy double-encoded rows. */
    public function getFeaturesSnapshotAttribute($value): array
    {
        $decoded = is_array($value) ? $value : json_decode($value ?? '[]', true);
        if (is_string($decoded)) {
            $decoded = json_decode($decoded, true);
        }

        return is_array($decoded) ? $decoded : [];
    }

    /** Always store as clean JSON whether given an array or a JSON string. */
    public function setFeaturesSnapshotAttribute($value): void
    {
        if (is_string($value)) {
            $value = json_decode($value, true) ?: [];
        }
        $this->attributes['features_snapshot'] = json_encode($value ?: []);
    }

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function scopeActive(Builder $q): Builder
    {
        return $q->whereIn('status', ['active', 'trial'])
            ->where(fn ($w) => $w->whereNull('end_date')->orWhereDate('end_date', '>=', now()->toDateString()))
            ->orderByDesc('start_date')
            ->orderByDesc('id');
    }
}
