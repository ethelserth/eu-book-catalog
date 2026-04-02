<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expressions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('work_id');
            $table->foreign('work_id')
                ->references('id')
                ->on('works')
                ->cascadeOnDelete();
            
            // Language of THIS expression (might differ from work's original language)
            $table->char('language', 3);  // ISO 639-2
            
            // Title in this expression's language
            // "Freedom or Death" for the English expression of "Ο Καπετάν Μιχάλης"
            $table->string('title');
            
            // What kind of expression is this?
            $table->enum('expression_type', [
                'original',     // The original text
                'translation',  // Translation to another language
                'adaptation',   // Significant changes (children's version, etc.)
                'abridgment'    // Shortened version
            ])->default('original');
            
            $table->timestamps();
            
            // Common query: find expressions of a work in a specific language
            $table->index(['work_id', 'language']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expressions');
    }
};