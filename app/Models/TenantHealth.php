<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantHealth extends Model
{
    protected $table = 'sa_tenant_health';

    protected $primaryKey = 'tenant_id';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    protected $casts = [
        'snapshot_date' => 'date',
        'last_activity_at' => 'datetime',
        'seat_utilisation' => 'decimal:2',
        'is_stale' => 'boolean',
        'updated_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
