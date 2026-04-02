<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // First create the table without the foreign key
        Schema::create('thema_subjects', function (Blueprint $table) {
            $table->string('code', 10)->primary();
            $table->string('parent_code', 10)->nullable()->index();
            $table->string('heading_en');
            $table->string('heading_el')->nullable();
            $table->tinyInteger('level')->default(0);
        });
        
        // Then add the self-referential foreign key
        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->foreign('parent_code')
                ->references('code')
                ->on('thema_subjects')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('thema_subjects');
    }
};