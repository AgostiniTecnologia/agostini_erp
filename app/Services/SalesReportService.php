<?php

namespace App\Services;

use App\Models\SalesOrder;
use App\Models\SalesVisit;
use App\Models\User;
use Carbon\Carbon;

class SalesReportService
{
    /**
     * Gera o relatório completo de vendas para PDF
     *
     * @param string $startDate
     * @param string $endDate
     * @param string|int $companyId
     * @return array
     */
    public function generateReport(string $startDate, string $endDate, $companyId): array
    {
        $salesVisits = SalesVisit::where('company_id', $companyId)
            ->whereBetween('scheduled_at', [$startDate, $endDate])
            ->with(['client', 'assignedTo', 'salesOrder'])
            ->get();

        $salesOrders = SalesOrder::where('company_id', $companyId)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->with(['client', 'user', 'items.product'])
            ->get();

        $users = User::where('company_id', $companyId)->get();

        $rows = [];
        foreach ($users as $user) {
            $userOrders = $salesOrders->where('user_id', $user->id);
            $userVisits = $salesVisits->where('assigned_to', $user->id);

            $totals = [
                'sales' => $userOrders->sum('total_value'),
                'goal' => $userOrders->sum('goal_value'),
                'performance' => $userOrders->sum('goal_value') ? $userOrders->sum('total_value') / $userOrders->sum('goal_value') * 100 : 0,
                'commission' => $userOrders->sum('commission'),
            ];

            $visitsCompleted = $userVisits->where('status', SalesVisit::STATUS_COMPLETED)->count();
            $conversionRate = $userVisits->count() ? ($visitsCompleted / $userVisits->count()) * 100 : 0;

            // Curva ABC Clientes
            $abcClients = $this->calculateABC($userOrders->groupBy('client_id')->map(function ($orders, $clientId) {
                return [
                    'client' => $orders->first()->client->name ?? 'Sem Cliente',
                    'total' => $orders->sum('total_value'),
                ];
            })->values()->toArray());

            // Curva ABC Produtos
            $abcProducts = $this->calculateABC($userOrders->flatMap(function ($order) {
                return $order->items->map(function ($item) {
                    return [
                        'product' => $item->product->name ?? 'Produto Desconhecido',
                        'total' => $item->total_value,
                    ];
                });
            })->groupBy('product')->map(function ($items, $productName) {
                return [
                    'product' => $productName,
                    'total' => collect($items)->sum('total'),
                ];
            })->values()->toArray());

            // Visitas sem pedido
            $withoutOrder = $userVisits->filter(fn($v) => !$v->salesOrder)->count();
            $withoutOrderDetails = $userVisits->filter(fn($v) => !$v->salesOrder)->map(function ($v) {
                return [
                    'client' => $v->client->name ?? 'Sem Cliente',
                    'date' => Carbon::parse($v->scheduled_at)->format('d/m/Y H:i'),
                    'reason' => $v->reason ?? '-',
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

        $summary = [
            'total_sales' => $salesOrders->sum('total_value'),
            'total_goal' => $salesOrders->sum('goal_value'),
            'achievement_rate' => $salesOrders->sum('goal_value') ? $salesOrders->sum('total_value') / $salesOrders->sum('goal_value') * 100 : 0,
            'total_commission' => $salesOrders->sum('commission'),
            'avg_conversion_rate' => $salesVisits->count() ? ($salesVisits->where('status', SalesVisit::STATUS_COMPLETED)->count() / $salesVisits->count()) * 100 : 0,
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
        usort($items, fn($a, $b) => $b['total'] <=> $a['total']);

        $accumulated = 0;
        foreach ($items as $i => &$item) {
            $percentage = $totalSum ? ($item['total'] / $totalSum) * 100 : 0;
            $accumulated += $percentage;
            $item['accumulated_percentage'] = $accumulated;

            if ($accumulated <= 70) {
                $item['category'] = 'A';
            } elseif ($accumulated <= 90) {
                $item['category'] = 'B';
            } else {
                $item['category'] = 'C';
            }
        }

        return $items;
    }

    private function generateAnalysis(array $rows): string
    {
        $analysis = "";
        foreach ($rows as $row) {
            if ($row['totals']['performance'] < 80) {
                $analysis .= "⚠️ O vendedor {$row['salesperson']} está abaixo da meta ({$row['totals']['performance']}%).\n";
            } elseif ($row['totals']['performance'] >= 100) {
                $analysis .= "✅ O vendedor {$row['salesperson']} atingiu ou superou a meta ({$row['totals']['performance']}%).\n";
            } else {
                $analysis .= "🔹 O vendedor {$row['salesperson']} está próximo da meta ({$row['totals']['performance']}%).\n";
            }

            if ($row['visits']['without_order'] > 0) {
                $analysis .= "⚠️ Possui {$row['visits']['without_order']} visitas sem pedidos.\n";
            }

            $topClients = collect($row['abc_clients'])->where('category', 'A')->pluck('client')->toArray();
            if ($topClients) {
                $analysis .= "⭐ Clientes prioritários (A): " . implode(', ', $topClients) . "\n";
            }

            $analysis .= "\n";
        }

        return $analysis;
    }
}
