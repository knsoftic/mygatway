<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Money precision
    |--------------------------------------------------------------------------
    | All amounts are stored as DECIMAL(30,8) and calculated with bcmath.
    | Both Cryptomus and Binance Pay report amounts with up to 8 decimals.
    */
    'scale' => 8,

    /*
    |--------------------------------------------------------------------------
    | Reverse proxies whose X-Forwarded-* headers are trusted (e.g. Nginx on localhost)
    |--------------------------------------------------------------------------
    */
    'trusted_proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1'))))),

    /*
    |--------------------------------------------------------------------------
    | Merchant API
    |--------------------------------------------------------------------------
    */
    'api' => [
        // Pepper used to hash API secrets. Never commit the real value.
        'key_pepper' => env('GATEWAY_API_KEY_PEPPER'),
        'default_rate_limit_per_minute' => 120,
        'max_rate_limit_per_minute' => 1200,
        // How long an Idempotency-Key is remembered.
        'idempotency_ttl_hours' => 24,
        'max_metadata_keys' => 20,
        'scopes' => [
            'payments:read', 'payments:write',
            'payouts:read', 'payouts:write',
            'balance:read',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Pay In
    |--------------------------------------------------------------------------
    */
    'payments' => [
        'default_lifetime' => 3600,
        // Cryptomus allows 300..43200 seconds; we use the same window for every provider.
        'min_lifetime' => 300,
        'max_lifetime' => 43200,
        // Grace period after expiry before we ask the provider for a final status.
        'expiry_grace_seconds' => 300,
        // After a signed callback arrives, confirm the state with an authenticated
        // provider status query before applying it (defence in depth).
        'verify_callbacks_with_api' => (bool) env('GATEWAY_VERIFY_CALLBACKS_WITH_API', true),
    ],

    /*
    |--------------------------------------------------------------------------
    | Pay Out
    |--------------------------------------------------------------------------
    */
    'payouts' => [
        'manual_approval' => (bool) env('GATEWAY_PAYOUT_MANUAL_APPROVAL', true),
        // Charge the provider's published network/commission estimate to the merchant.
        'pass_network_fee_to_merchant' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Outgoing merchant webhooks
    |--------------------------------------------------------------------------
    */
    'webhooks' => [
        'signature_header' => 'X-Gateway-Signature',
        'timeout' => 10,
        // Seconds to wait before each retry attempt (attempt 2, 3, ...).
        'retry_schedule' => [60, 300, 1800, 7200, 21600, 43200, 86400],
        'block_private_ips' => (bool) env('GATEWAY_WEBHOOK_BLOCK_PRIVATE_IPS', true),
        'require_https_in_live' => true,
        'tolerance_seconds' => 300,
    ],

    /*
    |--------------------------------------------------------------------------
    | Risk controls
    |--------------------------------------------------------------------------
    */
    'risk' => [
        'failed_login_threshold' => 5,
        'failed_login_window_minutes' => 15,
        // Single payouts at or above this amount (any currency) raise a review risk event.
        'large_payout_review_threshold' => '10000',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data retention (days)
    |--------------------------------------------------------------------------
    | Financial records (payments, payouts, ledger) are never pruned.
    */
    'retention' => [
        'api_request_logs' => 90,
        'provider_webhook_logs' => 365,
        'login_histories' => 365,
    ],

];
