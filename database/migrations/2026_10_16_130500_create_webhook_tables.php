<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Merchant-configured endpoints for outgoing notifications.
        Schema::create('webhook_endpoints', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 4);
            $table->string('url', 500);
            $table->string('description')->nullable();
            // Encrypted at rest (Laravel encrypter); needed in clear to sign payloads.
            $table->text('secret');
            // NULL = all events.
            $table->json('events')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->timestamps();
            $table->index(['merchant_id', 'mode', 'is_active']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('mode', 4);
            $table->string('type', 40)->index();
            $table->nullableMorphs('resource');
            $table->json('payload');
            // Prevents emitting the same event twice (e.g. "payment:15:payment.completed").
            $table->string('dedupe_key', 191)->unique();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['merchant_id', 'mode', 'created_at']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('webhook_endpoint_id')->constrained()->cascadeOnDelete();
            // pending | retrying | succeeded | failed
            $table->string('status', 20)->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('next_attempt_at')->nullable()->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->string('last_error', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
            $table->unique(['webhook_event_id', 'webhook_endpoint_id']);
        });

        Schema::create('webhook_delivery_attempts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('webhook_delivery_id')->constrained()->cascadeOnDelete();
            $table->unsignedSmallInteger('attempt');
            $table->unsignedSmallInteger('response_status')->nullable();
            $table->text('response_body')->nullable();
            $table->string('error', 500)->nullable();
            $table->unsignedInteger('duration_ms')->nullable();
            $table->foreignId('triggered_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();
        });

        // Raw inbound provider callbacks, stored before processing.
        Schema::create('provider_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('provider_id')->constrained('payment_providers')->restrictOnDelete();
            $table->string('event_type', 40)->nullable();
            $table->string('external_id', 100)->nullable()->index();
            $table->char('payload_hash', 64);
            $table->json('headers')->nullable();
            $table->longText('raw_body');
            $table->string('ip_address', 45)->nullable();
            $table->boolean('signature_valid')->default(false);
            // received | processed | ignored | failed | rejected | duplicate
            $table->string('status', 20)->index();
            $table->string('error', 500)->nullable();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();
            $table->index(['provider_id', 'payload_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provider_webhook_logs');
        Schema::dropIfExists('webhook_delivery_attempts');
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('webhook_endpoints');
    }
};
