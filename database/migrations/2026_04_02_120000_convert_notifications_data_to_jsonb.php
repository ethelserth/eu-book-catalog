<?php

use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Laravel's notifications table creates `data` as TEXT.
     * Filament's database notification query uses the PostgreSQL JSON operator
     * (`data->>'format' = 'filament'`) which requires the column to be JSONB.
     * A TEXT column does not support ->> and throws "operator does not exist: text ->> unknown".
     *
     * Fix: cast the column to JSONB so PostgreSQL can parse and query the JSON payload.
     */
    public function up(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE jsonb USING data::jsonb');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE notifications ALTER COLUMN data TYPE text USING data::text');
    }
};
