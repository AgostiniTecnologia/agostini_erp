<?php

namespace App\Filament\Resources\ProductResource\RelationManagers;

use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Forms\Components\TextInput;
use Filament\Tables\Columns\TextColumn;
use Illuminate\Validation\Rule;

class RawMaterialsRelationManager extends RelationManager
{
    protected static string $relationship = 'rawMaterials';

    protected static ?string $title = 'Matérias-Primas';

    protected static ?string $recordTitleAttribute = 'name';

    // **Não** colocar static aqui
    public function form(Form $form): Form
    {
        return $form
            ->schema([
                TextInput::make('quantity')
                    ->label('Quantidade')
                    ->numeric()
                    ->required()
                    ->helperText('Quantidade desta matéria-prima para este produto.'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nome')->searchable()->sortable(),
                TextColumn::make('quantity')->label('Quantidade')->sortable(),
            ])
            ->headerActions([
                Tables\Actions\AttachAction::make()
                    ->label('Anexar Matéria-Prima')
                    ->form(fn(Tables\Actions\AttachAction $action): array => [
                        $action->getRecordSelect(), // Seleção do registro
                        TextInput::make('quantity')
                            ->label('Quantidade')
                            ->numeric()
                            ->required()
                            ->default(fn(RelationManager $livewire) => 1)
                            ->helperText('Quantidade desta matéria-prima para este produto.'),
                    ])
                    ->preloadRecordSelect()
                    ->modalHeading('Anexar Matéria-Prima'),
            ])
            ->actions([
                Tables\Actions\EditAction::make()->label('Editar Quantidade'),
                Tables\Actions\DetachAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DetachBulkAction::make(),
                ]),
            ]);
    }
}
