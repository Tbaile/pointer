<?php

namespace App\Filament\Resources\ToolRequests\Tables;

use App\Enums\SystemType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolRequestsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('requests_count', 'desc')
            ->columns([
                TextColumn::make('title')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $searchQuery) => $searchQuery
                            ->whereLike('title', "%{$search}%")
                            ->orWhereLike('description', "%{$search}%"),
                    ))
                    ->sortable(),
                TextColumn::make('system_type')
                    ->label(__('Product'))
                    ->sortable(),
                TextColumn::make('requests_count')
                    ->label(__('Requests'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('system_type')
                    ->label(__('Product'))
                    ->options(SystemType::class)
                    ->multiple(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
