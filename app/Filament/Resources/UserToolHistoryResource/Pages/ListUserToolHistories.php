<?php

namespace App\Filament\Resources\UserToolHistoryResource\Pages;

use App\Filament\Resources\UserToolHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserToolHistories extends ListRecords
{
    protected static string $resource = UserToolHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
} 