<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials\Pages;

use App\Filament\Resources\ProviderCredentials\ProviderCredentialResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProviderCredential extends EditRecord
{
    protected static string $resource = ProviderCredentialResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
