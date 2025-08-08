<?php

namespace App\Filament\Resources\FileConversionTaskResource\Pages;

use App\Filament\Resources\FileConversionTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewFileConversionTask extends ViewRecord
{
    protected static string $resource = FileConversionTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 移除编辑按钮，转换任务应该通过 API 管理
        ];
    }
}
