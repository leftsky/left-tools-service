<?php

namespace App\Filament\Resources\ToolUsageStatResource\Pages;

use App\Filament\Resources\ToolUsageStatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateToolUsageStat extends CreateRecord
{
    protected static string $resource = ToolUsageStatResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 