<?php

namespace App\Filament\Resources\ConversionEngineFormatResource\Pages;

use App\Filament\Resources\ConversionEngineFormatResource;
use Filament\Resources\Pages\CreateRecord;

class CreateConversionEngineFormat extends CreateRecord
{
    protected static string $resource = ConversionEngineFormatResource::class;

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }

    protected function getCreatedNotificationTitle(): ?string
    {
        return '转换引擎格式配置创建成功';
    }
}
