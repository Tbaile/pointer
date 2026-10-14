<?php

namespace App\Filament\Pages\Auth;

use App\Filament\Pages\McpSetup;
use App\Models\User;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use SensitiveParameter;

class EditProfile extends BaseEditProfile
{
    protected bool $passwordWasChanged = false;

    public function mount(): void
    {
        parent::mount();

        if ($this->mustChangePassword()) {
            Notification::make()
                ->title(__('Change your temporary password to continue.'))
                ->warning()
                ->send();
        }
    }

    protected function getPasswordFormComponent(): Component
    {
        $component = parent::getPasswordFormComponent();

        if ($component instanceof TextInput) {
            $component->required(fn (): bool => $this->mustChangePassword());
        }

        return $component;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(#[SensitiveParameter] array $data): array
    {
        if (array_key_exists('password', $data)) {
            $this->passwordWasChanged = $this->mustChangePassword();
            $data['must_change_password'] = false;
        }

        return $data;
    }

    protected function getRedirectUrl(): ?string
    {
        return $this->passwordWasChanged ? McpSetup::getUrl() : null;
    }

    private function mustChangePassword(): bool
    {
        /** @var User $user */
        $user = $this->getUser();

        return $user->must_change_password;
    }
}
