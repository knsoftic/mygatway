<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('merchant_api_keys')->nullOnDelete();
            $table->string('mode', 4);
            $table->foreignId('provider_id')->constrained('payment_providers')->restrictOnDelete();
            // Merchant's own order reference - unique per merchant and mode.
            $table->string('merchant_reference', 64);
            $table->string('description')->nullable();
            $table->decimal('amount', 30, 8);
            $table->string('currency', 16);
            $table->string('network', 32)->nullable();
            // Filled only from verified provider data.
            $table->decimal('received_amount', 30, 8)->nullable();
            $table->string('received_currency', 16)->nullable();
            $table->decimal('service_fee', 30, 8)->default(0);
            $table->decimal('provider_fee', 30, 8)->nullable();
            $table->decimal('network_fee', 30, 8)->nullable();
            $table->decimal('merchant_net_amount', 30, 8)->nullable();
            $table->string('status', 20)->index();
            $table->string('provider_status', 40)->nullable();
            // ID we send to the provider (order_id / merchantTradeNo). Letters+digits, <= 32 chars.
            $table->string('provider_order_id', 64)->unique();
            // Provider's own ID (Cryptomus uuid / Binance prepayId).
            $table->string('provider_payment_id', 64)->nullable();
            $table->text('checkout_url')->nullable();
            $table->string('pay_address', 191)->nullable();
            $table->string('payer_address', 191)->nullable();
            $table->string('tx_hash', 191)->nullable();
            $table->string('customer_email')->nullable();
            $table->string('success_url', 255)->nullable();
            $table->string('cancel_url', 255)->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('callback_verified')->default(false);
            $table->timestamp('callback_verified_at')->nullable();
            $table->boolean('needs_review')->default(false)->index();
            $table->string('review_reason')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'mode', 'merchant_reference'], 'payments_merchant_reference_unique');
            $table->index(['merchant_id', 'mode', 'status', 'created_at']);
            $table->index(['provider_id', 'provider_payment_id']);
            $table->index(['mode', 'status', 'created_at']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->foreignId('api_key_id')->nullable()->constrained('merchant_api_keys')->nullOnDelete();
            $table->foreignId('requested_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('mode', 4);
            $table->foreignId('provider_id')->constrained('payment_providers')->restrictOnDelete();
            $table->string('merchant_reference', 64);
            $table->string('description')->nullable();
            $table->decimal('amount', 30, 8);
            $table->string('currency', 16);
            $table->string('network', 32)->nullable();
            // crypto_address | binance_id | email
            $table->string('destination_type', 20);
            $table->string('destination', 191);
            $table->string('destination_memo', 64)->nullable();
            $table->decimal('service_fee', 30, 8)->default(0);
            // Network/provider fee estimate charged to the merchant at request time.
            $table->decimal('network_fee', 30, 8)->default(0);
            $table->decimal('total_debit', 30, 8);
            // Actual provider commission, from verified provider data.
            $table->decimal('provider_fee', 30, 8)->nullable();
            $table->string('status', 20)->index();
            $table->string('provider_status', 40)->nullable();
            $table->string('provider_order_id', 64)->unique();
            $table->string('provider_payout_id', 64)->nullable();
            $table->string('tx_hash', 191)->nullable();
            $table->string('failure_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->boolean('needs_review')->default(false)->index();
            $table->string('review_reason')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('rejection_reason')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('failed_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['merchant_id', 'mode', 'merchant_reference'], 'payouts_merchant_reference_unique');
            $table->index(['merchant_id', 'mode', 'status', 'created_at']);
            $table->index(['mode', 'status', 'created_at']);
        });

        Schema::create('payout_approvals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payout_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            // approve | reject | cancel | retry | note
            $table->string('action', 20);
            $table->string('from_status', 20)->nullable();
            $table->string('to_status', 20)->nullable();
            $table->text('note')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
        });

        Schema::create('refunds', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('payment_id')->constrained()->restrictOnDelete();
            $table->foreignId('merchant_id')->constrained()->restrictOnDelete();
            $table->string('mode', 4);
            $table->foreignId('provider_id')->constrained('payment_providers')->restrictOnDelete();
            $table->decimal('amount', 30, 8);
            $table->string('currency', 16);
            // Cryptomus refunds require the customer's wallet address.
            $table->string('address', 191)->nullable();
            $table->string('reason')->nullable();
            // requested | processing | completed | failed
            $table->string('status', 20)->index();
            $table->string('provider_request_id', 64)->unique();
            $table->string('provider_refund_id', 64)->nullable();
            $table->string('provider_status', 40)->nullable();
            $table->string('failure_reason')->nullable();
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('refunds');
        Schema::dropIfExists('payout_approvals');
        Schema::dropIfExists('payouts');
        Schema::dropIfExists('payments');
    }
};
