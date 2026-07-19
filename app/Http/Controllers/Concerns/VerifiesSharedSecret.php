<?php

declare(strict_types=1);

namespace App\Http\Controllers\Concerns;

trait VerifiesSharedSecret
{
    /**
     * Constant-time comparison of a caller-supplied secret against the expected
     * value. Fails closed: if either side is missing/empty the check returns
     * false, so an unconfigured secret can never authenticate a request.
     */
    protected function sharedSecretMatches(mixed $provided, mixed $expected): bool
    {
        if (! is_string($provided) || $provided === ''
            || ! is_string($expected) || $expected === '') {
            return false;
        }

        return hash_equals($expected, $provided);
    }
}
