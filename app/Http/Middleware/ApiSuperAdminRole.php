<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** e.g. api.sa_role:superadmin — superadmin always passes. */
class ApiSuperAdminRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();
        if ($user && ($user->role === 'superadmin' || in_array($user->role, $roles, true))) {
            return $next($request);
        }

        return response()->json(['error' => [
            'code' => 'insufficient_role',
            'message' => 'Your super-admin role does not permit this action.',
        ]], 403);
    }
}
