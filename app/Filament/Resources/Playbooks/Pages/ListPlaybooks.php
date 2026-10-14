<?php

namespace App\Filament\Resources\Playbooks\Pages;

use App\Filament\Resources\Playbooks\PlaybookResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Enums\Width;

class ListPlaybooks extends ListRecords
{
    protected static string $resource = PlaybookResource::class;

    protected Width|string|null $maxContentWidth = Width::Full;
}
