<?php

namespace App\Filament\Widgets;

use App\Models\FileConversionTask;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Filament\Tables\Columns\TextColumn;

class LatestFileConversionTasksWidget extends BaseWidget
{
    protected static ?int $sort = 3;

    protected int | string | array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->query(
                FileConversionTask::query()
                    ->latest()
                    ->limit(10)
            )
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('filename')
                    ->label('文件名')
                    ->searchable()
                    ->limit(30),
                TextColumn::make('input_format')
                    ->label('输入格式')
                    ->badge()
                    ->color('gray'),
                TextColumn::make('output_format')
                    ->label('输出格式')
                    ->badge()
                    ->color('blue'),
                TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        FileConversionTask::STATUS_WAIT => 'warning',
                        FileConversionTask::STATUS_CONVERT => 'info',
                        FileConversionTask::STATUS_FINISH => 'success',
                        FileConversionTask::STATUS_FAILED => 'danger',
                        FileConversionTask::STATUS_CANCELLED => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (int $state): string => match ($state) {
                        FileConversionTask::STATUS_WAIT => '等待中',
                        FileConversionTask::STATUS_CONVERT => '转换中',
                        FileConversionTask::STATUS_FINISH => '已完成',
                        FileConversionTask::STATUS_FAILED => '失败',
                        FileConversionTask::STATUS_CANCELLED => '已取消',
                        default => '未知',
                    }),
                TextColumn::make('step_percent')
                    ->label('进度')
                    ->formatStateUsing(fn (int $state): string => $state . '%')
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'info',
                        $state > 0 => 'warning',
                        default => 'gray',
                    }),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                Tables\Actions\Action::make('view')
                    ->label('查看')
                    ->icon('heroicon-m-eye')
                    ->url(fn (FileConversionTask $record): string => "/admin/file-conversion-tasks/{$record->id}")
                    ->openUrlInNewTab(),
            ]);
    }
}
