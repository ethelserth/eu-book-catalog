<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Enums\ProviderType;
use App\Models\ProviderCredential;
use App\Models\User;
use Filament\Notifications\Notification;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Artisan;

class SyncProviderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Allow up to 10 minutes — a full historical fetch can take several minutes.
     * This must be higher than the `catalog:sync` command's own internal timeouts.
     */
    public int $timeout = 600;

    /**
     * Do not retry automatically — a failed sync should be re-triggered manually
     * from the admin panel so the operator can check logs first.
     */
    public int $tries = 1;

    public function __construct(
        private readonly ProviderType $provider,
        private readonly bool $forceFull,
        private readonly int $triggeredByUserId,
    ) {}

    public function handle(): void
    {
        $args = [
            '--provider' => $this->provider->value,
            '--force' => true,
        ];

        if ($this->forceFull) {
            $args['--full'] = true;
        }

        $exitCode = Artisan::call('catalog:sync', $args);
        $credential = ProviderCredential::forProvider($this->provider);
        $user = User::find($this->triggeredByUserId);

        if (! $user) {
            return;
        }

        $label = $credential?->label ?? $this->provider;

        if ($exitCode === 0) {
            $lastSync = $credential?->fresh()?->last_ingestion_at?->diffForHumans() ?? 'just now';

            Notification::make()
                ->title("{$label}: sync completed")
                ->body("Last ingestion: {$lastSync}")
                ->success()
                ->sendToDatabase($user);
        } else {
            Notification::make()
                ->title("{$label}: sync failed")
                ->body('Check storage/logs/laravel.log for details.')
                ->danger()
                ->sendToDatabase($user);
        }
    }
}
