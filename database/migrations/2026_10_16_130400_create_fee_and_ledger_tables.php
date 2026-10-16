<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('fee_rules', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            // payin | payout
            $table->string('direction', 10);
            // NULL = applies to every merchant / provider / currency.
            $table->foreignId('merchant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('payment_providers')->cascadeOnDelete();
            $table->string('currency', 16)->nullable();
            $table->decimal('percentage', 8, 4)->default(0);
            // Fixed / min / max amounts are only allowed on currency-specific rules.
            $table->decimal('fixed_amount', 30, 8)->default(0);
            $table->decimal('min_fee', 30, 8)->nullable();
            $table->decimal('max_fee', 30, 8)->nullable();
            $table->dateTime('effective_from');
            $table->dateTime('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['direction', 'is_active', 'effective_from']);
        });

        // Immutable snapshot of every fee charged; historical fees never change when rules change.
        Schema::create('transaction_fees', function (Blueprint $table) {
            $table->id();
            $table->morphs('feeable');
            // service | provider | network
            $table->string('kind', 20);
            // quote (at creation) | final (at settlement)
            $table->string('stage', 10);
            $table->foreignId('fee_rule_id')->nullable()->constrained()->nullOnDelete();
            $table->string('currency', 16);
            $table->decimal('base_amount', 30, 8);
            $table->decimal('percentage', 8, 4)->default(0);
            $table->decimal('fixed_amount', 30, 8)->default(0);
            $table->decimal('min_fee', 30, 8)->nullable();
            $table->decimal('max_fee', 30, 8)->nullable();
            $table->decimal('amount', 30, 8);
            $table->json('rule_snapshot')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['feeable_type', 'feeable_id', 'kind', 'stage'], 'transaction_fees_unique');
        });

        Schema::create('ledger_accounts', function (Blueprint $table) {
            $table->id();
            // Deterministic key, e.g. "live:USDT:merchant_available:m:12"
            $table->string('account_key', 191)->unique();
            $table->string('mode', 4);
            $table->string('currency', 16);
            $table->string('type', 40);
            $table->foreignId('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('provider_id')->nullable()->constrained('payment_providers')->restrictOnDelete();
            // debit | credit
            $table->string('normal_balance', 6);
            // Cached running balance; only LedgerService writes it, inside the same DB
            // transaction as the entries. Reconciliation verifies it equals SUM(entries).
            $table->decimal('balance', 30, 8)->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'mode', 'type']);
        });

        // Journal header: one balanced financial transaction.
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('mode', 4);
            $table->string('type', 40)->index();
            $table->nullableMorphs('reference');
            $table->string('currency', 16);
            $table->string('description')->nullable();
            // Guarantees a business event is posted at most once (e.g. "payment:15:settle").
            $table->string('idempotency_key', 191)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('posted_at')->useCurrent();
            $table->timestamps();
            $table->index(['merchant_id', 'mode', 'posted_at']);
        });

        Schema::create('ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('transaction_id')->constrained()->restrictOnDelete();
            $table->foreignId('ledger_account_id')->constrained()->restrictOnDelete();
            // debit | credit
            $table->string('direction', 6);
            $table->decimal('amount', 30, 8);
            $table->string('currency', 16);
            $table->decimal('balance_after', 30, 8);
            $table->timestamp('created_at')->useCurrent();
            $table->index(['ledger_account_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ledger_entries');
        Schema::dropIfExists('transactions');
        Schema::dropIfExists('ledger_accounts');
        Schema::dropIfExists('transaction_fees');
        Schema::dropIfExists('fee_rules');
    }
};
