<?php

namespace App\Filament\Resources\Playbooks\Tables;

use App\Enums\PlaybookVerdict;
use App\Enums\SystemType;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PlaybooksTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('tags')->withCount('pendingProposals'))
            ->defaultSort(fn (Builder $query) => $query
                ->orderByRaw('case when pending_proposals_count > 0 then 0 else 1 end')
                ->orderByRaw('case when verdict is null then 0 else 1 end')
                ->latest('updated_at'))
            ->columns([
                TextColumn::make('title')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $searchQuery) => $searchQuery
                            ->whereLike('title', "%{$search}%")
                            ->orWhereLike('body', "%{$search}%")
                            ->orWhereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereLike('name', "%{$search}%")),
                    )),
                TextColumn::make('tags.name')
                    ->label(__('Tags'))
                    ->badge(),
                TextColumn::make('system_type')
                    ->label(__('Product')),
                TextColumn::make('verdict')
                    ->placeholder(__('Unverified')),
                TextColumn::make('uses_count')
                    ->label(__('Uses')),
                TextColumn::make('pending_proposals_count')
                    ->label(__('Pending proposals')),
                TextColumn::make('updated_at')
                    ->label(__('Updated'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->filters([
                SelectFilter::make('system_type')
                    ->label(__('Product'))
                    ->options(SystemType::class),
                SelectFilter::make('verdict')
                    ->options(['unverified' => __('Unverified')] + collect(PlaybookVerdict::cases())->mapWithKeys(fn (PlaybookVerdict $verdict): array => [$verdict->value => $verdict->getLabel()])->all())
                    ->query(fn (Builder $query, array $data) => match ($data['value'] ?? null) {
                        null, '' => $query,
                        'unverified' => $query->whereNull('verdict'),
                        default => $query->where('verdict', $data['value']),
                    }),
                Filter::make('pending')
                    ->label(__('With pending proposals'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->has('pendingProposals')),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->paginationPageOptions([15]);
    }
}
