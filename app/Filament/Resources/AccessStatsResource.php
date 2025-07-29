<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AccessStatsResource\Pages;
use App\Models\AccessStats;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\Filter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class AccessStatsResource extends Resource
{
    protected static ?string $model = AccessStats::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = '访问管理';

    protected static ?string $navigationLabel = '访问统计';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\DatePicker::make('date')
                    ->label('统计日期')
                    ->required(),
                Forms\Components\TextInput::make('url')
                    ->label('访问路径')
                    ->required()
                    ->maxLength(255),
                Forms\Components\TextInput::make('visit_count')
                    ->label('访问次数')
                    ->required()
                    ->numeric()
                    ->minValue(0),
                Forms\Components\TextInput::make('unique_visitors')
                    ->label('独立访客数')
                    ->required()
                    ->numeric()
                    ->minValue(0),
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
                TextColumn::make('date')
                    ->label('统计日期')
                    ->date('Y-m-d')
                    ->sortable(),
                TextColumn::make('url')
                    ->label('访问路径')
                    ->searchable()
                    ->limit(50),
                TextColumn::make('url')
                    ->label('页面类型')
                    ->badge()
                    ->color('primary'),
                TextColumn::make('visit_count')
                    ->label('访问次数')
                    ->sortable()
                    ->numeric(),
                TextColumn::make('unique_visitors')
                    ->label('独立访客数')
                    ->sortable()
                    ->numeric(),
                TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable(),
            ])
            ->filters([
                Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('开始日期'),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('结束日期'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn (Builder $query, $date): Builder => $query->where('date', '>=', $date),
                            )
                            ->when(
                                $data['date_until'],
                                fn (Builder $query, $date): Builder => $query->where('date', '<=', $date),
                            );
                    }),
                Filter::make('url')
                    ->form([
                        Forms\Components\Select::make('url_type')
                            ->label('页面类型')
                            ->options([
                                '/' => '首页',
                                '/video-converter' => '视频转换',
                                'admin' => '管理后台',
                            ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (isset($data['url_type'])) {
                            if ($data['url_type'] === 'admin') {
                                $query->where('url', 'like', 'admin%');
                            } else {
                                $query->where('url', $data['url_type']);
                            }
                        }
                        return $query;
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('date', 'desc');
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
            'index' => Pages\ListAccessStats::route('/'),
            'view' => Pages\ViewAccessStats::route('/{record}'),
        ];
    }
} 