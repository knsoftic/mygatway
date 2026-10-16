<?php

namespace App\Http\Middleware;

use App\Models\ApiRequestLog;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Records metadata (never bodies or secrets) of every merchant API call for usage
 * statistics, error logs and abuse investigation.
 */
class LogApiRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $request->attributes->set('started_at', microtime(true));

        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        try {
            $key = $request->attributes->get('api_key');
            $errorCode = $request->attributes->get('error_code');
            if (! $errorCode && $response->getStatusCode() >= 400) {
                $decoded = json_decode((string) $response->getContent(), true);
                $errorCode = is_array($decoded) ? ($decoded['error']['code'] ?? null) : null;
            }

            ApiRequestLog::query()->create([
                'request_id' => $request->attributes->get('request_id') ?? (string) \Illuminate\Support\Str::uuid(),
                'merchant_id' => $key?->merchant_id,
                'api_key_id' => $key?->id,
                'mode' => $key?->mode?->value,
                'method' => $request->method(),
                'path' => mb_substr('/'.$request->path(), 0, 255),
                'status_code' => $response->getStatusCode(),
                'error_code' => $errorCode ? mb_substr((string) $errorCode, 0, 64) : null,
                'ip_address' => $request->ip(),
                'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                'idempotency_key' => $request->header('Idempotency-Key') ? mb_substr((string) $request->header('Idempotency-Key'), 0, 191) : null,
                'duration_ms' => (int) ((microtime(true) - (float) $request->attributes->get('started_at', microtime(true))) * 1000),
                'created_at' => now(),
            ]);
        } catch (Throwable $e) {
            Log::warning('Failed to write API request log', ['error' => $e->getMessage()]);
        }
    }
}
