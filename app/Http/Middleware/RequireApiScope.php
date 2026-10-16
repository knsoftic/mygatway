<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiError;
use App\Models\MerchantApiKey;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RequireApiScope
{
    public function handle(Request $request, Closure $next, string $scope): Response
    {
        /** @var MerchantApiKey|null $key */
        $key = $request->attributes->get('api_key');

        if (! $key || ! $key->hasScope($scope)) {
            return ApiError::make('insufficient_scope', "This API key is missing the [{$scope}] permission.", 403, ['required_scope' => $scope]);
        }

        return $next($request);
    }
}
