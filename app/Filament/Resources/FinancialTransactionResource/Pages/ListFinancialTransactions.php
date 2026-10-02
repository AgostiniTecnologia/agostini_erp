<?php

namespace App\Filament\Resources\FinancialTransactionResource\Pages;

use App\Filament\Resources\FinancialTransactionResource;
use Filament\Actions;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;

class ListFinancialTransactions extends ListRecords
{
    protected static string $resource = FinancialTransactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Actions\CreateAction::make(),
            Actions\Action::make('financial_transactions_report')
                ->label('Visualizar relatório')
                ->icon('heroicon-o-document-chart-bar')
                ->color('gray')
                ->action(function (): void {
                    $period = $this->getTableFilterState('transaction_date') ?? [];
                    $startDate = $period['transaction_date_from'] ?? null;
                    $endDate = $period['transaction_date_until'] ?? null;

                    if (blank($startDate) || blank($endDate)) {
                        Notification::make()
                            ->warning()
                            ->title('Informe o período do relatório')
                            ->body('Preencha os filtros “Lançamento de” e “Lançamento até”.')
                            ->send();

                        return;
                    }

                    if ($endDate < $startDate) {
                        Notification::make()
                            ->danger()
                            ->title('Período inválido')
                            ->body('A data final deve ser igual ou posterior à data inicial.')
                            ->send();

                        return;
                    }

                    $url = route('financial-transactions.report.pdf', [
                        'start_date' => $startDate,
                        'end_date' => $endDate,
                    ]);

                    $this->js("window.open('{$url}', '_blank')");
                }),
        ];
    }
}
