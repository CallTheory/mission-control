<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * The URIs that should be excluded from CSRF verification.
     *
     * @var array<int, string>
     */
    protected $except = [
        '/webhooks/*',
        '/api/*',
        '/sso/*',
        // The WCTP gateway: Enterprise Hosts post to /wctp and carriers post their
        // webhooks under /wctp/... -- none of them can carry a CSRF token. Both entries
        // are needed: 'wctp/*' does not match the bare /wctp endpoint.
        '/wctp',
        '/wctp/*',
    ];
}
