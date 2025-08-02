<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewAccessLog extends ViewRecord
{
    protected static string $resource = AccessLogResource::class;

    public static function getNavigationLabel(): string
    {
        return '查看访问记录';
    }

    protected function getHeaderActions(): array
    {
        return [
            Actions\EditAction::make(),
        ];
    }
} 