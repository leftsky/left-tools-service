<?php

namespace App\Filament\Resources\AccessStatsResource\Pages;

use App\Filament\Resources\AccessStatsResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccessStats extends ListRecords
{
    protected static string $resource = AccessStatsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
} 