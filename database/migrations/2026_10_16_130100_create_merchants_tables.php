<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('merchants', function (Blueprint $table) {
            $table->id();
            $table->string('public_id', 40)->unique();
            $table->string('name');
            $table->string('business_email');
            $table->string('business_website')->nullable();
            // pending | active | restricted | suspended
            $table->string('status', 20)->default('pending')->index();
            // unverified | submitted | approved | rejected
            $table->string('verification_status', 20)->default('unverified')->index();
            $table->boolean('live_enabled')->default(false);
            $table->timestamp('live_enabled_at')->nullable();
            $table->string('status_reason')->nullable();
            $table->foreignId('owner_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('merchant_id')->references('id')->on('merchants')->nullOnDelete();
        });

        Schema::create('merchant_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('legal_name')->nullable();
            $table->string('registration_number', 100)->nullable();
            $table->char('country', 2)->nullable();
            $table->string('address_line1')->nullable();
            $table->string('address_line2')->nullable();
            $table->string('city', 120)->nullable();
            $table->string('state', 120)->nullable();
            $table->string('postal_code', 32)->nullable();
            $table->string('contact_name')->nullable();
            $table->string('contact_phone', 40)->nullable();
            $table->string('support_email')->nullable();
            $table->text('business_description')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_verifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            // submitted | approved | rejected
            $table->string('status', 20)->default('submitted')->index();
            $table->json('submitted_data');
            $table->string('document_path')->nullable();
            $table->string('document_original_name')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_note')->nullable();
            $table->timestamp('reviewed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('merchant_limits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            // payin | payout
            $table->string('direction', 10);
            $table->string('currency', 16);
            $table->decimal('max_per_transaction', 30, 8)->nullable();
            $table->decimal('max_daily_total', 30, 8)->nullable();
            $table->timestamps();
            $table->unique(['merchant_id', 'direction', 'currency']);
        });

        Schema::create('merchant_api_keys', function (Blueprint $table) {
            $table->id();
            $table->foreignId('merchant_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            // test | live
            $table->string('mode', 4)->index();
            $table->string('key_id', 64)->unique();
            // HMAC-SHA256(pepper, secret) - the plain secret is never stored.
            $table->char('secret_hash', 64)->unique();
            $table->string('secret_last4', 4);
            $table->json('scopes');
            $table->json('ip_allowlist')->nullable();
            $table->unsignedInteger('rate_limit_per_minute')->default(120);
            $table->timestamp('last_used_at')->nullable();
            $table->string('last_used_ip', 45)->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('rotated_from_id')->nullable()->constrained('merchant_api_keys')->nullOnDelete();
            $table->timestamps();
            $table->index(['merchant_id', 'mode', 'revoked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('merchant_api_keys');
        Schema::dropIfExists('merchant_limits');
        Schema::dropIfExists('merchant_verifications');
        Schema::dropIfExists('merchant_profiles');
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['merchant_id']);
        });
        Schema::dropIfExists('merchants');
    }
};
