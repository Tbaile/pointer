<?php

namespace App\Filament\Resources\Playbooks\Pages;

use App\Filament\Resources\Playbooks\PlaybookResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPlaybooks extends ListRecords
{
    protected static string $resource = PlaybookResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
