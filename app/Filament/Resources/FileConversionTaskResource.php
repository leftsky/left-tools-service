<?php

namespace App\Filament\Resources;

use App\Filament\Resources\FileConversionTaskResource\Pages;
use App\Models\FileConversionTask;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\Filter;
use Filament\Forms\Components\DatePicker;

class FileConversionTaskResource extends Resource
{
    protected static ?string $model = FileConversionTask::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrow-path';

    protected static ?string $navigationGroup = '文件转换';

    protected static ?string $navigationLabel = '转换任务';

    protected static ?string $modelLabel = '转换任务';

    protected static ?string $pluralModelLabel = '转换任务';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('id')
                            ->label('ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('user.name')
                            ->label('用户')
                            ->disabled(),
                        Forms\Components\TextInput::make('filename')
                            ->label('文件名')
                            ->disabled(),
                        Forms\Components\TextInput::make('input_format')
                            ->label('输入格式')
                            ->disabled(),
                        Forms\Components\TextInput::make('output_format')
                            ->label('输出格式')
                            ->disabled(),
                        Forms\Components\TextInput::make('conversion_engine')
                            ->label('转换引擎')
                            ->disabled()
                            ->formatStateUsing(fn (string $state): string => match ($state) {
                                FileConversionTask::ENGINE_CONVERTIO => 'Convertio',
                                FileConversionTask::ENGINE_CLOUDCONVERT => 'CloudConvert',
                                default => $state,
                            }),
                    ])
                    ->columns(2),
                
                Forms\Components\Section::make('状态信息')
                    ->schema([
                        Forms\Components\TextInput::make('status')
                            ->label('状态')
                            ->disabled()
                            ->formatStateUsing(fn (int $state): string => match ($state) {
                                FileConversionTask::STATUS_WAIT => '等待中',
                                FileConversionTask::STATUS_CONVERT => '转换中',
                                FileConversionTask::STATUS_FINISH => '已完成',
                                FileConversionTask::STATUS_FAILED => '失败',
                                FileConversionTask::STATUS_CANCELLED => '已取消',
                                default => '未知',
                            }),
                        Forms\Components\TextInput::make('step_percent')
                            ->label('进度')
                            ->disabled()
                            ->formatStateUsing(fn (int $state): string => $state . '%'),
                        Forms\Components\TextInput::make('formatted_file_size')
                            ->label('文件大小')
                            ->disabled(),
                        Forms\Components\TextInput::make('formatted_output_size')
                            ->label('输出大小')
                            ->disabled(),
                        Forms\Components\TextInput::make('formatted_processing_time')
                            ->label('处理时间')
                            ->disabled(),
                    ])
                    ->columns(2),

                Forms\Components\Section::make('文件信息')
                    ->schema([
                        Forms\Components\TextInput::make('input_file')
                            ->label('输入文件')
                            ->disabled(),
                        Forms\Components\Placeholder::make('output_url_display')
                            ->label('输出文件URL')
                            ->content(fn (FileConversionTask $record): \Illuminate\Contracts\Support\Htmlable => 
                                new \Illuminate\Support\HtmlString(
                                    $record->output_url 
                                        ? '<a href="' . htmlspecialchars($record->output_url, ENT_QUOTES, 'UTF-8') . '" target="_blank" class="text-primary-600 hover:text-primary-500 underline">' . htmlspecialchars($record->output_url, ENT_QUOTES, 'UTF-8') . '</a>'
                                        : '<span class="text-gray-500">暂无</span>'
                                )
                            ),
                    ]),

                Forms\Components\Section::make('时间信息')
                    ->schema([
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('创建时间')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('started_at')
                            ->label('开始时间')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('completed_at')
                            ->label('完成时间')
                            ->disabled(),
                    ])
                    ->columns(3),

                Forms\Components\Section::make('错误信息')
                    ->schema([
                        Forms\Components\Textarea::make('error_message')
                            ->label('错误信息')
                            ->disabled()
                            ->rows(3),
                    ])
                    ->visible(fn (FileConversionTask $record): bool => !empty($record->error_message)),

                Forms\Components\Section::make('转换选项')
                    ->schema([
                        Forms\Components\KeyValue::make('conversion_options')
                            ->label('转换选项')
                            ->disabled(),
                    ])
                    ->visible(fn (FileConversionTask $record): bool => !empty($record->conversion_options)),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('filename')
                    ->label('文件名')
                    ->searchable()
                    ->sortable()
                    ->limit(30)
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('input_format')
                    ->label('输入格式')
                    ->badge()
                    ->color('gray'),
                Tables\Columns\TextColumn::make('output_format')
                    ->label('输出格式')
                    ->badge()
                    ->color('blue'),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->badge()
                    ->color(fn(int $state): string => match ($state) {
                        FileConversionTask::STATUS_WAIT => 'warning',
                        FileConversionTask::STATUS_CONVERT => 'info',
                        FileConversionTask::STATUS_FINISH => 'success',
                        FileConversionTask::STATUS_FAILED => 'danger',
                        FileConversionTask::STATUS_CANCELLED => 'secondary',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(int $state): string => match ($state) {
                        FileConversionTask::STATUS_WAIT => '等待中',
                        FileConversionTask::STATUS_CONVERT => '转换中',
                        FileConversionTask::STATUS_FINISH => '已完成',
                        FileConversionTask::STATUS_FAILED => '失败',
                        FileConversionTask::STATUS_CANCELLED => '已取消',
                        default => '未知',
                    }),
                Tables\Columns\TextColumn::make('step_percent')
                    ->label('进度')
                    ->formatStateUsing(fn(int $state): string => $state . '%')
                    ->badge()
                    ->color(fn(int $state): string => match (true) {
                        $state >= 100 => 'success',
                        $state >= 50 => 'info',
                        $state > 0 => 'warning',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('conversion_engine')
                    ->label('引擎')
                    ->badge()
                    ->color(fn(string $state): string => match ($state) {
                        FileConversionTask::ENGINE_CONVERTIO => 'orange',
                        FileConversionTask::ENGINE_CLOUDCONVERT => 'purple',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn(string $state): string => match ($state) {
                        FileConversionTask::ENGINE_CONVERTIO => 'Convertio',
                        FileConversionTask::ENGINE_CLOUDCONVERT => 'CloudConvert',
                        default => $state,
                    }),
                Tables\Columns\TextColumn::make('formatted_file_size')
                    ->label('文件大小')
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('file_size', $direction)),
                Tables\Columns\TextColumn::make('formatted_output_size')
                    ->label('输出大小')
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('output_size', $direction)),
                Tables\Columns\TextColumn::make('formatted_processing_time')
                    ->label('处理时间')
                    ->sortable(query: fn(Builder $query, string $direction): Builder => $query->orderBy('processing_time', $direction)),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('started_at')
                    ->label('开始时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('completed_at')
                    ->label('完成时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('状态')
                    ->options([
                        FileConversionTask::STATUS_WAIT => '等待中',
                        FileConversionTask::STATUS_CONVERT => '转换中',
                        FileConversionTask::STATUS_FINISH => '已完成',
                        FileConversionTask::STATUS_FAILED => '失败',
                        FileConversionTask::STATUS_CANCELLED => '已取消',
                    ]),
                SelectFilter::make('conversion_engine')
                    ->label('转换引擎')
                    ->options([
                        FileConversionTask::ENGINE_CONVERTIO => 'Convertio',
                        FileConversionTask::ENGINE_CLOUDCONVERT => 'CloudConvert',
                    ]),
                SelectFilter::make('input_method')
                    ->label('输入方式')
                    ->options([
                        FileConversionTask::INPUT_METHOD_URL => 'URL',
                        FileConversionTask::INPUT_METHOD_RAW => '原始数据',
                        FileConversionTask::INPUT_METHOD_BASE64 => 'Base64',
                        FileConversionTask::INPUT_METHOD_UPLOAD => '上传',
                        FileConversionTask::INPUT_METHOD_DIRECT_UPLOAD => '直传',
                    ]),
                Filter::make('created_at')
                    ->form([
                        DatePicker::make('created_from')
                            ->label('创建时间从'),
                        DatePicker::make('created_until')
                            ->label('创建时间到'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn(Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\Action::make('retry')
                    ->label('重试')
                    ->icon('heroicon-o-arrow-path')
                    ->color('warning')
                    // ->visible(fn(FileConversionTask $record): bool => $record->isFailed())
                    ->action(function (FileConversionTask $record): void {
                        // 重新调度转换任务
                        \App\Jobs\ProcessConversionTaskJob::dispatch($record)->onQueue('file-conversion');
                        $record->update(['status' => FileConversionTask::STATUS_WAIT]);
                    })
                    ->successNotificationTitle('重试任务已调度'),

            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\BulkAction::make('retry_failed')
                        ->label('重试失败任务')
                        ->icon('heroicon-o-arrow-path')
                        ->color('warning')
                        ->action(function ($records): void {
                            $records->each(function ($record) {
                                if ($record->isFailed()) {
                                    // 重新调度转换任务
                                    \App\Jobs\ProcessConversionTaskJob::dispatch($record)->onQueue('file-conversion');
                                    $record->update(['status' => FileConversionTask::STATUS_WAIT]);
                                }
                            });
                        })
                        ->successNotificationTitle('失败任务重试已调度'),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListFileConversionTasks::route('/'),
            'view' => Pages\ViewFileConversionTask::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }
}
