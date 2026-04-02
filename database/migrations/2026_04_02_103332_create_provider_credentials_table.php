<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('provider_credentials', function (Blueprint $table) {
            $table->uuid('id')->primary();

            // Machine identifier: 'biblionet', 'openlibrary', 'worldcat', etc.
            // Unique — one credential record per provider.
            $table->string('provider')->unique();

            // Human-readable label shown in the admin panel.
            $table->string('label');

            // Whether this provider is enabled for ingestion.
            $table->boolean('is_active')->default(false);

            // Provider-specific credentials (client_id, client_secret, api_key, etc.).
            // Stored encrypted using APP_KEY — plaintext never touches the DB.
            $table->text('credentials')->nullable();

            // Non-secret provider settings (rate_limit, base_url overrides, etc.).
            $table->json('settings')->nullable();

            // Whether to include this provider in automated scheduled syncs.
            $table->boolean('auto_sync')->default(false);

            // Audit timestamps.
            $table->timestamp('last_tested_at')->nullable();
            $table->timestamp('last_ingestion_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('provider_credentials');
    }
};
