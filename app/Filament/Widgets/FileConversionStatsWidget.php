<?php

namespace App\Filament\Widgets;

use App\Models\FileConversionTask;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class FileConversionStatsWidget extends BaseWidget
{
    protected function getStats(): array
    {
        $totalTasks = FileConversionTask::count();
        $completedTasks = FileConversionTask::where('status', FileConversionTask::STATUS_FINISH)->count();
        $failedTasks = FileConversionTask::where('status', FileConversionTask::STATUS_FAILED)->count();
        $processingTasks = FileConversionTask::where('status', FileConversionTask::STATUS_CONVERT)->count();
        $waitingTasks = FileConversionTask::where('status', FileConversionTask::STATUS_WAIT)->count();

        $successRate = $totalTasks > 0 ? round(($completedTasks / $totalTasks) * 100, 1) : 0;

        return [
            Stat::make('总任务数', $totalTasks)
                ->description('所有转换任务')
                ->descriptionIcon('heroicon-m-document-text')
                ->color('primary'),

            Stat::make('已完成', $completedTasks)
                ->description('成功完成的任务')
                ->descriptionIcon('heroicon-m-check-circle')
                ->color('success'),

            Stat::make('成功率', $successRate . '%')
                ->description('完成率统计')
                ->descriptionIcon('heroicon-m-chart-bar')
                ->color($successRate >= 80 ? 'success' : ($successRate >= 60 ? 'warning' : 'danger')),

            Stat::make('处理中', $processingTasks)
                ->description('正在转换的任务')
                ->descriptionIcon('heroicon-m-arrow-path')
                ->color('info'),

            Stat::make('等待中', $waitingTasks)
                ->description('等待处理的任务')
                ->descriptionIcon('heroicon-m-clock')
                ->color('warning'),

            Stat::make('失败', $failedTasks)
                ->description('转换失败的任务')
                ->descriptionIcon('heroicon-m-x-circle')
                ->color('danger'),
        ];
    }
}
