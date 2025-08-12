<?php

namespace App\Filament\Resources\UserFeedbackResource\Pages;

use App\Filament\Resources\UserFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ListRecords;

class ListUserFeedback extends ListRecords
{
    protected static string $resource = UserFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}