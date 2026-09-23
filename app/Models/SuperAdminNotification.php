<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SuperAdminNotification extends Model
{
    protected $table = 'super_admin_notifications';

    protected $guarded = [];

    protected $casts = [
        'data' => 'array',
        'read_at' => 'datetime',
    ];

    public function scopeVisibleTo($q, int $superAdminId)
    {
        return $q->where(fn ($w) => $w->whereNull('super_admin_id')->orWhere('super_admin_id', $superAdminId));
    }
}
