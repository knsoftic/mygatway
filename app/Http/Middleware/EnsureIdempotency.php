<?php

namespace App\Http\Middleware;

use App\Http\Responses\ApiError;
use App\Models\IdempotencyKey;
use App\Models\Merchant;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * Requires an Idempotency-Key on money-moving POST requests.
 *
 *  - First request with a key: executed; a successful (2xx) response is stored.
 *  - Same key + same body again: the stored response is replayed (Idempotent-Replayed: true).
 *  - Same key + different body: 422 idempotency_key_reused.
 *  - Same key while the first request is still running: 409 request_in_progress.
 * Unsuccessful responses are not stored, so the client may retry with the same key.
 */
class EnsureIdempotency
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = trim((string) $request->header('Idempotency-Key', ''));

        if ($key === '') {
            return ApiError::make('idempotency_key_required', 'Send a unique Idempotency-Key header with this request.', 400);
        }
        if (strlen($key) > 191 || ! preg_match('/^[\x21-\x7E]+$/', $key)) {
            return ApiError::make('invalid_idempotency_key', 'Idempotency-Key must be 1-191 printable ASCII characters.', 400);
        }

        /** @var Merchant $merchant */
        $merchant = $request->attributes->get('merchant');
        $mode = $request->attributes->get('mode');
        $hash = hash('sha256', $request->method().'|'.$request->path().'|'.$request->getContent());

        IdempotencyKey::query()->where('expires_at', '<', now())
            ->where('merchant_id', $merchant->id)->where('key', $key)->delete();

        try {
            $record = IdempotencyKey::query()->create([
                'merchant_id' => $merchant->id,
                'mode' => $mode->value,
                'key' => $key,
                'request_method' => $request->method(),
                'request_path' => mb_substr($request->path(), 0, 255),
                'request_hash' => $hash,
                'expires_at' => now()->addHours((int) config('gateway.api.idempotency_ttl_hours', 24)),
            ]);
        } catch (UniqueConstraintViolationException) {
            $existing = IdempotencyKey::query()
                ->where('merchant_id', $merchant->id)->where('mode', $mode->value)->where('key', $key)->first();

            if (! $existing) {
                return ApiError::make('request_in_progress', 'A request with this Idempotency-Key is being processed.', 409);
            }
            if (! hash_equals($existing->request_hash, $hash)) {
                return ApiError::make('idempotency_key_reused', 'This Idempotency-Key was already used with a different request.', 422);
            }
            if ($existing->completed_at === null) {
                return ApiError::make('request_in_progress', 'A request with this Idempotency-Key is being processed.', 409);
            }

            return (new IlluminateResponse($existing->response_body, $existing->response_status, ['Content-Type' => 'application/json']))
                ->header('Idempotent-Replayed', 'true');
        }

        $request->attributes->set('idempotency_key', $key);

        try {
            $response = $next($request);
        } catch (\Throwable $e) {
            $record->delete();

            throw $e;
        }

        if ($response->getStatusCode() >= 200 && $response->getStatusCode() < 300) {
            $record->forceFill([
                'response_status' => $response->getStatusCode(),
                'response_body' => $response->getContent(),
                'completed_at' => now(),
            ])->save();
        } else {
            $record->delete();
        }

        return $response;
    }
}
