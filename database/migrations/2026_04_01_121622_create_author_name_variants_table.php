<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('author_name_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            // Foreign key to authors table
            $table->uuid('author_id');
            $table->foreign('author_id')
                ->references('id')
                ->on('authors')
                ->cascadeOnDelete();  // If author is deleted, delete their variants too
            
            // The variant name form
            $table->string('name')->index();  // "Νίκος Καζαντζάκης", "Kazantzakis, N.", etc.
            
            // What script/alphabet is this name in?
            $table->enum('script', ['latin', 'greek', 'cyrillic', 'other'])->default('latin');
            
            // Where did we encounter this variant?
            $table->string('source');  // "biblionet", "viaf", "manual", etc.
            
            $table->timestamps();
            
            // Prevent duplicate variants for same author
            $table->unique(['author_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('author_name_variants');
    }
};