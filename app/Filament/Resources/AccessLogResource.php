<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessLogResource\Pages;
use App\Models\AccessLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class AccessLogResource extends Resource
{
    protected static ?string $model = AccessLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-rectangle-stack';

    protected static ?string $navigationGroup = '访问管理';

    protected static ?string $navigationLabel = '访问记录';

    protected static ?string $modelLabel = '访问记录';

    protected static ?string $pluralModelLabel = '访问记录';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Select::make('user_id')
                    ->relationship('user', 'name')
                    ->label('用户')
                    ->searchable()
                    ->placeholder('匿名用户'),
                Forms\Components\TextInput::make('ip_address')
                    ->label('IP地址')
                    ->required()
                    ->maxLength(45),
                Forms\Components\Textarea::make('user_agent')
                    ->label('用户代理')
                    ->rows(3)
                    ->maxLength(65535),
                Forms\Components\TextInput::make('url')
                    ->label('访问路径')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('referer')
                    ->label('来源页面')
                    ->maxLength(255),
                Forms\Components\TextInput::make('session_id')
                    ->label('会话ID')
                    ->maxLength(255),
                Forms\Components\TextInput::make('browser_fingerprint')
                    ->label('浏览器指纹')
                    ->maxLength(64)
                    ->placeholder('未收集'),
                Forms\Components\Select::make('device_type')
                    ->label('设备类型')
                    ->options([
                        'mobile' => '移动端',
                        'desktop' => '桌面端',
                        'tablet' => '平板端',
                        'unknown' => '未知',
                    ])
                    ->default('unknown'),
                Forms\Components\TextInput::make('screen_resolution')
                    ->label('屏幕分辨率')
                    ->maxLength(20)
                    ->placeholder('未收集'),
                Forms\Components\DateTimePicker::make('created_at')
                    ->label('访问时间')
                    ->required(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('user.name')
                    ->label('用户')
                    ->placeholder('匿名用户')
                    ->searchable(),
                TextColumn::make('ip_address')
                    ->label('IP地址')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('url')
                    ->label('页面类型')
                    ->badge()
                    ->color('primary')
                    ->formatStateUsing(function ($state) {
                        $urlMap = [
                            '/' => '首页',
                            '/video-converter' => '视频转换',
                            'video-converter' => '视频转换',
                            'admin' => '管理后台',
                        ];
                        return $urlMap[$state] ?? $state;
                    }),
                TextColumn::make('referer')
                    ->label('来源页面')
                    ->limit(30)
                    ->placeholder('直接访问'),
                TextColumn::make('browser_fingerprint')
                    ->label('浏览器指纹')
                    ->limit(16)
                    ->placeholder('未收集')
                    ->copyable(),
                TextColumn::make('device_type')
                    ->label('设备类型')
                    ->badge()
                    ->color(function ($state) {
                        return match($state) {
                            'mobile' => 'success',
                            'desktop' => 'info',
                            'tablet' => 'warning',
                            default => 'gray',
                        };
                    })
                    ->formatStateUsing(function ($state) {
                        return match($state) {
                            'mobile' => '移动端',
                            'desktop' => '桌面端',
                            'tablet' => '平板端',
                            default => '未知',
                        };
                    }),
                TextColumn::make('screen_resolution')
                    ->label('屏幕分辨率')
                    ->placeholder('未收集'),
                TextColumn::make('created_at')
                    ->label('访问时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('url')
                    ->label('访问路径')
                    ->options([
                        '/' => '首页',
                        '/video-converter' => '视频转换',
                        'video-converter' => '视频转换',
                        'admin' => '管理后台',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['values'])) {
                            if (in_array('admin', $data['values'])) {
                                $query->where('url', 'like', 'admin%');
                            } else {
                                $query->whereIn('url', $data['values']);
                            }
                        }
                        return $query;
                    }),
                SelectFilter::make('device_type')
                    ->label('设备类型')
                    ->options([
                        'mobile' => '移动端',
                        'desktop' => '桌面端',
                        'tablet' => '平板端',
                        'unknown' => '未知',
                    ]),
                Filter::make('created_at')
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
            ->bulkActions([])
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
            'index' => Pages\ListAccessLogs::route('/'),
            'view' => Pages\ViewAccessLog::route('/{record}'),
        ];
    }
} 