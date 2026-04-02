<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Thema v1.6 introduced national extension codes up to 14 characters
     * (e.g. 1KBB-US-NAKCMG). The original schema used varchar(10), which
     * is too narrow. We widen both code columns to varchar(20) to give
     * headroom for any future Thema version.
     *
     * Note: Changing the length of a primary key in PostgreSQL requires
     * dropping the FK first, altering both columns, then re-adding the FK.
     */
    public function up(): void
    {
        // 1. Drop the self-referential FK so we can alter the referenced column.
        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->dropForeign(['parent_code']);
        });

        // 2. Widen both columns.
        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->string('code', 20)->change();
            $table->string('parent_code', 20)->nullable()->change();
        });

        // 3. Re-add the FK.
        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->foreign('parent_code')
                ->references('code')
                ->on('thema_subjects')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->dropForeign(['parent_code']);
        });

        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->string('code', 10)->change();
            $table->string('parent_code', 10)->nullable()->change();
        });

        Schema::table('thema_subjects', function (Blueprint $table) {
            $table->foreign('parent_code')
                ->references('code')
                ->on('thema_subjects')
                ->nullOnDelete();
        });
    }
};
