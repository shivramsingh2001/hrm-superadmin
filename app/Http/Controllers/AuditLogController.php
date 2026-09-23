<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\SuperAdmin;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request)
    {
        $logs = $this->filtered($request)->paginate(40)->withQueryString();

        return view('audit-logs.index', [
            'logs' => $logs,
            'admins' => SuperAdmin::orderBy('name')->pluck('name', 'id'),
            'tenants' => Tenant::orderBy('company_name')->pluck('company_name', 'id'),
            'actions' => AuditLog::query()->select('action')->distinct()->orderBy('action')->pluck('action'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        $rows = $this->filtered($request)->limit(10000)->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['id', 'created_at', 'actor_type', 'actor_id', 'tenant_id', 'action', 'entity_type', 'entity_id', 'ip_address', 'old_values', 'new_values']);
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->id, $r->created_at, $r->actor_type, $r->actor_id, $r->tenant_id,
                    $r->action, $r->entity_type, $r->entity_id, $r->ip_address,
                    json_encode($r->old_values), json_encode($r->new_values),
                ]);
            }
            fclose($out);
        }, 'audit-logs-' . now()->format('Ymd-His') . '.csv', ['Content-Type' => 'text/csv']);
    }

    private function filtered(Request $request)
    {
        return AuditLog::query()
            ->when($request->input('actor_id'), fn ($w, $v) => $w->where('actor_id', $v)->where('actor_type', 'super_admin'))
            ->when($request->input('tenant_id'), fn ($w, $v) => $w->where('tenant_id', $v))
            ->when($request->input('action'), fn ($w, $v) => $w->where('action', $v))
            ->when($request->input('entity_type'), fn ($w, $v) => $w->where('entity_type', $v))
            ->when($request->input('from'), fn ($w, $v) => $w->whereDate('created_at', '>=', $v))
            ->when($request->input('to'), fn ($w, $v) => $w->whereDate('created_at', '<=', $v))
            ->orderByDesc('created_at');
    }
}
