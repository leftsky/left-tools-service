<?php

namespace App\Filament\Resources\FileConversionTaskResource\Pages;

use App\Filament\Resources\FileConversionTaskResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListFileConversionTasks extends ListRecords
{
    protected static string $resource = FileConversionTaskResource::class;

    protected function getHeaderActions(): array
    {
        return [
            // 移除创建按钮，转换任务应该通过 API 创建
        ];
    }
}
