<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ConversionEngineFormatResource\Pages;
use App\Models\ConversionEngineFormat;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ConversionEngineFormatResource extends Resource
{
    protected static ?string $model = ConversionEngineFormat::class;

    protected static ?string $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static ?string $navigationGroup = '文件转换管理';

    protected static ?string $navigationLabel = '转换引擎格式';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = '转换引擎格式';

    protected static ?string $pluralModelLabel = '转换引擎格式';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('格式配置')
                    ->schema([
                        Forms\Components\TextInput::make('input_format')
                            ->label('输入格式')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('例如: jpg, png, pdf')
                            ->helperText('输入文件的格式扩展名'),

                        Forms\Components\TextInput::make('output_format')
                            ->label('输出格式')
                            ->required()
                            ->maxLength(20)
                            ->placeholder('例如: png, jpg, docx')
                            ->helperText('目标输出格式'),

                        Forms\Components\Select::make('default_engine')
                            ->label('默认引擎')
                            ->required()
                            ->options(fn () => ConversionEngineFormat::getSupportedEngines())
                            ->default('convertio')
                            ->helperText('选择用于此格式转换的默认引擎'),
                    ])
                    ->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('id')
                    ->label('ID')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\TextColumn::make('input_format')
                    ->label('输入格式')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('primary'),

                Tables\Columns\TextColumn::make('output_format')
                    ->label('输出格式')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('success'),

                Tables\Columns\TextColumn::make('format_combination')
                    ->label('格式组合')
                    ->getStateUsing(fn (Model $record): string => $record->format_combination)
                    ->searchable(false)
                    ->sortable(false),

                Tables\Columns\TextColumn::make('default_engine')
                    ->label('默认引擎')
                    ->sortable()
                    ->searchable()
                    ->badge()
                    ->color('warning')
                    ->formatStateUsing(fn (string $state): string => ConversionEngineFormat::getSupportedEngines()[$state] ?? $state),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('创建时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->searchable(false),

                Tables\Columns\TextColumn::make('updated_at')
                    ->label('更新时间')
                    ->dateTime('Y-m-d H:i:s')
                    ->sortable()
                    ->searchable(false),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('default_engine')
                    ->label('引擎筛选')
                    ->options(fn () => ConversionEngineFormat::getSupportedEngines()),

                Tables\Filters\Filter::make('input_format')
                    ->form([
                        Forms\Components\TextInput::make('input_format')
                            ->label('输入格式')
                            ->placeholder('输入格式名称'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['input_format'],
                                fn (Builder $query, $inputFormat): Builder => $query->where('input_format', 'like', "%{$inputFormat}%"),
                            );
                    }),

                Tables\Filters\Filter::make('output_format')
                    ->form([
                        Forms\Components\TextInput::make('output_format')
                            ->label('输出格式')
                            ->placeholder('输出格式名称'),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when(
                                $data['output_format'],
                                fn (Builder $query, $outputFormat): Builder => $query->where('output_format', 'like', "%{$outputFormat}%"),
                            );
                    }),
            ])
            ->actions([
                Tables\Actions\EditAction::make()
                    ->icon('heroicon-m-pencil-square')
                    ->color('primary'),

                Tables\Actions\DeleteAction::make()
                    ->icon('heroicon-m-trash')
                    ->color('danger')
                    ->requiresConfirmation(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make()
                        ->requiresConfirmation(),
                ]),
            ])
            ->defaultSort('id', 'desc')
            ->emptyStateHeading('暂无转换引擎格式配置')
            ->emptyStateDescription('创建第一个转换引擎格式配置来开始管理文件转换规则。')
            ->emptyStateActions([
                Tables\Actions\CreateAction::make()
                    ->label('创建配置'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListConversionEngineFormats::route('/'),
            'create' => Pages\CreateConversionEngineFormat::route('/create'),
            'edit' => Pages\EditConversionEngineFormat::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->orderBy('id', 'desc');
    }
}
