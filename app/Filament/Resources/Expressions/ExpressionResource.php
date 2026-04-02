<?php

namespace App\Filament\Resources\Expressions;

use App\Filament\Resources\Expressions\Pages\CreateExpression;
use App\Filament\Resources\Expressions\Pages\EditExpression;
use App\Filament\Resources\Expressions\Pages\ListExpressions;
use App\Filament\Resources\Expressions\Pages\ViewExpression;
use App\Filament\Resources\Expressions\Schemas\ExpressionForm;
use App\Filament\Resources\Expressions\Schemas\ExpressionInfolist;
use App\Filament\Resources\Expressions\Tables\ExpressionsTable;
use App\Filament\Resources\Expressions\RelationManagers\ContributorsRelationManager;
use App\Filament\Resources\Expressions\RelationManagers\EditionsRelationManager;
use App\Models\Expression;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ExpressionResource extends Resource
{
    protected static ?string $model = Expression::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLanguage;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return ExpressionForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return ExpressionInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ExpressionsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ContributorsRelationManager::class,
            EditionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListExpressions::route('/'),
            'create' => CreateExpression::route('/create'),
            'view' => ViewExpression::route('/{record}'),
            'edit' => EditExpression::route('/{record}/edit'),
        ];
    }
}
