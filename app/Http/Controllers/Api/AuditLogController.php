<?php

namespace App\Http\Controllers\Api;

use App\Models\AuditLog;
use Illuminate\Http\Request;

class AuditLogController extends ApiController
{
    public function index(Request $request)
    {
        $q = AuditLog::query()
            ->when($request->input('tenant_id'), fn ($w, $v) => $w->where('tenant_id', $v))
            ->when($request->input('actor_id'), fn ($w, $v) => $w->where('actor_id', $v))
            ->when($request->input('action'), fn ($w, $v) => $w->where('action', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');

        return $this->ok($q->paginate(min(200, (int) $request->input('per_page', 50))));
    }
}
