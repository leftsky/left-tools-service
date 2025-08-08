<?php

namespace App\Filament\Pages;

use App\Filament\Widgets\FileConversionStatsWidget;
use App\Filament\Widgets\FileConversionChartWidget;
use App\Filament\Widgets\LatestFileConversionTasksWidget;
use Filament\Pages\Page;

class FileConversionDashboard extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = '文件转换';

    protected static ?string $navigationLabel = '转换概览';

    protected static ?string $title = '文件转换概览';

    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.file-conversion-dashboard';

    protected function getHeaderWidgets(): array
    {
        return [
            FileConversionStatsWidget::class,
            FileConversionChartWidget::class,
        ];
    }

    protected function getFooterWidgets(): array
    {
        return [
            LatestFileConversionTasksWidget::class,
        ];
    }
}
