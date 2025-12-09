<?php

namespace App\Services;

use App\Models\ChartOfAccount;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

class FinanceReportService
{
    public function generateReport($startDate, $endDate, $companyId): array
    {
        $startDate = $startDate instanceof Carbon ? $startDate : Carbon::parse($startDate);
        $endDate   = $endDate instanceof Carbon ? $endDate : Carbon::parse($endDate);

        $data = [
            'months' => [],
            'summary' => [
                'revenue'     => 0,
                'expense'     => 0,
                'net_profit'  => 0,
            ],
        ];

        $currentMonth = $startDate->copy()->startOfMonth();
        $finalMonth   = $endDate->copy()->startOfMonth();

        while ($currentMonth->lte($finalMonth)) {

            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd   = $currentMonth->copy()->endOfMonth();

            $revenueAccounts = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('type', ChartOfAccount::TYPE_REVENUE)
                ->get();

            $expenseAccounts = ChartOfAccount::query()
                ->where('company_id', $companyId)
                ->where('type', ChartOfAccount::TYPE_EXPENSE)
                ->get();

            $revenue = $revenueAccounts->sum(fn($a) =>
                $a->getValuesForPeriod($monthStart, $monthEnd, 'entrada')
            );

            $expense = $expenseAccounts->sum(fn($a) =>
                $a->getValuesForPeriod($monthStart, $monthEnd, 'saida')
            );

            $net = $revenue - $expense;

            $data['months'][] = [
                'month'      => $currentMonth->translatedFormat('F Y'),
                'revenue'    => $revenue,
                'expense'    => $expense,
                'net_profit' => $net,
            ];

            $data['summary']['revenue']    += $revenue;
            $data['summary']['expense']    += $expense;
            $data['summary']['net_profit'] += $net;

            $currentMonth->addMonthNoOverflow();
        }

        $data['analysis'] = $this->generateSmartAnalysis(
            $data['months'],
            $data['summary']
        );

        return $data;
    }

    public function generateSmartAnalysis(array $months, array $summary): array
    {
        $analysis = [];

        if (count($months) > 1) {
            $first = $months[0];
            $last = end($months);

            $revenueGrowth = $last['revenue'] - $first['revenue'];
            $expenseGrowth = $last['expense'] - $first['expense'];

            $analysis[] = $revenueGrowth >= 0
                ? "A receita cresceu R$ " . number_format($revenueGrowth, 2, ',', '.')
                : "A receita caiu R$ " . number_format(abs($revenueGrowth), 2, ',', '.');

            $analysis[] = $expenseGrowth >= 0
                ? "As despesas aumentaram R$ " . number_format($expenseGrowth, 2, ',', '.')
                : "As despesas diminuíram R$ " . number_format(abs($expenseGrowth), 2, ',', '.');
        }

        $sorted = collect($months)->sortBy('net_profit');

        $worst = $sorted->first();
        $best = $sorted->last();

        $analysis[] = "Melhor mês: {$best['month']} (Lucro R$ " . number_format($best['net_profit'], 2, ',', '.') . ")";
        $analysis[] = "Pior mês: {$worst['month']} (Lucro R$ " . number_format($worst['net_profit'], 2, ',', '.') . ")";

        if ($summary['revenue'] > 0) {
            $margin = ($summary['net_profit'] / $summary['revenue']) * 100;
            $analysis[] = "Margem média de lucro: " . number_format($margin, 2, ',', '.') . "%.";
        }

        return $analysis;
    }
}
