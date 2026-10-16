<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Baseline browser security headers and a nonce-based Content Security Policy.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $nonce = Str::random(32);
        Vite::useCspNonce($nonce);
        $request->attributes->set('csp_nonce', $nonce);

        $response = $next($request);

        $headers = $response->headers;
        $headers->set('X-Content-Type-Options', 'nosniff');
        $headers->set('X-Frame-Options', 'DENY');
        $headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $headers->set('Cross-Origin-Opener-Policy', 'same-origin');

        if ($request->isSecure()) {
            $headers->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        if (! $headers->has('Content-Security-Policy') && ! $request->is('api/*')) {
            $viteDev = app()->isLocal() && Vite::isRunningHot() ? ' '.rtrim((string) file_get_contents(public_path('hot'))) : '';
            $wsDev = $viteDev !== '' ? ' '.str_replace(['http://', 'https://'], ['ws://', 'wss://'], trim($viteDev)) : '';

            $headers->set('Content-Security-Policy', implode('; ', [
                "default-src 'self'",
                "script-src 'self' 'nonce-{$nonce}'{$viteDev}",
                "style-src 'self' 'unsafe-inline'{$viteDev}",
                "img-src 'self' data:",
                "font-src 'self' data:",
                "connect-src 'self'{$viteDev}{$wsDev}",
                "frame-ancestors 'none'",
                "form-action 'self'",
                "base-uri 'self'",
                "object-src 'none'",
            ]));
        }

        if ($request->is('api/*')) {
            $headers->set('Cache-Control', 'no-store');
        }

        return $response;
    }
}
