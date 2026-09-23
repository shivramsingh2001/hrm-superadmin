<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Shared hrm_22_04.tenants. The HRM app owns this schema — read/write existing
 * columns only.
 */
class Tenant extends Model
{
    use SoftDeletes;

    protected $table = 'tenants';

    protected $guarded = [];

    protected $casts = [
        'settings' => 'array',
        'default_weekoff_days' => 'array',
        'trial_ends_at' => 'datetime',
        'deletion_requested_at' => 'datetime',
        'late_halfday_enabled' => 'boolean',
        'custom_shifts_enabled' => 'boolean',
        'field_tracking_enabled' => 'boolean',
    ];

    public function company()
    {
        return $this->hasOne(Company::class);
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class);
    }

    public function activeSubscription()
    {
        return $this->subscriptions()->getQuery()->active()->first();
    }

    public function featureOverrides()
    {
        return $this->hasMany(TenantFeatureOverride::class);
    }

    public function planModel()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'subscription_plan_id');
    }
}
