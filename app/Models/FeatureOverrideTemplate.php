<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FeatureOverrideTemplate extends Model
{
    protected $table = 'sa_feature_override_templates';

    protected $guarded = [];

    protected $casts = [
        'map' => 'array',
    ];
}
