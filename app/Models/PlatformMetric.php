<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PlatformMetric extends Model
{
    protected $table = 'sa_platform_metrics';

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'metric_date' => 'date',
        'mrr' => 'decimal:2',
        'arr' => 'decimal:2',
        'funnel' => 'array',
        'new_tenants_by_month' => 'array',
        'created_at' => 'datetime',
    ];
}
