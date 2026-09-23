<?php

namespace App\Http\Controllers;

use App\Models\ImpersonationSession;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Phase 6 — the super admin acts as a tenant user for support. A session row is
 * the bearer; the HRM validates it against the shared table (no signing needed),
 * logs the user in, shows a banner, and calls back on "End session".
 */
class ImpersonationController extends Controller
{
    public function index()
    {
        $sessions = ImpersonationSession::with(['tenant', 'superAdmin', 'tenantUser'])
            ->orderByDesc('started_at')->paginate(25);

        // One-click "impersonate the admin" per row — the earliest active admin-role user per tenant.
        $primaryAdmins = User::where('role', 'admin')->where('status', '1')
            ->whereIn('tenant_id', $sessions->pluck('tenant_id')->unique())
            ->orderBy('id')->get(['id', 'tenant_id', 'name'])
            ->groupBy('tenant_id')->map(fn ($g) => $g->first());

        return view('impersonation.index', compact('sessions', 'primaryAdmins'));
    }

    public function start(Request $request, Tenant $tenant)
    {
        $data = $request->validate([
            'tenant_user_id' => ['required', 'integer'],
        ]);

        $target = User::where('id', $data['tenant_user_id'])->where('tenant_id', $tenant->id)->first();
        if (! $target) {
            return back()->with('error', 'That user is not in this tenant.');
        }
        if ((string) $target->status !== '1') {
            return back()->with('error', 'That user is inactive.');
        }

        // Close any live session this admin already has.
        ImpersonationSession::live()->where('super_admin_id', $request->user()->id)
            ->update(['ended_at' => now(), 'end_reason' => 'admin_logout']);

        $ttl = (int) config('platform.impersonation_ttl_minutes', 60);
        $token = Str::random(64);

        $session = ImpersonationSession::create([
            'super_admin_id' => $request->user()->id,
            'tenant_user_id' => $target->id,
            'tenant_id' => $tenant->id,
            'session_token' => $token,
            'started_at' => now(),
            'expires_at' => now()->addMinutes($ttl),
            'ip_address' => $request->ip(),
        ]);

        AuditLogger::record('impersonation.started', 'impersonation_sessions', $session->id, null, [
            'tenant_user_id' => $target->id,
            'user_name' => $target->name,
            'expires_at' => $session->expires_at->toDateTimeString(),
        ], $tenant->id);

        $url = rtrim(config('platform.hrm_web_url'), '/')
            . '/impersonate/consume?token=' . $token . '&tenant=' . $tenant->id;

        return redirect()->away($url);
    }

    /** Called by the HRM banner's "End session", or from the panel's list. */
    public function end(Request $request, string $token)
    {
        $session = ImpersonationSession::where('session_token', $token)->first();
        if ($session && $session->ended_at === null) {
            $session->update(['ended_at' => now(), 'end_reason' => 'manual']);
            AuditLogger::record('impersonation.ended', 'impersonation_sessions', $session->id, null,
                ['reason' => 'manual'], $session->tenant_id);
        }

        return redirect()->route('impersonation.index')->with('success', 'Impersonation session ended.');
    }
}
