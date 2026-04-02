<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('raw_ingestion_records', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Which provider?
            $table->string('source_system');  // 'biblionet', 'nlg', 'onix', etc.
            
            // The ID from the source system (unique per source, not globally)
            $table->string('source_record_id');
            
            // The complete raw response (JSON, XML converted to JSON, whatever)
            $table->json('payload');
            
            // Processing status
            $table->enum('status', [
                'pending',
                'processing',
                'completed',
                'failed'
            ])->default('pending');
            
            // Link to provenance batch
            $table->uuid('provenance_id')->nullable();
            $table->foreign('provenance_id')
                ->references('id')
                ->on('provenance')
                ->nullOnDelete();
            
            // If processing created an edition, link it
            $table->uuid('edition_id')->nullable();
            $table->foreign('edition_id')
                ->references('id')
                ->on('editions')
                ->nullOnDelete();
            
            // Error handling
            $table->text('error_message')->nullable();
            $table->unsignedTinyInteger('retry_count')->default(0);
            
            // Timing
            $table->timestamp('fetched_at');
            $table->timestamp('processed_at')->nullable();
            
            $table->timestamps();
            
            // Unique per source system (BIBLIONET record 123 ≠ NLG record 123)
            $table->unique(['source_system', 'source_record_id']);
            
            // For processing queue
            $table->index(['status', 'source_system', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('raw_ingestion_records');
    }
};