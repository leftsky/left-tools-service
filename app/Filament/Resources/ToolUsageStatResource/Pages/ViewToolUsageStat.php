<?php

namespace App\Filament\Resources\ToolUsageStatResource\Pages;

use App\Filament\Resources\ToolUsageStatResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewToolUsageStat extends ViewRecord
{
    protected static string $resource = ToolUsageStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('编辑统计'),
        ];
    }
} 