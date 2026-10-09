<?php

namespace App\Filament\Resources\ProductionOrderResource\Pages;

use App\Filament\Resources\ProductionOrderResource;
use App\Filament\Resources\SalesOrderResource;
use App\Models\ProductionOrder;
use Filament\Actions;
use Filament\Resources\Pages\EditRecord;

class EditProductionOrder extends EditRecord
{
    protected static string $resource = ProductionOrderResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('viewSalesOrder')
                ->label('Visualizar Pedido de Venda')
                ->icon('heroicon-o-shopping-cart')
                ->color('primary')
                ->url(fn (ProductionOrder $record): ?string => $record->salesOrder
                    ? SalesOrderResource::getUrl('edit', ['record' => $record->salesOrder])
                    : null)
                ->visible(fn (ProductionOrder $record): bool => $record->salesOrder()->exists()),
            Actions\DeleteAction::make(),
        ];
    }
}
