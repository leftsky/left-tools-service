<?php

namespace App\Filament\Resources\ToolUsageStatResource\Pages;

use App\Filament\Resources\ToolUsageStatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditToolUsageStat extends EditRecord
{
    protected static string $resource = ToolUsageStatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('删除统计'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}