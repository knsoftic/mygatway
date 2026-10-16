<?php

use App\Http\Controllers\Account\SecurityController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\Merchant;
use App\Http\Controllers\Web\ContactController;
use App\Http\Controllers\Web\DocsController;
use App\Http\Controllers\Web\PageController;
use App\Http\Controllers\Web\SandboxCheckoutController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public website
|--------------------------------------------------------------------------
*/
Route::get('/', [PageController::class, 'home'])->name('home');
Route::get('/pricing', [PageController::class, 'pricing'])->name('pricing');
Route::get('/payment-methods', [PageController::class, 'paymentMethods'])->name('payment-methods');
foreach (['features', 'how-it-works', 'about', 'faq', 'contact', 'terms', 'privacy', 'refund-policy'] as $page) {
    Route::get('/'.$page, [PageController::class, 'show'])->defaults('page', $page)->name($page);
}
Route::post('/contact', [ContactController::class, 'store'])->middleware('throttle:contact')->name('contact.store');

Route::get('/developers', [DocsController::class, 'index'])->name('docs.index');
Route::get('/developers/{page}', [DocsController::class, 'show'])->where('page', '[a-z0-9\-]+')->name('docs.show');

// Test-mode checkout (signed URLs only).
Route::middleware('signed')->group(function () {
    Route::get('/sandbox/checkout/{payment}', [SandboxCheckoutController::class, 'show'])->name('sandbox.checkout');
    Route::post('/sandbox/checkout/{payment}', [SandboxCheckoutController::class, 'simulate'])->middleware('throttle:30,1')->name('sandbox.simulate');
});

