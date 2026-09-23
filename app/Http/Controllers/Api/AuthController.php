<?php

namespace App\Http\Controllers\Api;

use App\Models\SuperAdmin;
use App\Services\Jwt;
use App\Services\Totp;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;

class AuthController extends ApiController
{
    /** email + password + totp code -> access & refresh tokens. */
    public function login(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'code' => ['nullable', 'string'],
        ]);

        $key = 'api-login:' . mb_strtolower($data['email']) . '|' . $request->ip();
        if (RateLimiter::tooManyAttempts($key, 5)) {
            return $this->fail('rate_limited', 'Too many attempts. Wait and retry.', 429);
        }

        $admin = SuperAdmin::where('email', $data['email'])->first();
        if (! $admin || ! Auth::getProvider()->validateCredentials($admin, $data)) {
            RateLimiter::hit($key, 900);

            return $this->fail('invalid_credentials', 'Email or password is incorrect.', 401);
        }
        if (! $admin->is_active) {
            return $this->fail('inactive', 'This account is deactivated.', 403);
        }
        if (! $admin->ipAllowed($request->ip())) {
            return $this->fail('ip_blocked', 'Login from this IP is not permitted.', 403);
        }

        if (! $admin->hasTotp()) {
            return $this->fail('totp_enrolment_required',
                'Enrol TOTP in the web panel before using the API.', 403);
        }
        if (! Totp::verify((string) $admin->totp_secret, (string) ($data['code'] ?? ''))) {
            RateLimiter::hit($key, 900);

            return $this->fail('totp_invalid', 'A valid 6-digit TOTP code is required.', 401);
        }

        RateLimiter::clear($key);
        $admin->forceFill(['last_login_at' => now(), 'last_login_ip' => $request->ip()])->save();

        return $this->ok($this->tokens($admin));
    }

    public function refresh(Request $request)
    {
        $claims = Jwt::verify($request->input('refresh_token') ?: $request->bearerToken());
        if (! $claims || ($claims['typ'] ?? null) !== 'refresh') {
            return $this->fail('invalid_refresh', 'Invalid or expired refresh token.', 401);
        }
        $admin = SuperAdmin::find($claims['sub'] ?? 0);
        if (! $admin || ! $admin->is_active) {
            return $this->fail('inactive', 'Account not found or inactive.', 401);
        }

        return $this->ok($this->tokens($admin));
    }

    public function logout()
    {
        // Stateless — client discards the token. (A denylist would go here.)
        return $this->ok(['ok' => true]);
    }

    public function me(Request $request)
    {
        $a = $request->user();

        return $this->ok([
            'id' => $a->id, 'name' => $a->name, 'email' => $a->email, 'role' => $a->role,
        ]);
    }

    private function tokens(SuperAdmin $admin): array
    {
        $access = (int) config('platform.jwt.access_ttl');
        $refresh = (int) config('platform.jwt.refresh_ttl');

        return [
            'token_type' => 'Bearer',
            'access_token' => Jwt::issue(['sub' => $admin->id, 'role' => $admin->role, 'typ' => 'access'], $access),
            'refresh_token' => Jwt::issue(['sub' => $admin->id, 'typ' => 'refresh'], $refresh),
            'expires_in' => $access,
            'admin' => ['id' => $admin->id, 'name' => $admin->name, 'role' => $admin->role],
        ];
    }
}
