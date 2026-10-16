<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Provider API credentials entered in the admin panel, encrypted with APP_KEY.
     * Values here override the matching environment variables.
     */
    public function up(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->text('credentials')->nullable()->after('settings');
            $table->timestamp('credentials_updated_at')->nullable()->after('credentials');
        });
    }

    public function down(): void
    {
        Schema::table('payment_providers', function (Blueprint $table) {
            $table->dropColumn(['credentials', 'credentials_updated_at']);
        });
    }
};
