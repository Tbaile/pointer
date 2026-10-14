<?php

namespace App\Filament\Resources\Playbooks\Pages;

use App\Actions\RevisePlaybook;
use App\Filament\Resources\Playbooks\PlaybookResource;
use App\Models\Playbook;
use App\Models\User;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

/**
 * @property Playbook $record
 */
class EditPlaybook extends EditRecord
{
    protected static string $resource = PlaybookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['tags'] = $this->record->tagNames();

        return $data;
    }

    /**
     * @param  Playbook  $record
     * @param  array{title: string, body: string, tags: list<string>}  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $reviewer */
        $reviewer = auth()->user();

        app(RevisePlaybook::class)->handle($record, $reviewer, $data['title'], $data['body'], $data['tags']);

        return $record;
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('view', ['record' => $this->record]);
    }
}
