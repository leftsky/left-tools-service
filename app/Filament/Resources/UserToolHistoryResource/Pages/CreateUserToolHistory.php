<?php

namespace App\Filament\Resources\UserToolHistoryResource\Pages;

use App\Filament\Resources\UserToolHistoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateUserToolHistory extends CreateRecord
{
    protected static string $resource = UserToolHistoryResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}