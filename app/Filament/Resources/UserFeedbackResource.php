<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserFeedbackResource\Pages;
use App\Models\UserFeedback;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Filament\Infolists\Infolist;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\Section;
use Filament\Support\Enums\FontWeight;

class UserFeedbackResource extends Resource
{
    protected static ?string $model = UserFeedback::class;

    protected static ?string $navigationIcon = 'heroicon-o-chat-bubble-left-right';

    protected static ?string $navigationGroup = '用户管理';

    protected static ?string $navigationLabel = '用户反馈';
    
    protected static ?string $modelLabel = '用户反馈';
    
    protected static ?string $pluralModelLabel = '用户反馈';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('反馈信息')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('用户')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->preload(),
                        Forms\Components\TextInput::make('contact_phone')
                            ->label('联系电话')
                            ->tel()
                            ->maxLength(20),
                        Forms\Components\Select::make('type')
                            ->label('反馈类型')
                            ->options(UserFeedback::getTypeOptions())
                            ->required(),
                        Forms\Components\TextInput::make('title')
                            ->label('反馈标题')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Textarea::make('content')
                            ->label('反馈内容')
                            ->required()
                            ->rows(5)
                            ->columnSpanFull(),
                        Forms\Components\KeyValue::make('attachments')
                            ->label('附件信息')
                            ->keyLabel('文件名')
                            ->valueLabel('文件路径')
                            ->columnSpanFull(),
                        Forms\Components\Select::make('status')
                            ->label('处理状态')
                            ->options(UserFeedback::getStatusOptions())
                            ->default(UserFeedback::STATUS_PENDING)
                            ->required(),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable()
                    ->sortable()
                    ->placeholder('匿名用户'),
                Tables\Columns\TextColumn::make('contact_phone')
                    ->label('联系电话')
                    ->searchable()
                    ->placeholder('-'),
                Tables\Columns\TextColumn::make('type')
                    ->label('反馈类型')
                    ->formatStateUsing(fn (int $state): string => UserFeedback::getTypeOptions()[$state] ?? '未知')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        UserFeedback::TYPE_BUG => 'danger',
                        UserFeedback::TYPE_FEATURE => 'success',
                        UserFeedback::TYPE_IMPROVEMENT => 'warning',
                        UserFeedback::TYPE_OTHER => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('title')
                    ->label('反馈标题')
                    ->searchable()
                    ->limit(30)
                    ->tooltip(function (Tables\Columns\TextColumn $column): ?string {
                        $state = $column->getState();
                        return strlen($state) > 30 ? $state : null;
                    }),
                Tables\Columns\TextColumn::make('status')
                    ->label('状态')
                    ->formatStateUsing(fn (int $state): string => UserFeedback::getStatusOptions()[$state] ?? '未知')
                    ->badge()
                    ->color(fn (int $state): string => match ($state) {
                        UserFeedback::STATUS_PENDING => 'warning',
                        UserFeedback::STATUS_PROCESSING => 'info',
                        UserFeedback::STATUS_RESOLVED => 'success',
                        UserFeedback::STATUS_CLOSED => 'gray',
                        default => 'gray',
                    })
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('提交时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('type')
                    ->label('反馈类型')
                    ->options(UserFeedback::getTypeOptions()),
                Tables\Filters\SelectFilter::make('status')
                    ->label('状态')
                    ->options(UserFeedback::getStatusOptions()),
                Tables\Filters\Filter::make('created_at')
                    ->label('提交时间')
                    ->form([
                        Forms\Components\DatePicker::make('created_from')
                            ->label('开始日期'),
                        Forms\Components\DatePicker::make('created_until')
                            ->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['created_from'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '>=', $date),
                            )
                            ->when(
                                $data['created_until'],
                                fn (Builder $query, $date): Builder => $query->whereDate('created_at', '<=', $date),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('mark_as_processing')
                        ->label('标记为处理中')
                        ->icon('heroicon-o-clock')
                        ->color('info')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['status' => UserFeedback::STATUS_PROCESSING]);
                            });
                        })
                        ->requiresConfirmation(),
                    Tables\Actions\BulkAction::make('mark_as_resolved')
                        ->label('标记为已解决')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function ($records) {
                            $records->each(function ($record) {
                                $record->update(['status' => UserFeedback::STATUS_RESOLVED]);
                            });
                        })
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function infolist(Infolist $infolist): Infolist
    {
        return $infolist
            ->schema([
                Section::make('反馈信息')
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('用户')
                            ->placeholder('匿名用户'),
                        TextEntry::make('contact_phone')
                            ->label('联系电话')
                            ->placeholder('-'),
                        TextEntry::make('type')
                            ->label('反馈类型')
                            ->formatStateUsing(fn (int $state): string => UserFeedback::getTypeOptions()[$state] ?? '未知')
                            ->badge()
                            ->color(fn (int $state): string => match ($state) {
                                UserFeedback::TYPE_BUG => 'danger',
                                UserFeedback::TYPE_FEATURE => 'success',
                                UserFeedback::TYPE_IMPROVEMENT => 'warning',
                                UserFeedback::TYPE_OTHER => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('status')
                            ->label('状态')
                            ->formatStateUsing(fn (int $state): string => UserFeedback::getStatusOptions()[$state] ?? '未知')
                            ->badge()
                            ->color(fn (int $state): string => match ($state) {
                                UserFeedback::STATUS_PENDING => 'warning',
                                UserFeedback::STATUS_PROCESSING => 'info',
                                UserFeedback::STATUS_RESOLVED => 'success',
                                UserFeedback::STATUS_CLOSED => 'gray',
                                default => 'gray',
                            }),
                        TextEntry::make('title')
                            ->label('反馈标题')
                            ->weight(FontWeight::Bold)
                            ->columnSpanFull(),
                        TextEntry::make('content')
                            ->label('反馈内容')
                            ->markdown()
                            ->columnSpanFull(),
                        TextEntry::make('attachments')
                            ->label('附件信息')
                            ->formatStateUsing(function ($state) {
                                if (empty($state)) {
                                    return '无附件';
                                }
                                return collect($state)->map(function ($value, $key) {
                                    return "$key: $value";
                                })->join('\n');
                            })
                            ->columnSpanFull(),
                        TextEntry::make('created_at')
                            ->label('提交时间')
                            ->dateTime('Y-m-d H:i:s'),
                        TextEntry::make('updated_at')
                            ->label('更新时间')
                            ->dateTime('Y-m-d H:i:s'),
                    ])
                    ->columns(2),
            ]);
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
            'index' => Pages\ListUserFeedback::route('/'),
            'view' => Pages\ViewUserFeedback::route('/{record}'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return static::getModel()::where('status', UserFeedback::STATUS_PENDING)->count();
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }
}