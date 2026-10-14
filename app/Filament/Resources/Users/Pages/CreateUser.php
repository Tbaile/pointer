<?php

namespace App\Filament\Resources\Users\Pages;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

/**
 * @property User $record
 */
class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    protected string $temporaryPassword = '';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->temporaryPassword = Str::password(16);

        return [
            ...$data,
            'password' => $this->temporaryPassword,
            'must_change_password' => true,
        ];
    }

    protected function afterCreate(): void
    {
        UserResource::sendTemporaryPasswordNotification($this->record, $this->temporaryPassword);
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
