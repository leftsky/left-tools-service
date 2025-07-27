<?php

namespace App\Filament\Resources\UserToolFavoriteResource\Pages;

use App\Filament\Resources\UserToolFavoriteResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserToolFavorites extends ListRecords
{
    protected static string $resource = UserToolFavoriteResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
} 