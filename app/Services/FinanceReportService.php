<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Carbon\Carbon;

class FinanceReportService
{
    /**
     * Gera os dados do relatório financeiro.
     *
     * @param string|Carbon $startDate
     * @param string|Carbon $endDate
     * @return array
     */
    public function generateReport($startDate, $endDate): array
    {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        $data = [
            'months' => [],
            'summary' => [
                'revenue' => 0,
                'expense' => 0,
                'net_profit' => 0,
            ],
        ];

        // Cria a lista de meses no período
        $currentMonth = $startDate->copy()->startOfMonth();
        $finalMonth = $endDate->copy()->startOfMonth();

        while ($currentMonth->lte($finalMonth)) {
            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth();

            // Calcula receita, despesa e lucro líquido do mês
            $revenue = ChartOfAccount::query()
                ->where('type', 'revenue')
                ->get()
                ->sum(fn($account) => $account->getValuesForPeriod($monthStart, $monthEnd, 'entrada'));

            $expense = ChartOfAccount::query()
                ->where('type', 'expense')
                ->get()
                ->sum(fn($account) => $account->getValuesForPeriod($monthStart, $monthEnd, 'saida'));

            $net = $revenue - $expense;

            $data['months'][] = [
                'month' => $currentMonth->format('F Y'),
                'revenue' => $revenue,
                'expense' => $expense,
                'net_profit' => $net,
            ];

            // Atualiza o resumo geral
            $data['summary']['revenue'] += $revenue;
            $data['summary']['expense'] += $expense;
            $data['summary']['net_profit'] += $net;

            $currentMonth->addMonthNoOverflow();
        }

        return $data;
    }
}
