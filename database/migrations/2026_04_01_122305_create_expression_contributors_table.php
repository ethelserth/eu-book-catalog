<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expression_contributors', function (Blueprint $table) {
            $table->uuid('expression_id');
            $table->uuid('author_id');
            
            // Roles specific to expressions (not the original work)
            $table->enum('role', [
                'translator',
                'editor',
                'illustrator',
                'narrator',      // For audiobooks
                'introduction'   // Wrote the introduction
            ]);
            
            $table->foreign('expression_id')
                ->references('id')
                ->on('expressions')
                ->cascadeOnDelete();
                
            $table->foreign('author_id')
                ->references('id')
                ->on('authors')
                ->cascadeOnDelete();
            
            $table->primary(['expression_id', 'author_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('expression_contributors');
    }
};