<?php

namespace App\Filament\Resources\Users;

use App\Filament\Resources\Users\Pages\CreateUser;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Schemas\UserForm;
use App\Filament\Resources\Users\Tables\UsersTable;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontFamily;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?int $navigationSort = 2;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }

    /**
     * Modal that shows a new agent token once, mounted with the token as the `token` argument.
     */
    public static function showAgentTokenAction(): Action
    {
        return Action::make('showAgentToken')
            ->modalHeading(__('Token created'))
            ->modalDescription(__('Copy it now, it will not be shown again.'))
            ->schema(fn (array $arguments): array => [
                TextEntry::make('token')
                    ->hiddenLabel()
                    ->state($arguments['token'] ?? null)
                    ->fontFamily(FontFamily::Mono)
                    ->copyable(),
            ])
            ->modalSubmitAction(false)
            ->modalCancelActionLabel(__('Done'))
            ->closeModalByClickingAway(false)
            ->closeModalByEscaping(false);
    }

    /**
     * Show the admin a temporary password, which is never retrievable again.
     */
    public static function sendTemporaryPasswordNotification(User $user, string $password): void
    {
        Notification::make()
            ->title(__('Temporary password for :name', ['name' => $user->name]))
            ->body(__('Share it with the user, it will not be shown again: :password', ['password' => $password]))
            ->success()
            ->persistent()
            ->send();
    }
}
