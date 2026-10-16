<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Merchant dashboard gate: the user must belong to a merchant account.
 * Admin-only users are sent to the admin panel.
 */
class EnsureMerchantUser
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user?->merchant) {
            return $user?->isAdmin() ? redirect()->route('admin.dashboard') : abort(403);
        }

        if ($user->status !== 'active') {
            abort(403, 'Your user account is disabled.');
        }

        if ($user->merchant->status === MerchantStatus::Suspended && ! $request->routeIs('dashboard', 'merchant.*.index', 'security.*', 'profile.*')) {
            return redirect()->route('dashboard')->with('error', 'Your merchant account is suspended. Please contact support.');
        }

        return $next($request);
    }
}
