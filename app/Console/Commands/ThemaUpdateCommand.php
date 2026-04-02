<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\ThemaSubjectSeeder;
use Illuminate\Console\Command;
use Illuminate\Database\Seeder;

class ThemaUpdateCommand extends Command
{
    protected $signature = 'thema:update
                            {--path= : Override the JSON file path (relative to storage/)}';

    protected $description = 'Import or refresh Thema subject codes from a JSON file';

    public function handle(): int
    {
        $this->info('Thema Subject Updater');
        $this->info('====================');
        $this->newLine();

        if (! $this->confirm('This will DELETE all existing Thema codes and re-import. Continue?', true)) {
            $this->warn('Aborted.');
            return self::FAILURE;
        }

        $seeder = new ThemaSubjectSeeder();
        $seeder->setCommand($this);
        $seeder->run();

        $this->newLine();
        $this->info('Thema update complete.');

        return self::SUCCESS;
    }
}
