<?php

namespace App\Integrations\BinancePay;

use Illuminate\Support\Str;

/**
 * Binance Pay signing rules
 * (developers.binance.com/en/docs/products/binance-pay-merchant/api-common and /webhook-common).
 *
 * Requests:  payload = timestamp + "\n" + nonce + "\n" + body + "\n"
 *            signature = uppercase(hex(HMAC-SHA512(payload, secretKey)))
 * Webhooks:  same payload, signature header is base64 RSA-SHA256, verified with the
 *            public key returned by POST /binancepay/openapi/certificates.
 */
final class BinancePaySignature
{
    public static function payload(string $timestamp, string $nonce, string $body): string
    {
        return $timestamp."\n".$nonce."\n".$body."\n";
    }

    public static function signRequest(string $timestamp, string $nonce, string $body, string $secretKey): string
    {
        return strtoupper(hash_hmac('sha512', self::payload($timestamp, $nonce, $body), $secretKey));
    }

    /** 32-character random string of ASCII letters and digits. */
    public static function nonce(): string
    {
        return Str::random(32);
    }

    public static function verifyWebhook(string $timestamp, string $nonce, string $body, string $signatureBase64, string $publicKey): bool
    {
        $signature = base64_decode($signatureBase64, true);
        if ($signature === false || $signature === '') {
            return false;
        }

        $key = openssl_pkey_get_public(self::toPem($publicKey));
        if ($key === false) {
            return false;
        }

        return openssl_verify(self::payload($timestamp, $nonce, $body), $signature, $key, OPENSSL_ALGO_SHA256) === 1;
    }

    /** certPublic may be delivered as a full PEM or as bare base64 key material. */
    public static function toPem(string $key): string
    {
        $key = trim($key);
        if (str_contains($key, '-----BEGIN')) {
            return $key;
        }

        return "-----BEGIN PUBLIC KEY-----\n".chunk_split(preg_replace('/\s+/', '', $key), 64, "\n")."-----END PUBLIC KEY-----\n";
    }
}
