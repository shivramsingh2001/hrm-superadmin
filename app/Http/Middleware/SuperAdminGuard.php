<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Phase 7: enforce, on every authenticated request —
 *   - global + per-account IP allowlist,
 *   - a hard session cap (config('platform.session_hard_minutes')) regardless of
 *     activity (idle timeout is SESSION_LIFETIME),
 *   - the account is still active.
 */
class SuperAdminGuard
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user) {
            return $next($request);
        }

        if (! $user->is_active) {
            return $this->kick($request, 'Your account has been deactivated.');
        }

        $global = config('platform.ip_whitelist', []);
        if ($global && ! in_array($request->ip(), $global, true)) {
            return $this->kick($request, 'Access from this network is not permitted.');
        }
        if (method_exists($user, 'ipAllowed') && ! $user->ipAllowed($request->ip())) {
            return $this->kick($request, 'Access from this IP is not permitted for your account.');
        }

        $loginAt = $request->session()->get('sa_login_at');
        $hard = (int) config('platform.session_hard_minutes', 720);
        if ($loginAt && Carbon::parse($loginAt)->addMinutes($hard)->isPast()) {
            return $this->kick($request, 'Session expired — please sign in again.');
        }

        return $next($request);
    }

    private function kick(Request $request, string $msg): Response
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->expectsJson()) {
            abort(403, $msg);
        }

        return redirect()->route('login')->withErrors(['email' => $msg]);
    }
}
