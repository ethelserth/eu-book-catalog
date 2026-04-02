<?php

namespace App\Filament\Resources\ReviewQueues\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ReviewQueueForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Read-only context so the reviewer knows what they're resolving
                Section::make('Issue (read-only)')
                    ->columns(2)
                    ->schema([
                        \Filament\Forms\Components\TextInput::make('entity_type')
                            ->disabled(),

                        \Filament\Forms\Components\TextInput::make('issue_type')
                            ->disabled(),

                        \Filament\Forms\Components\Textarea::make('details')
                            ->disabled()
                            ->columnSpanFull(),
                    ]),

                Section::make('Resolution')
                    ->description('Set a decision and save. Then navigate to the entity to apply any corrections.')
                    ->schema([
                        Select::make('status')
                            ->options([
                                'pending'  => 'Pending — needs review',
                                'resolved' => 'Resolved — match confirmed or entity corrected',
                                'ignored'  => 'Ignored — not actionable',
                            ])
                            ->required(),
                    ]),
            ]);
    }
}
