<?php

declare(strict_types=1);

namespace App\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Eloquent\Model;

/**
 * Transparent encryption cast that is wire-compatible with the `encrypt()` /
 * `decrypt()` helpers used historically across this codebase (i.e. Laravel's
 * serializing encrypter). The built-in `encrypted` cast uses the *non*-serializing
 * encryptString/decryptString, so it would NOT round-trip data that was written
 * with encrypt(); this cast does.
 *
 * The setter is idempotent: a value that is already valid ciphertext is stored
 * unchanged, so a stray manual encrypt() (or a test/seeder that pre-encrypts) can
 * never double-encrypt. The getter is lenient: a value that cannot be decrypted is
 * returned as-is, so a legacy plaintext row written before encryption was enforced
 * is still usable rather than throwing on read.
 */
class EncryptedSerialized implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        if ($value === null || $value === '') {
            return $value;
        }

        try {
            return decrypt($value);
        } catch (DecryptException $e) {
            return $value;
        }
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): array
    {
        if ($value === null) {
            return [$key => null];
        }

        // Already ciphertext? Store unchanged so we never double-encrypt.
        try {
            decrypt($value);

            return [$key => $value];
        } catch (DecryptException $e) {
            return [$key => encrypt($value)];
        }
    }
}
