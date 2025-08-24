<?php

namespace App\Filament\Resources\ConversionEngineFormatResource\Pages;

use App\Filament\Resources\ConversionEngineFormatResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListConversionEngineFormats extends ListRecords
{
    protected static string $resource = ConversionEngineFormatResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make()
                ->label('创建格式配置')
                ->icon('heroicon-m-plus'),
        ];
    }
}
