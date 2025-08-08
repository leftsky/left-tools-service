<?php

namespace App\Filament\Widgets;

use App\Models\FileConversionTask;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Carbon;

class FileConversionChartWidget extends ChartWidget
{
    protected static ?string $heading = '转换任务趋势';

    protected static ?int $sort = 2;

    protected function getData(): array
    {
        $days = collect();
        $completed = collect();
        $failed = collect();
        $processing = collect();

        for ($i = 6; $i >= 0; $i--) {
            $date = Carbon::now()->subDays($i);
            $days->push($date->format('m/d'));

            $completed->push(
                FileConversionTask::where('status', FileConversionTask::STATUS_FINISH)
                    ->whereDate('created_at', $date)
                    ->count()
            );

            $failed->push(
                FileConversionTask::where('status', FileConversionTask::STATUS_FAILED)
                    ->whereDate('created_at', $date)
                    ->count()
            );

            $processing->push(
                FileConversionTask::where('status', FileConversionTask::STATUS_CONVERT)
                    ->whereDate('created_at', $date)
                    ->count()
            );
        }

        return [
            'datasets' => [
                [
                    'label' => '已完成',
                    'data' => $completed->toArray(),
                    'borderColor' => '#10b981',
                    'backgroundColor' => '#10b981',
                ],
                [
                    'label' => '失败',
                    'data' => $failed->toArray(),
                    'borderColor' => '#ef4444',
                    'backgroundColor' => '#ef4444',
                ],
                [
                    'label' => '处理中',
                    'data' => $processing->toArray(),
                    'borderColor' => '#3b82f6',
                    'backgroundColor' => '#3b82f6',
                ],
            ],
            'labels' => $days->toArray(),
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }
}
