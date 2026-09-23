<?php

namespace App\Services;

/**
 * RFC 6238 TOTP — SHA-1, 6 digits, 30-second period. No external dependency.
 */
class Totp
{
    private const PERIOD = 30;
    private const DIGITS = 6;

    /** 20-byte random secret, base32-encoded (for authenticator apps). */
    public static function generateSecret(): string
    {
        return self::base32Encode(random_bytes(20));
    }

    /** otpauth:// URI for a QR code. */
    public static function uri(string $secret, string $account, string $issuer = 'HRM Super Admin'): string
    {
        return 'otpauth://totp/' . rawurlencode("{$issuer}:{$account}")
            . '?secret=' . $secret
            . '&issuer=' . rawurlencode($issuer)
            . '&algorithm=SHA1&digits=' . self::DIGITS . '&period=' . self::PERIOD;
    }

    /** Verify a code, allowing ±1 time step for clock drift. */
    public static function verify(string $secret, string $code, int $window = 1): bool
    {
        $code = preg_replace('/\D/', '', $code);
        if (strlen($code) !== self::DIGITS) {
            return false;
        }
        $key = self::base32Decode($secret);
        if ($key === '') {
            return false;
        }
        $counter = intdiv(time(), self::PERIOD);
        for ($i = -$window; $i <= $window; $i++) {
            if (hash_equals(self::hotp($key, $counter + $i), $code)) {
                return true;
            }
        }

        return false;
    }

    /** The code valid right now — for tests / tooling only. */
    public static function currentCode(string $secret): string
    {
        return self::hotp(self::base32Decode($secret), intdiv(time(), self::PERIOD));
    }

    private static function hotp(string $key, int $counter): string
    {
        $bin = pack('N*', 0) . pack('N*', $counter);
        $hash = hash_hmac('sha1', $bin, $key, true);
        $offset = ord($hash[19]) & 0x0f;
        $part = ((ord($hash[$offset]) & 0x7f) << 24)
            | ((ord($hash[$offset + 1]) & 0xff) << 16)
            | ((ord($hash[$offset + 2]) & 0xff) << 8)
            | (ord($hash[$offset + 3]) & 0xff);

        return str_pad((string) ($part % (10 ** self::DIGITS)), self::DIGITS, '0', STR_PAD_LEFT);
    }

    private static function base32Encode(string $data): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($data) as $ch) {
            $value = ($value << 8) | ord($ch);
            $bits += 8;
            while ($bits >= 5) {
                $bits -= 5;
                $out .= $alphabet[($value >> $bits) & 31];
            }
        }
        if ($bits > 0) {
            $out .= $alphabet[($value << (5 - $bits)) & 31];
        }

        return $out;
    }

    private static function base32Decode(string $b32): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
        $out = '';
        $bits = 0;
        $value = 0;
        foreach (str_split($b32) as $ch) {
            $idx = strpos($alphabet, $ch);
            if ($idx === false) {
                continue;
            }
            $value = ($value << 5) | $idx;
            $bits += 5;
            if ($bits >= 8) {
                $bits -= 8;
                $out .= chr(($value >> $bits) & 0xff);
            }
        }

        return $out;
    }
}
