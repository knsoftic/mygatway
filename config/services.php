<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Cryptomus Merchant API
    |--------------------------------------------------------------------------
    | Docs: https://doc.cryptomus.com/merchant-api/request-format
    | Requests are signed with md5(base64(json_body) . key). Payments use the
    | payment API key, payouts use the separate payout API key.
    */
    'cryptomus' => [
        'base_url' => env('CRYPTOMUS_BASE_URL', 'https://api.cryptomus.com'),
        'merchant_id' => env('CRYPTOMUS_MERCHANT_ID'),
        'payment_key' => env('CRYPTOMUS_PAYMENT_KEY'),
        'payout_key' => env('CRYPTOMUS_PAYOUT_KEY'),
        'webhook_ips' => array_values(array_filter(array_map('trim', explode(',', (string) env('CRYPTOMUS_WEBHOOK_IPS', '91.227.144.54'))))),
        'enforce_webhook_ip' => (bool) env('CRYPTOMUS_ENFORCE_WEBHOOK_IP', true),
        'timeout' => (int) env('CRYPTOMUS_TIMEOUT', 20),
        // Invoice finalises after the first payment (no top-ups). Keeps accounting deterministic.
        'is_payment_multiple' => false,
    ],

    /*
    |--------------------------------------------------------------------------
    | Binance Pay Merchant API
    |--------------------------------------------------------------------------
    | Docs: https://developers.binance.com/en/docs/products/binance-pay-merchant/api-common
    | Requests: HMAC-SHA512 (uppercase hex) over "timestamp\nnonce\nbody\n".
    | Webhooks: RSA-SHA256, public key from /binancepay/openapi/certificates.
    */
    'binance_pay' => [
        'base_url' => env('BINANCE_PAY_BASE_URL', 'https://bpay.binanceapi.com'),
        'api_key' => env('BINANCE_PAY_API_KEY'),
        'secret_key' => env('BINANCE_PAY_SECRET_KEY'),
        'payout_wallet' => env('BINANCE_PAY_PAYOUT_WALLET', 'FUNDING_WALLET'),
        'timeout' => (int) env('BINANCE_PAY_TIMEOUT', 20),
        'certificate_cache_ttl' => 3600,
    ],

];
