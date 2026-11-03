<?php

namespace App\Filament\Resources\CommandClassifications\Tables;

use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CommandClassificationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')
                    ->label(__('Time'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('user.name')
                    ->label(__('User')),
                TextColumn::make('sos_id')
                    ->label(__('SOS id'))
                    ->searchable(),
                TextColumn::make('command')
                    ->limit(60)
                    ->searchable(),
                TextColumn::make('flagged')
                    ->badge()
                    ->color('danger'),
                TextColumn::make('error')
                    ->limit(40),
            ])
            ->filters([
                TernaryFilter::make('error')
                    ->label(__('Failed'))
                    ->nullable(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
