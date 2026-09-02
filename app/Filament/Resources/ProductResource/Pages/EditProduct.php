<?php

namespace App\Filament\Resources\ProductResource\Pages;

use App\Filament\Resources\ProductResource;
use App\Models\Product;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('downloadTechnicalSheet')
                ->label('Baixar ficha técnica')
                ->icon('heroicon-o-document-arrow-down')
                ->color('info')
                ->url(fn (Product $record): string => route('products.technical-sheet.pdf', $record->uuid))
                ->openUrlInNewTab(),
            Actions\DeleteAction::make(),
        ];
    }
}
