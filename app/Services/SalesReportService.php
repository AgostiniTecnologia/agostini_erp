<?php

namespace App\Services;

use App\Models\SalesGoal;
use App\Models\SalesOrder;
use App\Models\SalesVisit;
use App\Models\User;
use Carbon\Carbon;

class SalesReportService
{
    /**
     * Gera o relatório completo de vendas para PDF
     *
     * @param  string|int  $companyId
     */
    public function generateReport(string $startDate, string $endDate, $companyId): array
    {
        $periodStart = Carbon::parse($startDate)->startOfDay();
        $periodEnd = Carbon::parse($endDate)->endOfDay();

        $salesVisits = SalesVisit::where('company_id', $companyId)
            ->whereBetween('scheduled_at', [$periodStart, $periodEnd])
            ->with(['client', 'assignedTo', 'salesOrder'])
            ->get();

        $salesOrders = SalesOrder::where('company_id', $companyId)
            ->whereBetween('order_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->where('status', '!=', SalesOrder::STATUS_CANCELLED)
            ->with(['client', 'user', 'items.product'])
            ->get();

        $users = User::where('company_id', $companyId)->get();
        $goals = SalesGoal::where('company_id', $companyId)
            ->whereBetween('period', [
                $periodStart->copy()->startOfMonth()->toDateString(),
                $periodEnd->copy()->startOfMonth()->toDateString(),
            ])
            ->get();

        $rows = [];
        foreach ($users as $user) {
            $userOrders = $salesOrders->where('user_id', $user->getKey());
            $userVisits = $salesVisits->where('assigned_to_user_id', $user->getKey());
            $userGoal = (float) $goals->where('user_id', $user->getKey())->sum('goal_amount');
            $userSales = (float) $userOrders->sum('total_amount');

            $totals = [
                'sales' => $userSales,
                'goal' => $userGoal,
                'performance' => $userGoal > 0 ? $userSales / $userGoal * 100 : 0,
                'commission' => (float) $userOrders->sum('commission_amount'),
            ];

            $visitsCompleted = $userVisits->where('status', SalesVisit::STATUS_COMPLETED)->count();
            $convertedVisits = $userVisits
                ->where('status', SalesVisit::STATUS_COMPLETED)
                ->filter(fn (SalesVisit $visit): bool => $visit->salesOrder !== null
                    && $visit->salesOrder->status !== SalesOrder::STATUS_CANCELLED)
                ->count();
            $conversionRate = $visitsCompleted > 0 ? ($convertedVisits / $visitsCompleted) * 100 : 0;

            // Curva ABC Clientes
            $abcClients = $this->calculateABC($userOrders->groupBy('client_id')->map(function ($orders, $clientId) {
                return [
                    'client' => $orders->first()->client->name ?? 'Sem Cliente',
                    'total' => (float) $orders->sum('total_amount'),
                ];
            })->values()->toArray());

            // Curva ABC Produtos
            $abcProducts = $this->calculateABC($userOrders->flatMap(function ($order) {
                return $order->items->map(function ($item) {
                    return [
                        'product' => $item->product->name ?? 'Produto Desconhecido',
                        'total' => (float) $item->total_price,
                    ];
                });
            })->groupBy('product')->map(function ($items, $productName) {
                return [
                    'product' => $productName,
                    'total' => collect($items)->sum('total'),
                ];
            })->values()->toArray());

            // Visitas sem pedido
            $withoutOrderVisits = $userVisits
                ->where('status', SalesVisit::STATUS_COMPLETED)
                ->filter(fn (SalesVisit $visit): bool => $visit->salesOrder === null);
            $withoutOrder = $withoutOrderVisits->count();
            $withoutOrderDetails = $withoutOrderVisits->map(function ($v) {
                return [
                    'client' => $v->client->name ?? 'Sem Cliente',
                    'date' => Carbon::parse($v->scheduled_at)->format('d/m/Y H:i'),
                    'reason' => $v->report_reason_no_order ?: '-',
                ];
            })->toArray();

            $rows[] = [
                'salesperson' => $user->name,
                'totals' => $totals,
                'visits' => [
                    'completed' => $visitsCompleted,
                    'conversion_rate' => $conversionRate,
                    'without_order' => $withoutOrder,
                    'without_order_details' => $withoutOrderDetails,
                ],
                'abc_clients' => $abcClients,
                'abc_products' => $abcProducts,
            ];
        }

        $totalSales = (float) $salesOrders->sum('total_amount');
        $totalGoal = (float) $goals->sum('goal_amount');
        $completedVisits = $salesVisits->where('status', SalesVisit::STATUS_COMPLETED);
        $convertedVisits = $completedVisits
            ->filter(fn (SalesVisit $visit): bool => $visit->salesOrder !== null
                && $visit->salesOrder->status !== SalesOrder::STATUS_CANCELLED)
            ->count();

        $summary = [
            'total_sales' => $totalSales,
            'total_goal' => $totalGoal,
            'achievement_rate' => $totalGoal > 0 ? $totalSales / $totalGoal * 100 : 0,
            'total_commission' => (float) $salesOrders->sum('commission_amount'),
            'avg_conversion_rate' => $completedVisits->count() > 0 ? ($convertedVisits / $completedVisits->count()) * 100 : 0,
        ];

        $analysis = $this->generateAnalysis($rows);

        return [
            'summary' => $summary,
            'rows' => $rows,
            'analysis' => $analysis,
        ];
    }

    private function calculateABC(array $items): array
    {
        $totalSum = array_sum(array_column($items, 'total'));
        usort($items, fn ($a, $b) => $b['total'] <=> $a['total']);

        $accumulated = 0;
        foreach ($items as $i => &$item) {
            $percentage = $totalSum ? ($item['total'] / $totalSum) * 100 : 0;
            $previousAccumulated = $accumulated;
            $accumulated += $percentage;
            $item['accumulated_percentage'] = $accumulated;

            if ($previousAccumulated < 70) {
                $item['category'] = 'A';
            } elseif ($previousAccumulated < 90) {
                $item['category'] = 'B';
            } else {
                $item['category'] = 'C';
            }
        }

        return $items;
    }

    private function generateAnalysis(array $rows): string
    {
        $analysis = '';
        foreach ($rows as $row) {
            if ($row['totals']['goal'] <= 0) {
                $analysis .= "ℹ️ O vendedor {$row['salesperson']} não possui meta cadastrada para o período.\n";
            } elseif ($row['totals']['performance'] < 80) {
                $analysis .= "⚠️ O vendedor {$row['salesperson']} está abaixo da meta (".number_format($row['totals']['performance'], 1, ',', '.')."%).\n";
            } elseif ($row['totals']['performance'] >= 100) {
                $analysis .= "✅ O vendedor {$row['salesperson']} atingiu ou superou a meta (".number_format($row['totals']['performance'], 1, ',', '.')."%).\n";
            } else {
                $analysis .= "🔹 O vendedor {$row['salesperson']} está próximo da meta (".number_format($row['totals']['performance'], 1, ',', '.')."%).\n";
            }

            if ($row['visits']['without_order'] > 0) {
                $analysis .= "⚠️ Possui {$row['visits']['without_order']} visita(s) concluída(s) sem pedido.\n";
            }

            if ($row['visits']['completed'] > 0) {
                $analysis .= '📈 Conversão das visitas concluídas: '
                    .number_format($row['visits']['conversion_rate'], 1, ',', '.')."%.\n";
            }

            $topClients = collect($row['abc_clients'])->where('category', 'A')->pluck('client')->toArray();
            if ($topClients) {
                $analysis .= '⭐ Clientes prioritários (A): '.implode(', ', $topClients)."\n";
            }

            $analysis .= "\n";
        }

        return $analysis;
    }
}
