<?php

namespace App\Filament\Resources\Playbooks\RelationManagers;

use App\Actions\AcceptProposal;
use App\Actions\RejectProposal;
use App\Enums\ProposalStatus;
use App\Models\PlaybookProposal;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProposalsRelationManager extends RelationManager
{
    protected static string $relationship = 'proposals';

    public function isReadOnly(): bool
    {
        return false;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['playbook.tags', 'user', 'reviewer']))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('reason')
                    ->wrap(),
                TextColumn::make('user.name')
                    ->label(__('Author'))
                    ->placeholder(__('Unknown author')),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (ProposalStatus $state): string => match ($state) {
                        ProposalStatus::Pending => 'warning',
                        ProposalStatus::Accepted => 'success',
                        ProposalStatus::Rejected => 'danger',
                    })
                    ->description(fn (PlaybookProposal $record) => $record->status === ProposalStatus::Pending && $record->isStale()
                        ? __('Stale: the playbook changed after this was proposed')
                        : null),
                TextColumn::make('created_at')
                    ->label(__('Proposed'))
                    ->dateTime('Y-m-d H:i'),
                TextColumn::make('reviewer.name')
                    ->label(__('Reviewer'))
                    ->placeholder('-'),
                TextColumn::make('review_note')
                    ->label(__('Review note'))
                    ->placeholder('-')
                    ->wrap(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->options(array_column(ProposalStatus::cases(), 'value', 'value'))
                    ->default(ProposalStatus::Pending->value),
            ])
            ->recordActions([
                Action::make('diff')
                    ->label(__('Diff'))
                    ->color('gray')
                    ->modalContent(fn (PlaybookProposal $record) => view('filament.proposal-diff', ['proposal' => $record]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel(__('Close')),
                Action::make('accept')
                    ->label(__('Accept'))
                    ->color('success')
                    ->visible(fn (PlaybookProposal $record): bool => $record->status === ProposalStatus::Pending)
                    ->requiresConfirmation()
                    ->action(function (PlaybookProposal $record, AcceptProposal $acceptProposal): void {
                        /** @var User $reviewer */
                        $reviewer = auth()->user();

                        $acceptProposal->handle($record, $reviewer);

                        $this->dispatch('refresh-playbook');
                    }),
                Action::make('reject')
                    ->label(__('Reject'))
                    ->color('danger')
                    ->visible(fn (PlaybookProposal $record): bool => $record->status === ProposalStatus::Pending)
                    ->schema([
                        TextInput::make('note')
                            ->label(__('Rejection note (optional)')),
                    ])
                    ->action(function (PlaybookProposal $record, array $data, RejectProposal $rejectProposal): void {
                        /** @var User $reviewer */
                        $reviewer = auth()->user();

                        $rejectProposal->handle($record, $reviewer, $data['note'] ?? null);
                    }),
            ]);
    }
}
