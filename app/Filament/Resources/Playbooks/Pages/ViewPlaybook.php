<?php

namespace App\Filament\Resources\Playbooks\Pages;

use App\Actions\SetPlaybookVerdict;
use App\Enums\PlaybookVerdict;
use App\Filament\Resources\Playbooks\PlaybookResource;
use App\Models\Playbook;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\ViewRecord;
use Livewire\Attributes\On;

/**
 * @property Playbook $record
 */
class ViewPlaybook extends ViewRecord
{
    protected static string $resource = PlaybookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setVerdict')
                ->label(__('Set verdict'))
                ->color('gray')
                ->fillForm(fn (Playbook $record): array => [
                    'verdict' => $record->verdict,
                    'verdict_note' => $record->verdict_note,
                ])
                ->schema([
                    Select::make('verdict')
                        ->options(PlaybookVerdict::class)
                        ->placeholder(__('Unverified')),
                    Textarea::make('verdict_note')
                        ->label(__('Verdict note'))
                        ->rows(2),
                ])
                ->action(function (Playbook $record, array $data, SetPlaybookVerdict $setPlaybookVerdict): void {
                    /** @var User $reviewer */
                    $reviewer = auth()->user();

                    $setPlaybookVerdict->handle($record, $reviewer, $data['verdict'], $data['verdict_note'] ?? null);
                }),
            EditAction::make(),
        ];
    }

    #[On('refresh-playbook')]
    public function refreshPlaybook(): void
    {
        $this->record->refresh();
    }
}
