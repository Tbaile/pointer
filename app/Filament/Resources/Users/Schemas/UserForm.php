<?php

namespace App\Filament\Resources\Users\Schemas;

use App\Enums\Permission;
use App\Enums\Role;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                TextInput::make('email')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(ignoreRecord: true),
                Select::make('roles')
                    ->label(__('Roles'))
                    ->relationship(
                        titleAttribute: 'name',
                        modifyQueryUsing: fn (Builder $query): Builder => auth()->user()->can(Permission::AssignAdminRoles)
                            ? $query
                            : $query->whereIn('name', [Role::Contributor->value, Role::Technician->value]),
                    )
                    ->multiple()
                    ->preload()
                    ->required(),
            ]);
    }
}
