<?php

declare(strict_types=1);

namespace App\Filament\Resources\ProviderCredentials;

use App\Filament\Resources\ProviderCredentials\Pages\EditProviderCredential;
use App\Filament\Resources\ProviderCredentials\Pages\ListProviderCredentials;
use App\Filament\Resources\ProviderCredentials\Pages\ViewProviderCredential;
use App\Filament\Resources\ProviderCredentials\Schemas\ProviderCredentialForm;
use App\Filament\Resources\ProviderCredentials\Schemas\ProviderCredentialInfolist;
use App\Filament\Resources\ProviderCredentials\Tables\ProviderCredentialsTable;
use App\Models\ProviderCredential;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ProviderCredentialResource extends Resource
{
    protected static ?string $model = ProviderCredential::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static \UnitEnum|string|null $navigationGroup = 'Settings';

    protected static ?string $navigationLabel = 'Data Providers';

    protected static ?string $modelLabel = 'Data Provider';

    protected static ?string $pluralModelLabel = 'Data Providers';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'label';

    public static function form(Schema $schema): Schema
    {
        return ProviderCredentialForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ProviderCredentialInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ProviderCredentialsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProviderCredentials::route('/'),
            'view' => ViewProviderCredential::route('/{record}'),
            'edit' => EditProviderCredential::route('/{record}/edit'),
        ];
    }
}
