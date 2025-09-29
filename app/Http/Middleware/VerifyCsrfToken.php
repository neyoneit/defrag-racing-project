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
        // Local-only: allow posting to the debug detect endpoint without CSRF so CLI tests work.
        // The debug route itself already guards by environment and will abort(404) if not local.
        'demos/debug/detect',
    ];
}
