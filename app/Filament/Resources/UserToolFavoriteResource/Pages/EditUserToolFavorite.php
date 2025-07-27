<?php

namespace App\Filament\Resources\UserToolFavoriteResource\Pages;

use App\Filament\Resources\UserToolFavoriteResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserToolFavorite extends EditRecord
{
    protected static string $resource = UserToolFavoriteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('删除收藏'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 