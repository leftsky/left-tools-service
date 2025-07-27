<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ToolResource\Pages;
use App\Models\Tool;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ToolResource extends Resource
{
    protected static ?string $model = Tool::class;

    protected static ?string $navigationIcon = 'heroicon-o-wrench-screwdriver';

    protected static ?string $navigationGroup = '工具管理';

    protected static ?string $navigationLabel = '工具';
    
    protected static ?string $modelLabel = '工具';
    
    protected static ?string $pluralModelLabel = '工具';

    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('基本信息')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('工具名称')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_enabled')
                            ->label('是否启用')
                            ->default(true),
                        Forms\Components\TextInput::make('sort_weight')
                            ->label('排序权重')
                            ->numeric()
                            ->default(0)
                            ->helperText('数值越大排序越靠前'),
                        Forms\Components\TextInput::make('hotness')
                            ->label('热度')
                            ->numeric()
                            ->default(0)
                            ->helperText('工具的热度值'),
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
                Tables\Columns\TextColumn::make('name')
                    ->label('工具名称')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_enabled')
                    ->label('状态')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sort_weight')
                    ->label('排序权重')
                    ->sortable(),
                Tables\Columns\TextColumn::make('hotness')
                    ->label('热度')
                    ->sortable(),
                Tables\Columns\TextColumn::make('today_usage_count')
                    ->label('今日使用次数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('today_user_count')
                    ->label('今日使用人数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_usage_count')
                    ->label('总使用次数')
                    ->sortable(),
                Tables\Columns\TextColumn::make('total_user_count')
                    ->label('总使用人数')
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
                Tables\Filters\TernaryFilter::make('is_enabled')
                    ->label('启用状态'),
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
            ->defaultSort('sort_weight', 'desc');
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
            'index' => Pages\ListTools::route('/'),
            'create' => Pages\CreateTool::route('/create'),
            'edit' => Pages\EditTool::route('/{record}/edit'),
            'view' => Pages\ViewTool::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withCount([
            'usageLogs',
            'favorites',
            'userHistory',
        ]);
    }
} 