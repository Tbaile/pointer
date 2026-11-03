<?php

namespace App\Filament\Resources\ToolRequests;

use App\Filament\Resources\ToolRequests\Pages\ListToolRequests;
use App\Filament\Resources\ToolRequests\Schemas\ToolRequestInfolist;
use App\Filament\Resources\ToolRequests\Tables\ToolRequestsTable;
use App\Models\ToolRequest;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ToolRequestResource extends Resource
{
    protected static ?string $model = ToolRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;

    protected static ?int $navigationSort = 4;

    protected static ?string $recordTitleAttribute = 'title';

    public static function infolist(Schema $schema): Schema
    {
        return ToolRequestInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToolRequestsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListToolRequests::route('/'),
        ];
    }
}
