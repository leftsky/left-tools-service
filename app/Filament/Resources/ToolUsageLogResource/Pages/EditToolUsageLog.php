<?php

namespace App\Filament\Resources\ToolUsageLogResource\Pages;

use App\Filament\Resources\ToolUsageLogResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditToolUsageLog extends EditRecord
{
    protected static string $resource = ToolUsageLogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('删除记录'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
} 