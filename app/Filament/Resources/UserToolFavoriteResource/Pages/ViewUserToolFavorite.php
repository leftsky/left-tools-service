<?php

namespace App\Filament\Resources\UserToolFavoriteResource\Pages;

use App\Filament\Resources\UserToolFavoriteResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserToolFavorite extends ViewRecord
{
    protected static string $resource = UserToolFavoriteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('编辑收藏'),
        ];
    }
} 