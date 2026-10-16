<?php

namespace App\Integrations\Cryptomus;

/**
 * Cryptomus request/webhook signing.
 *
 * Request (doc.cryptomus.com/merchant-api/request-format):
 *   sign = md5(base64_encode(json_body) . API_KEY)
 *
 * Webhook (doc.cryptomus.com/merchant-api/payments/webhook):
 *   remove "sign" from the decoded body, re-encode with PHP json_encode(..., JSON_UNESCAPED_UNICODE)
 *   (which escapes "/" as "\/"), then md5(base64_encode(json) . API_PAYMENT_KEY).
 */
final class CryptomusSignature
{
    public static function sign(string $jsonBody, string $apiKey): string
    {
        return md5(base64_encode($jsonBody).$apiKey);
    }

    /**
     * @param  array<string, mixed>  $payload  Decoded webhook body including "sign".
     * @param  list<string>  $candidateKeys  Keys allowed to have signed this webhook.
     */
    public static function verifyWebhook(array $payload, array $candidateKeys): bool
    {
        $received = $payload['sign'] ?? null;
        if (! is_string($received) || $received === '') {
            return false;
        }

        unset($payload['sign']);

        // Documented encoding first; the unescaped-slash variant covers senders that do not escape "/".
        $encodings = [
            json_encode($payload, JSON_UNESCAPED_UNICODE),
            json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        ];

        foreach ($candidateKeys as $key) {
            if (! is_string($key) || $key === '') {
                continue;
            }
            foreach (array_unique(array_filter($encodings)) as $json) {
                if (hash_equals(self::sign($json, $key), strtolower($received))) {
                    return true;
                }
            }
        }

        return false;
    }
}
