<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The original schema used enum('source_system', ['biblionet','nlg','onix','manual']).
     * PostgreSQL implements enum as VARCHAR + CHECK constraint — adding new providers
     * requires dropping and recreating that constraint every time.
     *
     * Since we are now multi-provider (OpenLibrary, future WorldCat, etc.), the
     * hardcoded list is wrong. We drop the CHECK constraint and leave it as a
     * plain string — provider names are enforced at the application layer instead.
     */
    public function up(): void
    {
        // Drop the enum CHECK constraint and re-declare as unconstrained string.
        // In PostgreSQL, "changing" an enum column requires dropping the constraint
        // explicitly via raw SQL — Laravel's Blueprint doesn't expose this directly.
        DB::statement('ALTER TABLE provenance DROP CONSTRAINT IF EXISTS provenance_source_system_check');
        DB::statement('ALTER TABLE provenance ALTER COLUMN source_system TYPE VARCHAR(255)');
    }

    public function down(): void
    {
        // Restore the original CHECK constraint (only covers the original providers).
        DB::statement("
            ALTER TABLE provenance
            ADD CONSTRAINT provenance_source_system_check
            CHECK (source_system IN ('biblionet','nlg','onix','manual'))
        ");
    }
};
