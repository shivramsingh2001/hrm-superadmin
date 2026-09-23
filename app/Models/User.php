<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared hrm_22_04.users — read-only in the Super Admin Panel (tenant "Users"
 * tab, impersonation target later). Never authenticate against this model here.
 */
class User extends Model
{
    protected $table = 'users';

    protected $guarded = [];

    protected $hidden = ['password', 'remember_token'];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
