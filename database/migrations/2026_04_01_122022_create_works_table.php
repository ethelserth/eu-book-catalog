<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('works', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Title in the original language
            $table->string('original_title');
            
            // ISO 639-2 three-letter code (ell, eng, fra)
            // Three letters give better coverage than two-letter ISO 639-1
            $table->char('original_language', 3);
            
            $table->text('description')->nullable();
            
            // For sorting/filtering - year the work was first published anywhere
            $table->smallInteger('first_publication_year')->nullable();
            
            // External identifiers for federation
            $table->string('wikidata_id')->nullable()->index();  // Q12345
            $table->string('oclc_work_id')->nullable()->index(); // OCLC Work ID
            
            $table->timestamps();
            
            // Index for searching by title
            $table->index('original_title');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('works');
    }
};