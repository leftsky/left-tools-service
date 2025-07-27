<?php

namespace App\Filament\Resources\ToolUsageLogResource\Pages;

use App\Filament\Resources\ToolUsageLogResource;
use Filament\Resources\Pages\CreateRecord;

class CreateToolUsageLog extends CreateRecord
{
    protected static string $resource = ToolUsageLogResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 