<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_queue', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // What type of entity needs review?
            $table->enum('entity_type', [
                'author',
                'work',
                'edition',
                'publisher'
            ]);
            
            // Which specific entity?
            $table->uuid('entity_id');
            
            // What's the problem?
            $table->enum('issue_type', [
                'low_confidence_match',  // Authority matching uncertain
                'possible_duplicate',    // Might be duplicate of another record
                'missing_authority',     // No VIAF/Wikidata link found
                'data_conflict',         // Sources disagree
                'missing_required'       // Required field is empty
            ]);
            
            // Context for the reviewer
            // e.g., {"matched_viaf_id": "12345", "confidence": 0.65, "reason": "name matched but dates differ"}
            $table->json('details')->nullable();
            
            // Workflow status
            $table->enum('status', [
                'pending',   // Awaiting review
                'resolved',  // Reviewer took action
                'ignored'    // Reviewer decided to skip
            ])->default('pending');
            
            // Who resolved it? (nullable until resolved)
            // References Laravel's default users table
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            
            $table->timestamp('created_at');
            $table->timestamp('resolved_at')->nullable();
            
            // Indexes for common queries
            $table->index(['status', 'entity_type']);  // "Show pending author issues"
            $table->index(['entity_type', 'entity_id']);  // "Show all issues for this author"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('review_queue');
    }
};