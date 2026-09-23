<?php

namespace App\Http\Middleware;

use App\Models\SuperAdmin;
use App\Services\Jwt;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class ApiJwtAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $claims = Jwt::verify($request->bearerToken());
        if (! $claims || ($claims['typ'] ?? 'access') !== 'access') {
            return $this->deny('unauthenticated', 'Missing or invalid access token.', 401);
        }

        $admin = SuperAdmin::find($claims['sub'] ?? 0);
        if (! $admin || ! $admin->is_active) {
            return $this->deny('unauthenticated', 'Account not found or inactive.', 401);
        }
        if (! $admin->ipAllowed($request->ip())) {
            return $this->deny('forbidden', 'IP not permitted for this account.', 403);
        }

        $request->setUserResolver(fn () => $admin);
        Auth::setUser($admin); // so Auth::id() / Auth::user() work in services

        return $next($request);
    }

    private function deny(string $code, string $msg, int $status): Response
    {
        return response()->json(['error' => ['code' => $code, 'message' => $msg]], $status);
    }
}
