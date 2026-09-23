<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProvisioningRun extends Model
{
    protected $table = 'sa_provisioning_runs';

    protected $guarded = [];

    protected $casts = [
        'steps' => 'array',
        'input' => 'array',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function inquiry()
    {
        return $this->belongsTo(Inquiry::class);
    }
}
