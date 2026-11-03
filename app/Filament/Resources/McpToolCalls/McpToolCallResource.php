<?php

namespace App\Filament\Resources\McpToolCalls;

use App\Filament\Resources\McpToolCalls\Pages\ListMcpToolCalls;
use App\Filament\Resources\McpToolCalls\Schemas\McpToolCallInfolist;
use App\Filament\Resources\McpToolCalls\Tables\McpToolCallsTable;
use App\Models\McpToolCall;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class McpToolCallResource extends Resource
{
    protected static ?string $model = McpToolCall::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedListBullet;

    protected static ?string $slug = 'mcp-activity';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'tool';

    public static function getNavigationLabel(): string
    {
        return __('MCP activity');
    }

    public static function getModelLabel(): string
    {
        return __('MCP tool call');
    }

    public static function getPluralModelLabel(): string
    {
        return __('MCP activity');
    }

    public static function infolist(Schema $schema): Schema
    {
        return McpToolCallInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return McpToolCallsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMcpToolCalls::route('/'),
        ];
    }
}
