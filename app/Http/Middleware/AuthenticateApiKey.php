<?php

namespace App\Http\Middleware;

use App\Enums\MerchantStatus;
use App\Http\Responses\ApiError;
use App\Services\Merchants\ApiKeyService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Authenticates merchant API calls with "Authorization: Bearer sk_{test|live}_...".
 * The key decides the merchant and the mode (test/live) for the whole request.
 */
class AuthenticateApiKey
{
    public function __construct(private readonly ApiKeyService $keys) {}

    public function handle(Request $request, Closure $next): Response
    {
        $secret = (string) $request->bearerToken();

        if ($secret === '') {
            return ApiError::make('authentication_required', 'Provide your secret API key as a Bearer token.', 401);
        }

        $key = $this->keys->findActiveBySecret($secret);
        if (! $key) {
            return ApiError::make('invalid_api_key', 'The API key is invalid, expired or revoked.', 401);
        }

        if (! $key->allowsIp($request->ip())) {
            return ApiError::make('ip_not_allowed', 'Requests from this IP address are not allowed for this API key.', 403);
        }

        $merchant = $key->merchant;
        if ($merchant->status === MerchantStatus::Suspended) {
            return ApiError::make('account_suspended', 'This merchant account is suspended.', 403);
        }

        $request->attributes->add([
            'merchant' => $merchant,
            'api_key' => $key,
            'mode' => $key->mode,
        ]);

        if ($key->last_used_at === null || $key->last_used_at->lt(now()->subMinute())) {
            $key->forceFill(['last_used_at' => now(), 'last_used_ip' => $request->ip()])->saveQuietly();
        }

        return $next($request);
    }
}
