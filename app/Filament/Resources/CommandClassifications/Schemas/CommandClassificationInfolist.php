<?php

namespace App\Filament\Resources\CommandClassifications\Schemas;

use Filament\Infolists\Components\KeyValueEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommandClassificationInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('created_at')
                            ->label(__('Time'))
                            ->dateTime('Y-m-d H:i'),
                        TextEntry::make('user.name')
                            ->label(__('User')),
                        TextEntry::make('sos_id')
                            ->label(__('SOS id')),
                        TextEntry::make('model'),
                        TextEntry::make('input_tokens')
                            ->label(__('Input tokens')),
                        TextEntry::make('output_tokens')
                            ->label(__('Output tokens')),
                    ]),
                Section::make(__('Command'))
                    ->schema([
                        TextEntry::make('command')
                            ->hiddenLabel(),
                        TextEntry::make('flagged')
                            ->badge()
                            ->color('danger'),
                        TextEntry::make('error')
                            ->visible(fn (?string $state): bool => filled($state)),
                    ]),
                Section::make(__('Probabilities'))
                    ->schema([
                        KeyValueEntry::make('probabilities')
                            ->hiddenLabel(),
                    ]),
            ]);
    }
}
