<?php

namespace App\Filament\Resources\ToolUsageStatResource\Pages;

use App\Filament\Resources\ToolUsageStatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolUsageStats extends ListRecords
{
    protected static string $resource = ToolUsageStatResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
} 