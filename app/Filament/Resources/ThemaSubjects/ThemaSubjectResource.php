<?php

namespace App\Filament\Resources\ThemaSubjects;

use App\Filament\Resources\ThemaSubjects\Pages\CreateThemaSubject;
use App\Filament\Resources\ThemaSubjects\Pages\EditThemaSubject;
use App\Filament\Resources\ThemaSubjects\Pages\ListThemaSubjects;
use App\Filament\Resources\ThemaSubjects\Pages\ViewThemaSubject;
use App\Filament\Resources\ThemaSubjects\Schemas\ThemaSubjectForm;
use App\Filament\Resources\ThemaSubjects\Schemas\ThemaSubjectInfolist;
use App\Filament\Resources\ThemaSubjects\Tables\ThemaSubjectsTable;
use App\Models\ThemaSubject;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ThemaSubjectResource extends Resource
{
    protected static ?string $model = ThemaSubject::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTag;

    protected static \UnitEnum|string|null $navigationGroup = 'Classification';

    protected static ?int $navigationSort = 1;

    protected static ?string $recordTitleAttribute = 'heading_en';

    public static function form(Schema $schema): Schema
    {
        return ThemaSubjectForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ThemaSubjectInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ThemaSubjectsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListThemaSubjects::route('/'),
            'create' => CreateThemaSubject::route('/create'),
            'view' => ViewThemaSubject::route('/{record}'),
            'edit' => EditThemaSubject::route('/{record}/edit'),
        ];
    }
}
