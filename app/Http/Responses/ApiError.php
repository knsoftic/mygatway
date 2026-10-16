<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Structured API error envelope:
 * { "error": { "code": "...", "message": "...", "details": {...}, "request_id": "..." } }
 */
final class ApiError
{
    /** @param  array<string, mixed>  $details */
    public static function make(string $code, string $message, int $status, array $details = [], ?Request $request = null): JsonResponse
    {
        $request ??= request();

        $error = ['code' => $code, 'message' => $message];
        if ($details !== []) {
            $error['details'] = $details;
        }
        $error['request_id'] = $request?->attributes->get('request_id');

        $response = new JsonResponse(['error' => $error], $status);
        $request?->attributes->set('error_code', $code);

        return $response;
    }
}
