<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('publisher_name_variants', function (Blueprint $table) {
            $table->uuid('id')->primary();
            
            $table->uuid('publisher_id');
            $table->foreign('publisher_id')
                ->references('id')
                ->on('publishers')
                ->cascadeOnDelete();
            
            $table->string('name')->index();
            $table->string('source');
            
            $table->timestamps();
            
            $table->unique(['publisher_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('publisher_name_variants');
    }
};