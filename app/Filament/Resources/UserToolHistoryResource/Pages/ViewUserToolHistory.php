<?php

namespace App\Filament\Resources\UserToolHistoryResource\Pages;

use App\Filament\Resources\UserToolHistoryResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserToolHistory extends ViewRecord
{
    protected static string $resource = UserToolHistoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('编辑历史'),
        ];
    }
} 