<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Inquiry extends Model
{
    protected $table = 'inquiries';

    protected $guarded = [];

    protected $casts = [
        'last_contacted_at' => 'datetime',
    ];

    public const OPEN_STATUSES = ['new', 'contacted', 'negotiating', 'payment_sent', 'paid'];
}
