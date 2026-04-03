<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('raw_ingestion_records', function (Blueprint $table) {
            // Provider-vocabulary type: 'edition', 'work', 'author', 'book', etc.
            // Nullable for backward-compat with existing staged records (pre-migration).
            // The normaliser uses this to route records to the correct mapper method.
            $table->string('record_type')->nullable()->after('source_record_id');

            // Index alongside status for efficient normaliser queries:
            // WHERE status = 'pending' AND source_system = 'openlibrary' AND record_type = 'edition'
            $table->index(['status', 'source_system', 'record_type']);
        });
    }

    public function down(): void
    {
        Schema::table('raw_ingestion_records', function (Blueprint $table) {
            $table->dropIndex(['status', 'source_system', 'record_type']);
            $table->dropColumn('record_type');
        });
    }
};
