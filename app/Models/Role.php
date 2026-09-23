<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions()
    {
        return $this->hasMany(RolePermission::class);
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
