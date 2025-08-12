<?php

namespace App\Filament\Resources\UserFeedbackResource\Pages;

use App\Filament\Resources\UserFeedbackResource;
use Filament\Actions;
use Filament\Resources\Pages\ViewRecord;

class ViewUserFeedback extends ViewRecord
{
    protected static string $resource = UserFeedbackResource::class;

    protected function getHeaderActions(): array
    {
        return [
            //
        ];
    }
}