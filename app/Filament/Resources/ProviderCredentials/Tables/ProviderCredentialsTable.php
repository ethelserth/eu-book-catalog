<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials\Tables;

use App\Jobs\SyncProviderJob;
use App\Models\ProviderCredential;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;

class ProviderCredentialsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('label')
                    ->label('Provider')
                    ->searchable()
                    ->weight('bold'),

                TextColumn::make('provider')
                    ->badge()
                    ->color('gray'),

                // ToggleColumn lets the admin flip is_active/auto_sync
                // directly in the table row — no need to open the Edit page.
                ToggleColumn::make('is_active')
                    ->label('Active'),

                ToggleColumn::make('auto_sync')
                    ->label('Auto Sync'),

                TextColumn::make('last_ingestion_at')
                    ->label('Last Sync')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Never'),

                TextColumn::make('updated_at')
                    ->label('Credentials Updated')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('label')
            ->recordActions([
                Action::make('sync')
                    ->label('Sync')
                    ->icon('heroicon-o-arrow-down-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading(fn (ProviderCredential $record): string => "Sync {$record->label}")
                    ->modalDescription('Queues an incremental fetch (or full if never synced). You will receive a notification when it completes.')
                    ->action(function (ProviderCredential $record): void {
                        dispatch(new SyncProviderJob(
                            provider: $record->provider,
                            forceFull: false,
                            triggeredByUserId: Auth::id(),
                        ));

                        Notification::make()
                            ->title("{$record->label}: sync queued")
                            ->body("You'll receive a notification when it completes.")
                            ->info()
                            ->send();
                    }),

                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
