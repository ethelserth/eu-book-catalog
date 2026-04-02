<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('edition_provenance_log', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('edition_id');
            $table->foreign('edition_id')
                ->references('id')
                ->on('editions')
                ->cascadeOnDelete();
            
            $table->uuid('provenance_id');
            $table->foreign('provenance_id')
                ->references('id')
                ->on('provenance')
                ->cascadeOnDelete();
            
            // What happened?
            $table->enum('action', [
                'created',  // New edition added
                'updated',  // Existing edition modified
                'merged'    // Merged with another edition (deduplication)
            ]);
            
            // What was there before? (for updates and merges)
            $table->json('previous_data')->nullable();
            
            $table->timestamp('created_at');
            
            // Index for "show me history of this edition"
            $table->index(['edition_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('edition_provenance_log');
    }
};