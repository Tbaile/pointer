<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('email')
                    ->searchable(),
                IconColumn::make('is_admin')
                    ->label(__('Administrator'))
                    ->boolean(),
                IconColumn::make('must_change_password')
                    ->label(__('Password change pending'))
                    ->boolean(),
                TextColumn::make('created_at')
                    ->label(__('Created'))
                    ->dateTime('Y-m-d H:i'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resetPassword')
                    ->label(__('Reset password'))
                    ->icon(Heroicon::OutlinedKey)
                    ->requiresConfirmation()
                    ->modalDescription(__('A new temporary password is generated and the user must change it at the next login.'))
                    ->action(function (User $record): void {
                        $password = Str::password(16);

                        $record->update([
                            'password' => $password,
                            'must_change_password' => true,
                        ]);

                        UserResource::sendTemporaryPasswordNotification($record, $password);
                    }),
                DeleteAction::make()
                    ->hidden(fn (User $record): bool => $record->is(auth()->user())),
            ]);
    }
}
