<?php

namespace App\Services;

/**
 * Minimal HS256 JWT — no external dependency. Used only for the Super Admin
 * Panel API (/api/v1/super-admin/*), signed with a secret that is distinct from
 * APP_KEY and from any tenant-HRM JWT secret.
 */
class Jwt
{
    public static function issue(array $claims, int $ttl): string
    {
        $now = time();
        $payload = array_merge($claims, [
            'iss' => config('platform.jwt.issuer'),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $now + $ttl,
        ]);

        $h = self::b64(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $p = self::b64(json_encode($payload));
        $s = self::b64(hash_hmac('sha256', "{$h}.{$p}", self::secret(), true));

        return "{$h}.{$p}.{$s}";
    }

    /** @return array|null decoded claims, or null if invalid/expired */
    public static function verify(?string $token): ?array
    {
        if (! $token || substr_count($token, '.') !== 2) {
            return null;
        }
        [$h, $p, $s] = explode('.', $token);
        $expected = self::b64(hash_hmac('sha256', "{$h}.{$p}", self::secret(), true));
        if (! hash_equals($expected, $s)) {
            return null;
        }
        $claims = json_decode(self::unb64($p), true);
        if (! is_array($claims) || ($claims['exp'] ?? 0) < time()) {
            return null;
        }

        return $claims;
    }

    private static function secret(): string
    {
        $s = (string) config('platform.jwt.secret');
        if ($s === '') {
            abort(500, 'SUPER_ADMIN_JWT_SECRET is not configured.');
        }

        return $s;
    }

    private static function b64(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    private static function unb64(string $s): string
    {
        return base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }
}
