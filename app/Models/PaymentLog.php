<?php

namespace App\Models;

use App\Casts\TolerantEncrypted;
use Illuminate\Database\Eloquent\Model;

class PaymentLog extends Model
{
    protected $table = 'payment_logs';

    protected $guarded = [];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'reference_number' => TolerantEncrypted::class,
    ];

    public function tenant()
    {
        return $this->belongsTo(Tenant::class);
    }

    public function collectedBy()
    {
        return $this->belongsTo(SuperAdmin::class, 'collected_by');
    }
}
