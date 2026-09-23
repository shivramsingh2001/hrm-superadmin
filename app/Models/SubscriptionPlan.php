<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SubscriptionPlan extends Model
{
    protected $table = 'subscription_plans';

    protected $guarded = [];

    protected $casts = [
        'features' => 'array',
        'price' => 'decimal:2',
        'price_per_employee' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    public function subscriptions()
    {
        return $this->hasMany(TenantSubscription::class, 'plan_id');
    }

    /**
     * Best-effort normalised monthly price for MRR roll-ups. Per-employee plans
     * are estimated against a supplied headcount (falls back to free_employees).
     */
    public function monthlyPrice(?int $employees = null): float
    {
        $cycleDivisor = match ($this->billing_cycle) {
            'yearly' => 12,
            'quarterly' => 3,
            'daily' => 1 / 30,
            default => 1, // monthly
        };

        if ($this->pricing_type === 'fixed') {
            return round((float) $this->price / $cycleDivisor, 2);
        }

        $per = (float) ($this->price_per_employee ?? 0);
        $billable = max(0, ($employees ?? $this->free_employees ?? 0) - (int) ($this->free_employees ?? 0));

        if ($this->pricing_type === 'per_employee_per_day') {
            return round($per * $billable * 30, 2);
        }

        // per_employee_per_month
        return round(($per * $billable) / $cycleDivisor, 2);
    }
}
