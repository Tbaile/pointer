<?php

namespace App\Filament\Resources\McpToolCalls\Schemas;

use App\Models\McpToolCall;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Support\HtmlString;

class McpToolCallInfolist
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
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('user.email')
                            ->label(__('User')),
                        TextEntry::make('client')
                            ->label(__('Client')),
                        TextEntry::make('tool')
                            ->label(__('Tool')),
                        TextEntry::make('sos_id')
                            ->label(__('SOS id')),
                        TextEntry::make('session_id')
                            ->label(__('Session')),
                        IconEntry::make('success')
                            ->label(__('Success'))
                            ->boolean(),
                        TextEntry::make('duration_ms')
                            ->label(__('Duration'))
                            ->suffix(' ms'),
                    ]),
                Section::make(__('Arguments'))
                    ->schema([
                        TextEntry::make('arguments')
                            ->hiddenLabel()
                            ->state(fn (McpToolCall $record): HtmlString => new HtmlString(
                                '<pre class="text-sm whitespace-pre-wrap">'.e(json_encode($record->arguments, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)).'</pre>',
                            )),
                    ]),
            ]);
    }
}
