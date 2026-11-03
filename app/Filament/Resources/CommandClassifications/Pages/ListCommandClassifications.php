<?php

namespace App\Filament\Resources\CommandClassifications\Pages;

use App\Filament\Resources\CommandClassifications\CommandClassificationResource;
use Filament\Resources\Pages\ListRecords;

class ListCommandClassifications extends ListRecords
{
    protected static string $resource = CommandClassificationResource::class;
}
