<?php

namespace App\Filament\Resources\ConversionEngineFormatResource\Pages;

use App\Filament\Resources\ConversionEngineFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditConversionEngineFormat extends EditRecord
{
    protected static string $resource = ConversionEngineFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\DeleteAction::make()
                ->label('删除配置')
                ->icon('heroicon-m-trash')
                ->color('danger'),
        ];
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return '转换引擎格式配置更新成功';
    }
}
