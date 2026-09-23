<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TenantNote extends Model
{
    protected $table = 'sa_tenant_notes';

    protected $guarded = [];

    public function author()
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }
}
