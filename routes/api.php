<?php

use App\Http\Controllers\Api\V1\AccountController;
use App\Http\Controllers\Api\V1\PaymentController;
use App\Http\Controllers\Api\V1\PayoutController;
use App\Http\Controllers\Webhooks\ProviderWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Unified Merchant API (v1)
|--------------------------------------------------------------------------
| These are this platform's own endpoints. They are not Cryptomus or Binance
| endpoints; provider calls happen server-side in app/Integrations.
*/
Route::prefix('v1')
    ->name('api.v1.')
    ->middleware(['api.log', 'api.key', 'throttle:merchant-api'])
    ->group(function () {
        Route::middleware('api.scope:payments:write')->group(function () {
            Route::post('payments', [PaymentController::class, 'store'])->middleware('api.idempotent')->name('payments.store');
            Route::post('payments/{id}/verify', [PaymentController::class, 'verify'])->name('payments.verify');
        });
        Route::middleware('api.scope:payments:read')->group(function () {
            Route::get('payments', [PaymentController::class, 'index'])->name('payments.index');
            Route::get('payments/{id}', [PaymentController::class, 'show'])->name('payments.show');
            Route::get('transactions', [AccountController::class, 'transactions'])->name('transactions.index');
        });

        Route::middleware('api.scope:payouts:write')->group(function () {
            Route::post('payouts', [PayoutController::class, 'store'])->middleware('api.idempotent')->name('payouts.store');
            Route::post('payouts/{id}/cancel', [PayoutController::class, 'cancel'])->name('payouts.cancel');
        });
        Route::middleware('api.scope:payouts:read')->group(function () {
            Route::get('payouts', [PayoutController::class, 'index'])->name('payouts.index');
            Route::get('payouts/{id}', [PayoutController::class, 'show'])->name('payouts.show');
        });

        Route::get('balance', [AccountController::class, 'balance'])->middleware('api.scope:balance:read')->name('balance');
        Route::get('fees', [AccountController::class, 'fees'])->name('fees');
        Route::get('fees/quote', [AccountController::class, 'quote'])->name('fees.quote');
        Route::get('currencies', [AccountController::class, 'currencies'])->name('currencies');
    });

/*
|--------------------------------------------------------------------------
| Provider callbacks (authenticated by provider signature, not API keys)
|--------------------------------------------------------------------------
*/
Route::post('webhooks/{provider}', ProviderWebhookController::class)
    ->where('provider', 'cryptomus|binance_pay')
    ->middleware('throttle:provider-webhooks')
    ->name('webhooks.provider');
