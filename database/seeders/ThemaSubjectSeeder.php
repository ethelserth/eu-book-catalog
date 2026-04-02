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
                "Download it from https://www.editeur.org/151/Thema/ and save as storage/thema/thema_en.json"
            );
        }

        $this->command->info('Loading Thema JSON...');
        $json = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        $codes = $json['ThemaCodes'] ?? $json['CodeList']['ThemaCodes'] ?? null;

        if (! $codes) {
            throw new RuntimeException('Could not find ThemaCodes in JSON. Check file structure.');
        }

        $this->command->info('Found ' . count($codes) . ' Thema codes.');

        // Sort by code length so parents always come before children.
        // (A before AB before ABC etc.)
        usort($codes, fn($a, $b) => strlen($a['CodeValue']) <=> strlen($b['CodeValue']));

        // Disable FK checks during bulk insert to avoid ordering issues.
        DB::statement('SET CONSTRAINTS ALL DEFERRED');

        $this->command->info('Truncating thema_subjects table...');
        // We use a raw delete to avoid issues with the self-referential FK.
        DB::table('thema_subjects')->delete();

        $this->command->info('Inserting Thema codes...');
        $bar = $this->command->getOutput()->createProgressBar(count($codes));
        $bar->start();

        $batch = [];
        $batchSize = 200;

        foreach ($codes as $code) {
            $codeValue = trim($code['CodeValue']);
            $parent    = trim($code['CodeParent'] ?? '');
            $heading   = trim($code['CodeDescription'] ?? $code['CodeHeading'] ?? '');

            if ($codeValue === '' || $heading === '') {
                $bar->advance();
                continue;
            }

            $batch[] = [
                'code'        => $codeValue,
                'parent_code' => $parent !== '' ? $parent : null,
                'heading_en'  => $heading,
                'heading_el'  => null, // Phase 3 Step 58: Greek headings added later
                'level'       => strlen($codeValue) - 1, // A=0, AB=1, ABC=2, etc.
            ];

            if (count($batch) >= $batchSize) {
                DB::table('thema_subjects')->insert($batch);
                $batch = [];
            }

            $bar->advance();
        }

        // Insert remaining records.
        if (! empty($batch)) {
            DB::table('thema_subjects')->insert($batch);
        }

        $bar->finish();
        $this->command->newLine();

        $total = ThemaSubject::count();
        $this->command->info("Done. {$total} Thema subjects seeded.");
        $this->command->info('Root categories: ' . ThemaSubject::whereNull('parent_code')->count());
        $this->command->info('Tip: Run php artisan thema:update to refresh codes in future.');
    }
}
