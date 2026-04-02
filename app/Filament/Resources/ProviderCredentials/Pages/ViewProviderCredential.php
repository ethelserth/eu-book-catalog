<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials\Pages;

use App\Filament\Resources\ProviderCredentials\ProviderCredentialResource;
use App\Jobs\SyncProviderJob;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Facades\Auth;

class ViewProviderCredential extends ViewRecord
{
    protected static string $resource = ProviderCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // Incremental fetch — auto-detects full vs delta based on last_ingestion_at.
            // If never synced → full historical. If synced before → yesterday's changes only.
            // Dispatches a background job — returns immediately with a toast.
            // The job sends a DB notification (bell icon) when it finishes.
            Action::make('fetchChanges')
                ->label('Fetch Changes')
                ->icon('heroicon-o-arrow-down-circle')
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('Fetch Changes')
                ->modalDescription(
                    'Queues an incremental sync for this provider. '.
                    'If this provider has never been synced it will perform a full historical fetch. '.
                    'You will receive a notification when it completes.'
                )
                ->action(function (): void {
                    dispatch(new SyncProviderJob(
                        provider: $this->record->provider,
                        forceFull: false,
                        triggeredByUserId: Auth::id(),
                    ));

                    Notification::make()
                        ->title('Sync queued')
                        ->body("Fetching changes for {$this->record->label}. You'll be notified when done.")
                        ->info()
                        ->send();
                }),

            Action::make('forceFull')
                ->label('Force Full Sync')
                ->icon('heroicon-o-arrow-path')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Force Full Sync')
                ->modalDescription(
                    'Re-fetches all records from full_sync_from to today. This can take several minutes. '.
                    'Only use this if you suspect the incremental state is corrupted. '.
                    'You will receive a notification when it completes.'
                )
                ->action(function (): void {
                    dispatch(new SyncProviderJob(
                        provider: $this->record->provider,
                        forceFull: true,
                        triggeredByUserId: Auth::id(),
                    ));

                    Notification::make()
                        ->title('Full sync queued')
                        ->body("Full historical fetch for {$this->record->label} is running in the background.")
                        ->info()
                        ->send();
                }),

            EditAction::make(),
        ];
    }
}
