<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_authors', function (Blueprint $table) {
            // No separate primary key - the combination IS the identity
            $table->uuid('work_id');
            $table->uuid('author_id');
            
            // What role did this author play?
            $table->enum('role', ['author', 'co_author', 'editor', 'compiler'])->default('author');
            
            // For ordering: first author, second author, etc.
            $table->tinyInteger('position')->default(0);
            
            // Foreign keys
            $table->foreign('work_id')
                ->references('id')
                ->on('works')
                ->cascadeOnDelete();
                
            $table->foreign('author_id')
                ->references('id')
                ->on('authors')
                ->cascadeOnDelete();
            
            // Composite primary key
            $table->primary(['work_id', 'author_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_authors');
    }
};