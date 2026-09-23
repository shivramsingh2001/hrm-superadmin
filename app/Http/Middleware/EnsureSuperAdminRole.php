<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate an action to one or more super-admin roles, e.g. `sa_role:superadmin`.
 * Superadmin always passes.
 */
class EnsureSuperAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user || ! $user->is_active) {
            abort(403);
        }

        if ($user->role === 'superadmin' || in_array($user->role, $roles, true)) {
            return $next($request);
        }

        abort(403, 'Your super-admin role does not permit this action.');
    }
}
