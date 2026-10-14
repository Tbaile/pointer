<?php

namespace App\Filament\Resources\Playbooks\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class PlaybookInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(5)
                    ->schema([
                        TextEntry::make('system_type')
                            ->label(__('Product')),
                        TextEntry::make('tags.name')
                            ->label(__('Tags'))
                            ->badge(),
                        TextEntry::make('uses_count')
                            ->label(__('Uses')),
                        TextEntry::make('user.name')
                            ->label(__('Author'))
                            ->placeholder(__('Unknown author')),
                        TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime('Y-m-d H:i'),
                    ]),
                Section::make(__('Body'))
                    ->schema([
                        TextEntry::make('body')
                            ->hiddenLabel()
                            ->markdown(),
                    ]),
                Section::make(__('Verdict'))
                    ->columns(3)
                    ->schema([
                        TextEntry::make('verdict')
                            ->placeholder(__('Unverified')),
                        TextEntry::make('verifier.name')
                            ->label(__('Verified by'))
                            ->placeholder('-'),
                        TextEntry::make('verified_at')
                            ->label(__('Verified at'))
                            ->dateTime('Y-m-d H:i')
                            ->placeholder('-'),
                        TextEntry::make('verdict_note')
                            ->label(__('Verdict note'))
                            ->placeholder('-')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
