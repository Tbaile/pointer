<?php

namespace App\Filament\Resources\Playbooks;

use App\Filament\Resources\Playbooks\Pages\EditPlaybook;
use App\Filament\Resources\Playbooks\Pages\ListPlaybooks;
use App\Filament\Resources\Playbooks\Pages\ViewPlaybook;
use App\Filament\Resources\Playbooks\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\Playbooks\Schemas\PlaybookForm;
use App\Filament\Resources\Playbooks\Schemas\PlaybookInfolist;
use App\Filament\Resources\Playbooks\Tables\PlaybooksTable;
use App\Models\Playbook;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class PlaybookResource extends Resource
{
    protected static ?string $model = Playbook::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return PlaybookForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PlaybookInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlaybooksTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProposalsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlaybooks::route('/'),
            'view' => ViewPlaybook::route('/{record}'),
            'edit' => EditPlaybook::route('/{record}/edit'),
        ];
    }
}
