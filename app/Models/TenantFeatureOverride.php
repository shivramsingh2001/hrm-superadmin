<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantFeatureOverride extends Model
{
    protected $table = 'tenant_feature_overrides';

    protected $guarded = [];

    protected $casts = [
        'is_enabled' => 'boolean',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
