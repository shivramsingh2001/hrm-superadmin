<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Shared hrm_22_04.broadcast_notifications — the HRM app (hrm (3)) owns this
 * schema (real migrations live there); this is a plain, unscoped model over
 * the same table, the same pattern already used for Tenant/User/Role here.
 * This panel never migrates the shared DB itself.
 *
 * A row created here has origin=superadmin, origin_tenant_id=null,
 * created_by_super_admin_id set. See App\Services\Broadcast\
 * SuperAdminBroadcastAudienceResolver for the 5 audience modes and
 * App\Services\Broadcast\BroadcastDeliveryService for how delivery writes
 * directly into the HRM's own `notifications` table for tenant_user
 * recipients (no HTTP bridge — this panel already writes tenant-owned
 * tables directly elsewhere, e.g. ProvisioningService).
 */
class Broadcast extends Model
{
    protected $table = 'broadcast_notifications';

    protected $guarded = [];

    protected $casts = [
        'audience_filters' => 'array',
        'channels' => 'array',
        'scheduled_at' => 'datetime',
        'sent_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    public function recipients()
    {
        return $this->hasMany(BroadcastRecipient::class, 'broadcast_id');
    }

    public function creatorSuperAdmin()
    {
        return $this->belongsTo(SuperAdmin::class, 'created_by_super_admin_id');
    }

    public function creatorTenantUser()
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}
