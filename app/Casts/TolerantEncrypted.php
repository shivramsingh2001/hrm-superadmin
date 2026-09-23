<?php

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Crypt;

/**
 * Encrypts on write. On read, decrypts if the value is ciphertext, otherwise
 * returns it unchanged — so a column can be encrypted going forward without a
 * hard cutover on legacy plaintext rows (run `payment:encrypt-refs` to migrate).
 */
class TolerantEncrypted implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }
        try {
            return Crypt::decryptString($value);
        } catch (\Throwable $e) {
            return $value; // legacy plaintext
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return $value === null ? null : Crypt::encryptString((string) $value);
    }
}
