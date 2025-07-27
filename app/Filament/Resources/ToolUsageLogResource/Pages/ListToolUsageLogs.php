<?php

namespace App\Filament\Resources\ToolUsageLogResource\Pages;

use App\Filament\Resources\ToolUsageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListToolUsageLogs extends ListRecords
{
    protected static string $resource = ToolUsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('创建使用记录'),
        ];
    }
} 