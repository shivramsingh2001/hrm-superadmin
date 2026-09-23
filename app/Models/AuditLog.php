<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Immutable audit trail (hrm_22_04.audit_logs). Insert-only — never updated or
 * deleted from the app. Has created_at but no updated_at.
 */
class AuditLog extends Model
{
    protected $table = 'audit_logs';

    protected $guarded = [];

    public $timestamps = false;

    protected $casts = [
        'old_values' => 'array',
        'new_values' => 'array',
        'created_at' => 'datetime',
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }
}
