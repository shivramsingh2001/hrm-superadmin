<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Custom role for the Super Admin Panel's own users (table sa_roles). Distinct
 * from App\Models\Role, which is the tenant-facing role/permission system.
 */
class SuperAdminRole extends Model
{
    protected $table = 'sa_roles';

    protected $guarded = [];

    protected $casts = [
        'is_system' => 'boolean',
    ];

    public function permissions()
    {
        return $this->hasMany(SuperAdminRolePermission::class, 'role_id');
    }

    public function superAdmins()
    {
        return $this->hasMany(SuperAdmin::class, 'role_id');
    }
}
