<?php

namespace App\Filament\Resources\AccessLogResource\Pages;

use App\Filament\Resources\AccessLogResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListAccessLogs extends ListRecords
{
    protected static string $resource = AccessLogResource::class;

    public static function getNavigationLabel(): string
    {
        return '访问记录';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
} 