<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('authors', function (Blueprint $table) {
            // Primary key: UUID instead of auto-increment
            $table->uuid('id')->primary();
            
            // Core fields
            $table->string('display_name');           // "Nikos Kazantzakis"
            $table->string('sort_name')->index();     // "Kazantzakis, Nikos" for alphabetical sorting
            
            // Biographical data (nullable - we won't always have this)
            $table->smallInteger('birth_year')->nullable();
            $table->smallInteger('death_year')->nullable();
            $table->char('nationality', 2)->nullable();  // ISO 3166-1 alpha-2 (GR, FR, US)
            
            // External authority identifiers
            $table->string('viaf_id')->nullable()->index();      // Virtual International Authority File
            $table->char('isni', 16)->nullable()->index();       // International Standard Name Identifier
            $table->string('wikidata_id')->nullable()->index();  // Wikidata Q-ID (Q185085)
            
            // Authority matching metadata
            $table->decimal('authority_confidence', 3, 2)->default(0);  // 0.00 to 1.00
            $table->boolean('needs_review')->default(false)->index();
            
            // Timestamps
            $table->timestamps();  // created_at and updated_at
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('authors');
    }
};