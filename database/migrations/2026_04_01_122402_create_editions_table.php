<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('editions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('expression_id');
            $table->foreign('expression_id')
                ->references('id')
                ->on('expressions')
                ->cascadeOnDelete();
            
            $table->uuid('publisher_id');
            $table->foreign('publisher_id')
                ->references('id')
                ->on('publishers')
                ->cascadeOnDelete();
            
            // ISBN fields
            $table->char('isbn13', 13)->nullable()->unique();  // Primary identifier
            $table->char('isbn10', 10)->nullable();            // Legacy, for reference
            
            // Publication info
            $table->date('publication_date')->nullable();
            $table->smallInteger('publication_year')->nullable()->index();  // Extracted for filtering
            
            // Physical attributes
            $table->enum('format', [
                'hardcover',
                'paperback',
                'ebook',
                'audiobook'
            ])->default('paperback');
            
            $table->smallInteger('pages')->nullable();
            $table->string('cover_url')->nullable();
            
            // Where did this record come from?
            $table->string('source_system');     // 'biblionet', 'onix', 'manual'
            $table->string('source_record_id');  // Original ID in source system
            
            $table->timestamps();
            
            // For ISBN-less editions: prevent duplicates
            $table->unique(
                ['publisher_id', 'expression_id', 'publication_year', 'format'],
                'editions_no_isbn_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('editions');
    }
};