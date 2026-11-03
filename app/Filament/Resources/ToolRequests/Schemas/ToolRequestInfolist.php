<?php

namespace App\Filament\Resources\ToolRequests\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ToolRequestInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make()
                    ->columns(3)
                    ->schema([
                        TextEntry::make('system_type')
                            ->label(__('Product')),
                        TextEntry::make('requests_count')
                            ->label(__('Requests')),
                        TextEntry::make('updated_at')
                            ->label(__('Updated'))
                            ->dateTime('Y-m-d H:i'),
                    ]),
                Section::make(__('Description'))
                    ->schema([
                        TextEntry::make('description')
                            ->hiddenLabel()
                            ->markdown(),
                    ]),
            ]);
    }
}
