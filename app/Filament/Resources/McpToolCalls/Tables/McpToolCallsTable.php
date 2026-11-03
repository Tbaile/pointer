<?php

namespace App\Filament\Resources\McpToolCalls\Tables;

use App\Models\McpToolCall;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class McpToolCallsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
                TextColumn::make('user.email')
                    ->label(__('User')),
                TextColumn::make('client')
                    ->label(__('Client')),
                TextColumn::make('tool')
                    ->label(__('Tool'))
                    ->searchable(),
                TextColumn::make('sos_id')
                    ->label(__('SOS id'))
                    ->searchable(),
                IconColumn::make('success')
                    ->label(__('Success'))
                    ->boolean(),
                TextColumn::make('duration_ms')
                    ->label(__('Duration'))
                    ->suffix(' ms')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('user')
                    ->label(__('User'))
                    ->relationship('user', 'email'),
                SelectFilter::make('client')
                    ->label(__('Client'))
                    ->options(fn (): array => self::distinct('client')),
                SelectFilter::make('tool')
                    ->label(__('Tool'))
                    ->options(fn (): array => self::distinct('tool')),
                SelectFilter::make('sos_id')
                    ->label(__('SOS id'))
                    ->options(fn (): array => self::distinct('sos_id'))
                    ->multiple()
                    ->searchable(),
                TernaryFilter::make('success')
                    ->label(__('Success')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->poll('5s');
    }

    /**
     * @return array<string, string>
     */
    private static function distinct(string $column): array
    {
        return McpToolCall::query()
            ->whereNotNull($column)
            ->distinct()
            ->orderBy($column)
            ->pluck($column, $column)
            ->all();
    }
}
