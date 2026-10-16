<?php

namespace App\Services\Webhooks;

/**
 * Signature for outgoing merchant webhooks.
 *
 * Header:  X-Gateway-Signature: t=<unix timestamp>,v1=<hex HMAC-SHA256(secret, "<t>.<raw body>")>
 * Merchants recompute the HMAC over the raw body, compare in constant time and
 * reject timestamps older than a few minutes to stop replays.
 */
final class WebhookSigner
{
    public static function sign(string $body, string $secret, int $timestamp): string
    {
        return 't='.$timestamp.',v1='.hash_hmac('sha256', $timestamp.'.'.$body, $secret);
    }

    public static function verify(string $body, string $header, string $secret, int $toleranceSeconds = 300, ?int $now = null): bool
    {
        $parts = [];
        foreach (explode(',', $header) as $pair) {
            [$k, $v] = array_pad(explode('=', trim($pair), 2), 2, null);
            if ($k !== null && $v !== null) {
                $parts[$k] = $v;
            }
        }

        if (! isset($parts['t'], $parts['v1']) || ! ctype_digit($parts['t'])) {
            return false;
        }

        $timestamp = (int) $parts['t'];
        if (abs(($now ?? time()) - $timestamp) > $toleranceSeconds) {
            return false;
        }

        return hash_equals(hash_hmac('sha256', $timestamp.'.'.$body, $secret), $parts['v1']);
    }
}
