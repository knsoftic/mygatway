<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Admin panel gate: active admin account with two-factor authentication confirmed.
 */
class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user || ! $user->isAdmin()) {
            abort(403);
        }

        if (! $user->hasTwoFactorEnabled()) {
            return redirect()->route('security.edit')
                ->with('warning', 'Administrators must enable two-factor authentication before using the admin panel.');
        }

        return $next($request);
    }
}
