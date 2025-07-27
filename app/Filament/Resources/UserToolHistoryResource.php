<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserToolHistoryResource\Pages;
use App\Models\UserToolHistory;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserToolHistoryResource extends Resource
{
    protected static ?string $model = UserToolHistory::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = '工具管理';

    protected static ?string $navigationLabel = '使用历史';

    protected static ?int $navigationSort = 5;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('历史记录信息')
                    ->schema([
                        Forms\Components\Select::make('user_id')
                            ->label('用户')
                            ->relationship('user', 'name')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Forms\Components\Select::make('tool_id')
                            ->label('工具')
                            ->relationship('tool', 'name')
                            ->required()
                            ->searchable()
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_used_at')
                            ->label('最后使用时间')
                            ->required()
                            ->disabled(),
                        Forms\Components\TextInput::make('usage_count')
                            ->label('使用次数')
                            ->numeric()
                            ->default(1)
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('tool.name')
                    ->label('工具名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('last_used_at')
                    ->label('最后使用时间')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('usage_count')
                    ->label('使用次数')
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
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('用户')
                    ->relationship('user', 'name'),
                Tables\Filters\SelectFilter::make('tool_id')
                    ->label('工具')
                    ->relationship('tool', 'name'),
                Tables\Filters\Filter::make('last_used_at')
                    ->form([
                        Forms\Components\DatePicker::make('used_from')
                            ->label('使用时间从'),
                        Forms\Components\DatePicker::make('used_until')
                            ->label('使用时间至'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when(
                                $data['used_from'],
                                fn ($query) => $query->whereDate('last_used_at', '>=', $data['used_from'])
                            )
                            ->when(
                                $data['used_until'],
                                fn ($query) => $query->whereDate('last_used_at', '<=', $data['used_until'])
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('last_used_at', 'desc');
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
            'index' => Pages\ListUserToolHistories::route('/'),
            'view' => Pages\ViewUserToolHistory::route('/{record}'),
        ];
    }
} 