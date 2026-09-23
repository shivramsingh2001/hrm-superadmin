<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RolePermission extends Model
{
    protected $table = 'role_permissions';

    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(Role::class);
    }
}