/*
|--------------------------------------------------------------------------
| Authenticated account area (merchants and admins)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'dashboard.mode'])->prefix('account')->group(function () {
    Route::get('/security', [SecurityController::class, 'edit'])->name('security.edit');
    Route::get('/security/recovery-codes', [SecurityController::class, 'showRecoveryCodes'])->middleware('password.confirm')->name('security.recovery-codes');
    Route::delete('/security/sessions', [SecurityController::class, 'destroyOtherSessions'])->middleware('throttle:sensitive')->name('security.sessions.destroy');
});

/*
|--------------------------------------------------------------------------
| Merchant dashboard
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'merchant', 'dashboard.mode'])->group(function () {
    Route::get('/dashboard', [Merchant\DashboardController::class, 'index'])->name('dashboard');

    Route::prefix('dashboard')->name('merchant.')->group(function () {
        Route::post('/mode', [Merchant\DashboardController::class, 'switchMode'])->name('mode.update');

        Route::get('/payments', [Merchant\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [Merchant\PaymentController::class, 'show'])->name('payments.show');

        Route::get('/payouts', [Merchant\PayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/new', [Merchant\PayoutController::class, 'create'])->name('payouts.create');
        Route::post('/payouts', [Merchant\PayoutController::class, 'store'])->middleware('throttle:sensitive')->name('payouts.store');
        Route::get('/payouts/{payout}', [Merchant\PayoutController::class, 'show'])->name('payouts.show');
        Route::post('/payouts/{payout}/cancel', [Merchant\PayoutController::class, 'cancel'])->name('payouts.cancel');

        Route::get('/api-keys', [Merchant\ApiKeyController::class, 'index'])->name('api-keys.index');
        Route::post('/api-keys', [Merchant\ApiKeyController::class, 'store'])->middleware('throttle:sensitive')->name('api-keys.store');
        Route::post('/api-keys/{apiKey}/rotate', [Merchant\ApiKeyController::class, 'rotate'])->middleware('throttle:sensitive')->name('api-keys.rotate');
        Route::delete('/api-keys/{apiKey}', [Merchant\ApiKeyController::class, 'destroy'])->name('api-keys.destroy');
        Route::get('/api-logs', [Merchant\ReportController::class, 'apiLogs'])->name('api-logs.index');

        Route::get('/ledger', [Merchant\LedgerController::class, 'index'])->name('ledger.index');
        Route::get('/reports', [Merchant\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [Merchant\ReportController::class, 'export'])->middleware('throttle:sensitive')->name('reports.export');

        Route::get('/webhooks', [Merchant\WebhookEndpointController::class, 'index'])->name('webhooks.index');
        Route::post('/webhooks', [Merchant\WebhookEndpointController::class, 'store'])->middleware('throttle:sensitive')->name('webhooks.store');
        Route::patch('/webhooks/{endpoint}', [Merchant\WebhookEndpointController::class, 'update'])->name('webhooks.update');
        Route::delete('/webhooks/{endpoint}', [Merchant\WebhookEndpointController::class, 'destroy'])->name('webhooks.destroy');
        Route::post('/webhooks/{endpoint}/roll-secret', [Merchant\WebhookEndpointController::class, 'rollSecret'])->name('webhooks.roll-secret');
        Route::post('/webhooks/{endpoint}/ping', [Merchant\WebhookEndpointController::class, 'ping'])->middleware('throttle:sensitive')->name('webhooks.ping');
        Route::post('/webhooks/deliveries/{delivery}/retry', [Merchant\WebhookEndpointController::class, 'retry'])->middleware('throttle:sensitive')->name('webhooks.retry');

        Route::get('/settings/profile', [Merchant\AccountController::class, 'editProfile'])->name('profile.edit');
        Route::put('/settings/profile', [Merchant\AccountController::class, 'updateProfile'])->name('profile.update');
        Route::get('/settings/verification', [Merchant\AccountController::class, 'showVerification'])->name('verification.show');
        Route::post('/settings/verification', [Merchant\AccountController::class, 'submitVerification'])->middleware('throttle:sensitive')->name('verification.store');
        Route::get('/settings/notifications', [Merchant\AccountController::class, 'editNotifications'])->name('notifications.edit');
        Route::put('/settings/notifications', [Merchant\AccountController::class, 'updateNotifications'])->name('notifications.update');
    });
});

Route::get('/notifications', [Merchant\AccountController::class, 'notifications'])->middleware(['auth', 'verified', 'dashboard.mode'])->name('notifications.index');

/*
|--------------------------------------------------------------------------
| Admin panel (admin role + confirmed 2FA)
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'verified', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::middleware('can:merchants.view')->group(function () {
        Route::get('/merchants', [Admin\MerchantController::class, 'index'])->name('merchants.index');
        Route::get('/merchants/{merchant}', [Admin\MerchantController::class, 'show'])->name('merchants.show');
    });
    Route::middleware('can:merchants.manage')->group(function () {
        Route::put('/merchants/{merchant}/status', [Admin\MerchantController::class, 'updateStatus'])->name('merchants.status');
        Route::put('/merchants/{merchant}/live', [Admin\MerchantController::class, 'updateLive'])->name('merchants.live');
        Route::post('/merchants/{merchant}/limits', [Admin\MerchantController::class, 'updateLimit'])->name('merchants.limits.store');
        Route::delete('/merchants/{merchant}/limits/{limit}', [Admin\MerchantController::class, 'destroyLimit'])->name('merchants.limits.destroy');
    });

    Route::middleware('can:merchants.verify')->group(function () {
        Route::get('/verifications', [Admin\VerificationController::class, 'index'])->name('verifications.index');
        Route::get('/verifications/{verification}', [Admin\VerificationController::class, 'show'])->name('verifications.show');
        Route::get('/verifications/{verification}/document', [Admin\VerificationController::class, 'document'])->name('verifications.document');
        Route::post('/verifications/{verification}/approve', [Admin\VerificationController::class, 'approve'])->name('verifications.approve');
        Route::post('/verifications/{verification}/reject', [Admin\VerificationController::class, 'reject'])->name('verifications.reject');
    });

    Route::middleware('can:payments.view')->group(function () {
        Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
        Route::get('/payments/{payment}', [Admin\PaymentController::class, 'show'])->middleware('password.confirm')->name('payments.show');
    });
    Route::post('/payments/{payment}/reverify', [Admin\PaymentController::class, 'reverify'])->middleware('can:payments.reconcile')->name('payments.reverify');
    Route::post('/payments/{payment}/clear-review', [Admin\PaymentController::class, 'clearReview'])->middleware('can:payments.reconcile')->name('payments.clear-review');
    Route::post('/payments/{payment}/refund', [Admin\PaymentController::class, 'refund'])->middleware('can:payments.refund')->name('payments.refund');

    Route::middleware('can:payouts.view')->group(function () {
        Route::get('/payouts', [Admin\PayoutController::class, 'index'])->name('payouts.index');
        Route::get('/payouts/{payout}', [Admin\PayoutController::class, 'show'])->middleware('password.confirm')->name('payouts.show');
    });
    Route::middleware('can:payouts.approve')->group(function () {
        Route::post('/payouts/{payout}/approve', [Admin\PayoutController::class, 'approve'])->name('payouts.approve');
        Route::post('/payouts/{payout}/reject', [Admin\PayoutController::class, 'reject'])->name('payouts.reject');
        Route::post('/payouts/{payout}/reverify', [Admin\PayoutController::class, 'reverify'])->name('payouts.reverify');
        Route::post('/payouts/{payout}/clear-review', [Admin\PayoutController::class, 'clearReview'])->name('payouts.clear-review');
    });

    Route::get('/fee-rules', [Admin\FeeRuleController::class, 'index'])->middleware('can:fees.view')->name('fee-rules.index');
    Route::get('/fee-rules/preview', [Admin\FeeRuleController::class, 'preview'])->middleware('can:fees.view')->name('fee-rules.preview');
    Route::middleware('can:fees.manage')->group(function () {
        Route::get('/fee-rules/create', [Admin\FeeRuleController::class, 'create'])->name('fee-rules.create');
        Route::post('/fee-rules', [Admin\FeeRuleController::class, 'store'])->name('fee-rules.store');
        Route::get('/fee-rules/{feeRule}/edit', [Admin\FeeRuleController::class, 'edit'])->name('fee-rules.edit');
        Route::put('/fee-rules/{feeRule}', [Admin\FeeRuleController::class, 'update'])->name('fee-rules.update');
        Route::post('/fee-rules/{feeRule}/toggle', [Admin\FeeRuleController::class, 'toggle'])->name('fee-rules.toggle');
    });

    Route::middleware('can:providers.manage')->group(function () {
        Route::get('/providers', [Admin\ProviderController::class, 'index'])->name('providers.index');
        Route::put('/providers/{provider}', [Admin\ProviderController::class, 'update'])->name('providers.update');
        Route::put('/providers/{provider}/credentials', [Admin\ProviderController::class, 'updateCredentials'])->middleware('throttle:sensitive')->name('providers.credentials');
        Route::get('/providers/{provider}/currencies', [Admin\ProviderController::class, 'currencies'])->name('providers.currencies');
        Route::post('/providers/{provider}/currencies', [Admin\ProviderController::class, 'storeCurrency'])->name('providers.currencies.store');
        Route::post('/currencies/{currency}/toggle', [Admin\ProviderController::class, 'toggleCurrency'])->name('providers.currencies.toggle');
        Route::post('/providers/health-check', [Admin\ProviderController::class, 'healthCheck'])->middleware('throttle:sensitive')->name('providers.health');
        Route::post('/providers/sync-currencies', [Admin\ProviderController::class, 'syncCurrencies'])->middleware('throttle:sensitive')->name('providers.sync');
    });

    Route::middleware('can:payments.reconcile')->group(function () {
        Route::get('/reconciliation', [Admin\ReconciliationController::class, 'index'])->name('reconciliation.index');
        Route::post('/reconciliation', [Admin\ReconciliationController::class, 'run'])->middleware('throttle:sensitive')->name('reconciliation.run');
        Route::get('/reconciliation/{run}', [Admin\ReconciliationController::class, 'show'])->name('reconciliation.show');
        Route::post('/reconciliation/items/{item}/resolve', [Admin\ReconciliationController::class, 'resolve'])->name('reconciliation.resolve');
    });

    Route::middleware('can:webhooks.view')->group(function () {
        Route::get('/webhooks', [Admin\LogController::class, 'webhooks'])->name('webhooks.index');
        Route::get('/webhooks/inbound/{log}', [Admin\LogController::class, 'showInbound'])->name('webhooks.inbound');
    });
    Route::post('/webhooks/inbound/{log}/reprocess', [Admin\LogController::class, 'reprocessInbound'])->middleware('can:payments.reconcile')->name('webhooks.reprocess');
    Route::post('/webhooks/deliveries/{delivery}/retry', [Admin\LogController::class, 'retryDelivery'])->middleware('can:webhooks.retry')->name('webhooks.retry');

    Route::get('/audit-logs', [Admin\LogController::class, 'audit'])->middleware('can:audit.view')->name('audit-logs.index');
    Route::get('/risk', [Admin\LogController::class, 'risk'])->middleware('can:risk.manage')->name('risk.index');
    Route::post('/risk/{event}/resolve', [Admin\LogController::class, 'resolveRisk'])->middleware('can:risk.manage')->name('risk.resolve');

    Route::get('/reports', [Admin\SystemController::class, 'reports'])->middleware('can:reports.export')->name('reports.index');
    Route::get('/reports/export', [Admin\SystemController::class, 'export'])->middleware(['can:reports.export', 'throttle:sensitive'])->name('reports.export');

    Route::middleware('can:settings.manage')->group(function () {
        Route::get('/settings', [Admin\SystemController::class, 'editSettings'])->middleware('password.confirm')->name('settings.edit');
        Route::put('/settings', [Admin\SystemController::class, 'updateSettings'])->name('settings.update');
    });
    Route::middleware('can:users.manage')->group(function () {
        Route::get('/users', [Admin\SystemController::class, 'users'])->middleware('password.confirm')->name('users.index');
        Route::post('/users', [Admin\SystemController::class, 'storeUser'])->name('users.store');
        Route::put('/users/{user}', [Admin\SystemController::class, 'updateUser'])->name('users.update');
    });
});
