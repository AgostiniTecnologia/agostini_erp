<?php

namespace App\Filament\Resources;

use App\Filament\Resources\RawMaterialResource\Pages;
use App\Models\RawMaterial;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\Tabs;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use Illuminate\Validation\Rule;

class RawMaterialResource extends Resource
{
    protected static ?string $model = RawMaterial::class;

    protected static ?string $navigationIcon = 'heroicon-o-cube';
    protected static ?string $modelLabel = 'Matéria Prima';
    protected static ?string $pluralModelLabel = 'Matéria Prima';
    protected static ?string $navigationGroup = 'Cadastros';
    protected static ?int $navigationSort = 22;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('RawTabs')
                    ->tabs([
                        Tabs\Tab::make('Informações Principais')
                            ->icon('heroicon-o-information-circle')
                            ->schema([
                                Forms\Components\TextInput::make('name')
                                    ->label('Nome')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('sku')
                                    ->label('SKU')
                                    ->maxLength(255)
                                    ->unique(table: RawMaterial::class, column: 'sku', ignoreRecord: true)
                                    ->columnSpan(1),
                                Forms\Components\TextInput::make('stock')
                                    ->label('Estoque')
                                    ->numeric()
                                    ->default(0)
                                    ->columnSpan(1),
                                Forms\Components\Select::make('unit_of_measure')
                                    ->label('Unidade de Medida')
                                    ->options([
                                        'unidade' => 'Unidade (un)',
                                        'peça' => 'Peça (pç)',
                                        'metro' => 'Metro (m)',
                                        'kg' => 'Quilograma (kg)',
                                        'litro' => 'Litro (l)',
                                    ])
                                    ->required()
                                    ->default('unidade')
                                    ->searchable()
                                    ->columnSpan(1),
                                Forms\Components\Textarea::make('description')
                                    ->label('Descrição')
                                    ->columnSpanFull(),
                            ])->columns(2),

                        Tabs\Tab::make('Custos')
                            ->icon('heroicon-o-currency-dollar')
                            ->schema([
                                Forms\Components\TextInput::make('standard_cost')
                                    ->label('Custo Padrão')
                                    ->numeric()
                                    ->prefix('R$')
                                    ->maxValue(42949672.95)
                                    ->default(null)
                                    ->columnSpan(['default' => 2, 'lg' => 1]),
                            ])->columns(['default' => 1, 'lg' => 2]),
                    ])->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Nome')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('sku')
                    ->label('SKU')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('stock')
                    ->label('Estoque')
                    ->sortable()
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: false),
                Tables\Columns\TextColumn::make('unit_of_measure')
                    ->label('Un. Medida')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('standard_cost')
                    ->label('Custo Padrão')
                    ->money('BRL')
                    ->sortable(),
                Tables\Columns\TextColumn::make('created_at')
                    ->label('Criado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('updated_at')
                    ->label('Atualizado em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                Tables\Columns\TextColumn::make('deleted_at')
                    ->label('Excluído em')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
                Tables\Actions\RestoreAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                    Tables\Actions\ForceDeleteBulkAction::make(),
                    Tables\Actions\RestoreBulkAction::make(),
                ]),
            ])
            ->defaultSort('name', 'asc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListRawMaterials::route('/'),
            'create' => Pages\CreateRawMaterial::route('/create'),
            'edit' => Pages\EditRawMaterial::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getGloballySearchableAttributes(): array
    {
        return ['name', 'sku'];
    }
}
