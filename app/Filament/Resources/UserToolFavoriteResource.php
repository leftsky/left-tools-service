<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserToolFavoriteResource\Pages;
use App\Models\UserToolFavorite;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class UserToolFavoriteResource extends Resource
{
    protected static ?string $model = UserToolFavorite::class;

    protected static ?string $navigationIcon = 'heroicon-o-heart';

    protected static ?string $navigationGroup = '工具管理';

    protected static ?string $navigationLabel = '用户收藏';
    
    protected static ?string $modelLabel = '用户收藏';
    
    protected static ?string $pluralModelLabel = '用户收藏';

    protected static ?int $navigationSort = 4;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('收藏信息')
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
                        Forms\Components\TextInput::make('weight')
                            ->label('权重')
                            ->numeric()
                            ->default(0)
                            ->helperText('权重越大排序越靠前')
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
                Tables\Columns\TextColumn::make('weight')
                    ->label('权重')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('收藏时间')
                    ->dateTime()
                    ->sortable(),
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
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([])
            ->defaultSort('weight', 'desc');
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
            'index' => Pages\ListUserToolFavorites::route('/'),
            'view' => Pages\ViewUserToolFavorite::route('/{record}'),
        ];
    }
} 