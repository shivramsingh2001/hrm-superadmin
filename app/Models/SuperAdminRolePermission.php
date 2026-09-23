<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminRolePermission extends Model
{
    protected $table = 'sa_role_permissions';

    protected $guarded = [];

    public function role()
    {
        return $this->belongsTo(SuperAdminRole::class, 'role_id');
    }
}
