<?php

namespace App\Filament\Resources\UserToolFavoriteResource\Pages;

use App\Filament\Resources\UserToolFavoriteResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserToolFavorite extends CreateRecord
{
    protected static string $resource = UserToolFavoriteResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 