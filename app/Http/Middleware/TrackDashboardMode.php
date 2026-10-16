<?php

namespace App\Http\Middleware;

use App\Enums\Mode;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * The merchant dashboard shows either test or live data (session toggle), never both mixed.
 */
class TrackDashboardMode
{
    public function handle(Request $request, Closure $next): Response
    {
        $mode = Mode::tryFrom((string) $request->session()->get('dashboard_mode', 'test')) ?? Mode::Test;
        $request->attributes->set('dashboard_mode', $mode);
        View::share('dashboardMode', $mode);

        return $next($request);
    }
}
