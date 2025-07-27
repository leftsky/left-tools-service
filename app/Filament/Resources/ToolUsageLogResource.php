<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolUsageLogResource\Pages;
use App\Models\ToolUsageLog;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class ToolUsageLogResource extends Resource
{
    protected static ?string $model = ToolUsageLog::class;

    protected static ?string $navigationIcon = 'heroicon-o-clock';

    protected static ?string $navigationGroup = '工具管理';

    protected static ?string $navigationLabel = '使用记录';

    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('使用记录信息')
                    ->schema([
                        Forms\Components\Select::make('tool_id')
                            ->label('工具')
                            ->relationship('tool', 'name')
                            ->required()
                            ->searchable(),
                        Forms\Components\Select::make('user_id')
                            ->label('用户')
                            ->relationship('user', 'name')
                            ->searchable()
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('used_at')
                            ->label('使用时间')
                            ->required()
                            ->default(now()),
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
                Tables\Columns\TextColumn::make('user.name')
                    ->label('用户')
                    ->searchable()
                    ->sortable()
                    ->placeholder('匿名用户'),
                Tables\Columns\TextColumn::make('used_at')
                    ->label('使用时间')
                    ->dateTime()
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('tool_id')
                    ->label('工具')
                    ->relationship('tool', 'name'),
                Tables\Filters\SelectFilter::make('user_id')
                    ->label('用户')
                    ->relationship('user', 'name'),
                Tables\Filters\Filter::make('used_at')
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
                                fn ($query) => $query->whereDate('used_at', '>=', $data['used_from'])
                            )
                            ->when(
                                $data['used_until'],
                                fn ($query) => $query->whereDate('used_at', '<=', $data['used_until'])
                            );
                    })
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ])
            ->defaultSort('used_at', 'desc');
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
            'index' => Pages\ListToolUsageLogs::route('/'),
            'create' => Pages\CreateToolUsageLog::route('/create'),
            'edit' => Pages\EditToolUsageLog::route('/{record}/edit'),
            'view' => Pages\ViewToolUsageLog::route('/{record}'),
        ];
    }
} 