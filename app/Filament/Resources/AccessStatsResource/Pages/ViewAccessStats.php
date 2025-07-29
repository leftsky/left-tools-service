<?php

namespace App\Filament\Resources\AccessStatsResource\Pages;

use App\Filament\Resources\AccessStatsResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccessStats extends ViewRecord
{
    protected static string $resource = AccessStatsResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
} 