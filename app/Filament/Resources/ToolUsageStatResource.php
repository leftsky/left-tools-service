<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolUsageStatResource\Pages;
use App\Models\ToolUsageStat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolUsageStatResource extends Resource
{
    protected static ?string $model = ToolUsageStat::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';

    protected static ?string $navigationGroup = '工具管理';

    protected static ?string $navigationLabel = '使用统计';
    
    protected static ?string $modelLabel = '使用统计';
    
    protected static ?string $pluralModelLabel = '使用统计';

    protected static ?int $navigationSort = 3;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('统计信息')
                    ->schema([
                        Forms\Components\Select::make('tool_id')
                            ->label('工具')
                            ->relationship('tool', 'name')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Forms\Components\DatePicker::make('date')
                            ->label('统计日期')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('usage_count')
                            ->label('使用次数')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('user_count')
                            ->label('使用人数')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->disabled(),
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
                Tables\Columns\TextColumn::make('tool.name')
                    ->label('工具名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('date')
                    ->label('统计日期')
                    ->date()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('使用次数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('user_count')
                    ->label('使用人数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tool_id')
                    ->label('工具')
                    ->relationship('tool', 'name'),
                Tables\Filters\Filter::make('date')
                    ->form([
                        Forms\Components\DatePicker::make('date_from')
                            ->label('日期从'),
                        Forms\Components\DatePicker::make('date_until')
                            ->label('日期至'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['date_from'],
                                fn ($query) => $query->whereDate('date', '>=', $data['date_from'])
                            )
                            ->when(
                                $data['date_until'],
                                fn ($query) => $query->whereDate('date', '<=', $data['date_until'])
                            );
                    })
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
            'index' => Pages\ListToolUsageStats::route('/'),
            'view' => Pages\ViewToolUsageStat::route('/{record}'),
        ];
    }
} 