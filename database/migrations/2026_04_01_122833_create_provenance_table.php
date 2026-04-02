<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provenance', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // What system did this come from?
            $table->enum('source_system', [
                'biblionet',
                'nlg',      // National Library of Greece
                'onix',     // Publisher ONIX feeds
                'manual'    // Manual entry
            ]);
            
            // Specific endpoint or file
            $table->string('source_url')->nullable();
            
            // Groups records from same import run
            // "biblionet-2026-04-01-143022"
            $table->string('batch_id')->index();
            
            // Timing
            $table->timestamp('ingestion_started_at');
            $table->timestamp('ingestion_completed_at')->nullable();
            
            // Statistics
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_failed')->default(0);
            
            // If something went wrong
            $table->text('error_log')->nullable();
            
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('provenance');
    }
};