<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Provider credentials live in environment variables only (config/services.php).
        // This table stores non-secret configuration and admin toggles.
        Schema::create('payment_providers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name');
            $table->boolean('payin_enabled')->default(false);
            $table->boolean('payout_enabled')->default(false);
            $table->boolean('refund_enabled')->default(false);
            $table->unsignedSmallInteger('priority')->default(100);
            $table->json('settings')->nullable();
            // unknown | healthy | degraded | down | unconfigured
            $table->string('health_status', 20)->default('unknown');
            $table->string('health_message')->nullable();
            $table->timestamp('health_checked_at')->nullable();
            $table->timestamps();
        });

        Schema::create('supported_currencies', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('payment_providers')->cascadeOnDelete();
            // payin | payout
            $table->string('direction', 10);
            $table->string('currency', 16);
            // Empty string = provider default / single-network asset.
            $table->string('network', 32)->default('');
            $table->decimal('min_amount', 30, 8)->nullable();
            $table->decimal('max_amount', 30, 8)->nullable();
            // Provider commission as published by the provider (informational / estimates).
            $table->decimal('provider_fee_percent', 10, 4)->nullable();
            $table->decimal('provider_fee_fixed', 30, 8)->nullable();
            $table->boolean('is_available')->default(true);
            $table->boolean('is_enabled')->default(true);
            // provider_api | admin
            $table->string('source', 20)->default('admin');
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
            $table->unique(['provider_id', 'direction', 'currency', 'network'], 'supported_currencies_unique');
            $table->index(['direction', 'currency']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('supported_currencies');
        Schema::dropIfExists('payment_providers');
    }
};
