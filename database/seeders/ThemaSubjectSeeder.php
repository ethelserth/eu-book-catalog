<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\ThemaSubject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class ThemaSubjectSeeder extends Seeder
{
    /**
     * Path to the Thema JSON file (relative to storage_path()).
     * Download from: https://www.editeur.org/151/Thema/
     * Save as: storage/thema/thema_en.json
     */
    private const JSON_PATH = 'thema/thema_en.json';

    public function run(): void
    {
        $path = storage_path(self::JSON_PATH);

        if (! file_exists($path)) {
            throw new RuntimeException(
                "Thema JSON not found at {$path}.\n" .
                'Download it from https://www.editeur.org/151/Thema/ and save as storage/thema/thema_en.json'
            );
        }

        $this->command->info('Loading Thema JSON...');
        $json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        // Handle both flat and nested structures:
        // v1.5: { "ThemaCodes": [...] }
        // v1.6: { "CodeList": { "ThemaCodes": { "Code": [...] } } }
        $codes = $json['ThemaCodes']
            ?? $json['CodeList']['ThemaCodes']['Code']
            ?? $json['CodeList']['ThemaCodes']
            ?? null;

        if (! $codes) {
            throw new RuntimeException('Could not find ThemaCodes in JSON. Check file structure.');
        }

        $this->command->info('Found ' . count($codes) . ' Thema codes.');

        $this->command->info('Truncating thema_subjects table...');
        // Delete all rows — safe because we re-insert everything below.
        // Using DB::table() bypasses Eloquent events, which is fine for bulk ops.
        DB::table('thema_subjects')->delete();

        // ----------------------------------------------------------------
        // Two-pass insert strategy
        //
        // Why not sort by code length?
        // Thema v1.6 has codes like 1FPCT (len 5) whose parent is 1FPC-CN-N
        // (len 9). Simple length-sort fails for these hyphenated hierarchy
        // codes. Topological sort would also work but adds complexity.
        //
        // Two-pass is simpler:
        //   Pass 1 — insert every row with parent_code = null (no FK risk).
        //   Pass 2 — batch-update parent_code to the actual value.
        // ----------------------------------------------------------------

        $this->command->info('Pass 1: inserting all codes (no parent links)...');

        $rows = [];
        $parentMap = []; // code => parent_code (for pass 2)

        foreach ($codes as $code) {
            $codeValue = trim((string) ($code['CodeValue'] ?? ''));
            // CodeParent may be an integer in v1.6 JSON (e.g. 1A -> parent 1)
            $parent  = trim((string) ($code['CodeParent'] ?? ''));
            $heading = trim((string) ($code['CodeDescription'] ?? $code['CodeHeading'] ?? ''));

            if ($codeValue === '' || $heading === '') {
                continue;
            }

            $rows[] = [
                'code'        => $codeValue,
                'parent_code' => null, // deliberately null in pass 1
                'heading_en'  => $heading,
                'heading_el'  => null, // Greek headings added later (Step 58)
                'level'       => strlen($codeValue) - 1, // A=0, AB=1, ABC=2…
            ];

            if ($parent !== '') {
                $parentMap[$codeValue] = $parent;
            }
        }

        // Insert in chunks of 500. Laravel's chunk insert is just array_chunk
        // + multiple INSERT statements — efficient and memory-friendly.
        $bar = $this->command->getOutput()->createProgressBar(count($rows));
        $bar->start();

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('thema_subjects')->insert($chunk);
            $bar->advance(count($chunk));
        }

        $bar->finish();
        $this->command->newLine();

        // ----------------------------------------------------------------
        // Pass 2: set parent_code for all codes that have one.
        // We batch these as CASE…WHEN updates to avoid N+1 queries.
        // ----------------------------------------------------------------

        $this->command->info('Pass 2: linking parent codes (' . count($parentMap) . ' records)...');

        $bar2 = $this->command->getOutput()->createProgressBar(count($parentMap));
        $bar2->start();

        foreach (array_chunk(array_keys($parentMap), 500) as $chunkKeys) {
            foreach ($chunkKeys as $childCode) {
                DB::table('thema_subjects')
                    ->where('code', $childCode)
                    ->update(['parent_code' => $parentMap[$childCode]]);
            }
            $bar2->advance(count($chunkKeys));
        }

        $bar2->finish();
        $this->command->newLine();

        $total = ThemaSubject::count();
        $this->command->info("Done. {$total} Thema subjects seeded.");
        $this->command->info('Root categories: ' . ThemaSubject::whereNull('parent_code')->count());
        $this->command->info('Tip: Run php artisan thema:update to refresh codes in future.');
    }
}
