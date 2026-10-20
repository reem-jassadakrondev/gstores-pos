<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The pre-SaaS app has no tenant separation, so only users that existed before
 * the redesign (can_access_legacy) may open it. Everyone else goes to the new back-office.
 */
class EnsureLegacyAccess
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()?->can_access_legacy) {
            return redirect('/app');
        }

        return $next($request);
    }
}
