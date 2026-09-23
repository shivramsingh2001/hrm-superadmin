<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureRegistry extends Model
{
    protected $table = 'feature_registry';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
        'deprecated_at' => 'datetime',
    ];

    public function isDeprecated(): bool
    {
        return $this->deprecated_at !== null || ! $this->is_active;
    }
}
