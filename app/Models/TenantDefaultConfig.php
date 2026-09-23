<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantDefaultConfig extends Model
{
    protected $table = 'tenant_default_config';

    protected $guarded = [];

    protected $casts = [
        'working_days' => 'array',
        'working_hours_per_day' => 'decimal:2',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
