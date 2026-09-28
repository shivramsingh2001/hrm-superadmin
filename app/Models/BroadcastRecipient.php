<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared hrm_22_04.broadcast_recipients — plain, unscoped model (see
 * App\Models\Broadcast's docblock). `tenant_id` is the real scoping
 * boundary for `recipient_type=tenant_user` rows (set to that user's own
 * tenant_id at snapshot time, since one superadmin broadcast can span many
 * tenants). For `recipient_type=super_admin` rows, `tenant_id` is stored as
 * `0` — a documented sentinel meaning "not tenant-scoped" (the column is
 * NOT NULL; super admins aren't tenant data). Nothing ever scopes a
 * super-admin-recipient query by tenant_id — those queries always filter on
 * `recipient_type='super_admin' AND super_admin_id=X` instead, so the
 * sentinel is inert, never a leak vector.
 */
class BroadcastRecipient extends Model
{
    protected $guarded = [];

    protected $casts = [
        'channel_status' => 'array',
        'delivered_at' => 'datetime',
        'read_at' => 'datetime',
        'action_clicked_at' => 'datetime',
    ];

    public function broadcast()
    {
        return $this->belongsTo(Broadcast::class, 'broadcast_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function superAdmin()
    {
        return $this->belongsTo(SuperAdmin::class, 'super_admin_id');
    }

    public function tenant()
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }
}
