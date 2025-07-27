<?php

namespace App\Filament\Resources\ToolUsageLogResource\Pages;

use App\Filament\Resources\ToolUsageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewToolUsageLog extends ViewRecord
{
    protected static string $resource = ToolUsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make()
                ->label('编辑记录'),
        ];
    }
} 