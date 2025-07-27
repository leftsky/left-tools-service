<?php

namespace App\Filament\Resources\UserToolHistoryResource\Pages;

use App\Filament\Resources\UserToolHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditUserToolHistory extends EditRecord
{
    protected static string $resource = UserToolHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('删除历史'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 