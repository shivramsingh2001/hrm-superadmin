<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlanVersion extends Model
{
    protected $table = 'sa_plan_versions';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'features' => 'array',
        'created_at' => 'datetime',
    ];

    public function plan()
    {
        return $this->belongsTo(SubscriptionPlan::class, 'plan_id');
    }
}
