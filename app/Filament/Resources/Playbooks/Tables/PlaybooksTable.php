<?php

namespace App\Filament\Resources\Playbooks\Tables;

use App\Enums\PlaybookVerdict;
use App\Enums\ProposalStatus;
use App\Enums\SystemType;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
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
                ->orderByRaw(
                    'case when exists (select 1 from playbook_proposals where playbook_proposals.playbook_id = playbooks.id and status = ?) then 0 else 1 end',
                    [ProposalStatus::Pending->value],
                )
                ->orderByRaw('case when verdict is null then 0 else 1 end')
                ->latest('updated_at'))
            ->columns([
                TextColumn::make('title')
                    ->searchable(query: fn (Builder $query, string $search) => $query->where(
                        fn (Builder $searchQuery) => $searchQuery
                            ->whereLike('title', "%{$search}%")
                            ->orWhereLike('body', "%{$search}%")
                            ->orWhereHas('tags', fn (Builder $tagQuery) => $tagQuery->whereLike('name', "%{$search}%")),
                    ))
                    ->sortable(),
                TextColumn::make('tags.name')
                    ->label(__('Tags'))
                    ->badge()
                    ->searchable(isIndividual: true, isGlobal: false),
                TextColumn::make('system_type')
                    ->label(__('Product'))
                    ->searchable(isIndividual: true, isGlobal: false)
                    ->sortable(),
                TextColumn::make('verdict')
                    ->badge()
                    ->placeholder(__('Unverified'))
                    ->sortable(),
                TextColumn::make('uses_count')
                    ->label(__('Uses'))
                    ->sortable(),
                TextColumn::make('pending_proposals_count')
                    ->label(__('Pending proposals'))
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
                SelectFilter::make('verdict')
                    ->options(['unverified' => __('Unverified')] + collect(PlaybookVerdict::cases())->mapWithKeys(fn (PlaybookVerdict $verdict): array => [$verdict->value => $verdict->getLabel()])->all())
                    ->multiple()
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['values'] ?? null),
                        fn (Builder $query) => $query->where(fn (Builder $verdictQuery) => $verdictQuery
                            ->whereIn('verdict', array_diff($data['values'], ['unverified']))
                            ->when(in_array('unverified', $data['values'], true), fn (Builder $verdictQuery) => $verdictQuery->orWhereNull('verdict'))),
                    )),
                SelectFilter::make('tags')
                    ->label(__('Tags'))
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                Filter::make('uses')
                    ->schema([
                        TextInput::make('min_uses')
                            ->label(__('Minimum uses'))
                            ->numeric()
                            ->minValue(0),
                    ])
                    ->query(fn (Builder $query, array $data) => $query->when(
                        filled($data['min_uses'] ?? null),
                        fn (Builder $query) => $query->where('uses_count', '>=', (int) $data['min_uses']),
                    )),
                Filter::make('pending')
                    ->label(__('With pending proposals'))
                    ->toggle()
                    ->query(fn (Builder $query) => $query->has('pendingProposals')),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }
}
