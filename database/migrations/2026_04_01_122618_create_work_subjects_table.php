<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_subjects', function (Blueprint $table) {
            $table->uuid('work_id');
            $table->string('thema_code', 10);
            
            $table->foreign('work_id')
                ->references('id')
                ->on('works')
                ->cascadeOnDelete();
                
            $table->foreign('thema_code')
                ->references('code')
                ->on('thema_subjects')
                ->cascadeOnDelete();
            
            $table->primary(['work_id', 'thema_code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_subjects');
    }
};