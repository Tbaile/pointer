<?php

namespace App\Filament\Resources\CommandClassifications;

use App\Filament\Resources\CommandClassifications\Pages\ListCommandClassifications;
use App\Filament\Resources\CommandClassifications\Schemas\CommandClassificationInfolist;
use App\Filament\Resources\CommandClassifications\Tables\CommandClassificationsTable;
use App\Models\CommandClassification;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class CommandClassificationResource extends Resource
{
    protected static ?string $model = CommandClassification::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static ?int $navigationSort = 6;

    protected static ?string $recordTitleAttribute = 'command';

    public static function infolist(Schema $schema): Schema
    {
        return CommandClassificationInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommandClassificationsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommandClassifications::route('/'),
        ];
    }
}
