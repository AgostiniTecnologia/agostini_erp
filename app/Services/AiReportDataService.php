<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\TaskPauseLog;

class AiReportDataService
{
    public function buildDataset(string $companyId): array
    {
        return [
            'dashboard' => $this->dashboardMetrics($companyId),
            'products' => $this->productsMetrics($companyId),
            'pause_reasons' => $this->pauseReasonsRanking($companyId),
            'production_history' => $this->productionHistory($companyId),
        ];
    }

    /* -----------------------------------------------------------
       DASHBOARD
    ----------------------------------------------------------- */
    public function dashboardMetrics(string $companyId): array
    {
        $totalOrders = ProductionOrder::where('company_id', $companyId)->count();

        $completed = ProductionOrder::where('company_id', $companyId)
            ->whereNotNull('start_date')
            ->whereNotNull('completion_date')
            ->get();

        $avgPerOrder = $completed->count() > 0
            ? $completed->avg(fn ($o) => $o->start_date->diffInSeconds($o->completion_date))
            : 0;

        return [
            'total_orders' => $totalOrders,
            'avg_lead_time_seconds' => (int) $avgPerOrder,
        ];
    }

    /* -----------------------------------------------------------
       PRODUTOS — Ranking e tempos médios
    ----------------------------------------------------------- */
    public function productsMetrics(string $companyId): array
    {
        $products = Product::where('company_id', $companyId)->orderBy('name')->get();
        $out = [];

        foreach ($products as $p) {

            $orders = ProductionOrder::whereHas('items', fn ($q) => $q->where('product_uuid', $p->uuid)
            )
                ->where('company_id', $companyId)
                ->whereNotNull('start_date')
                ->whereNotNull('completion_date')
                ->with('items')
                ->get();

            if ($orders->count() === 0) {
                $out[] = [
                    'product' => $p->name,
                    'avg_effective_seconds' => 0,
                    'avg_dead_seconds' => 0,
                    'count' => 0,
                ];

                continue;
            }

            $effSum = 0;
            $deadSum = 0;
            $count = 0;

            foreach ($orders as $o) {
                $lead = $o->start_date->diffInSeconds($o->completion_date);

                $itemUuids = $o->items->where('product_uuid', $p->uuid)->pluck('uuid');

                $dead = TaskPauseLog::join('pause_reasons', 'pause_reasons.uuid', 'task_pause_logs.pause_reason_uuid')
                    ->whereIn('task_pause_logs.production_order_item_uuid', $itemUuids)
                    ->where('pause_reasons.type', 'dead_time')
                    ->sum('duration_seconds');

                $effective = max(0, $lead - $dead);

                $effSum += $effective;
                $deadSum += $dead;
                $count++;
            }

            $out[] = [
                'product' => $p->name,
                'avg_effective_seconds' => (int) ($effSum / $count),
                'avg_dead_seconds' => (int) ($deadSum / $count),
                'count' => $count,
            ];
        }

        return $out;
    }

    /* -----------------------------------------------------------
       MOTIVOS DE PAUSA — Ranking
    ----------------------------------------------------------- */
    public function pauseReasonsRanking(string $companyId): array
    {
        return TaskPauseLog::join('pause_reasons', 'pause_reasons.uuid', 'task_pause_logs.pause_reason_uuid')
            ->join('production_order_items', 'production_order_items.uuid', 'task_pause_logs.production_order_item_uuid')
            ->where('production_order_items.company_id', $companyId)
            ->whereNull('production_order_items.deleted_at')
            ->selectRaw('
                pause_reasons.name AS motivo,
                pause_reasons.type AS tipo,
                SUM(task_pause_logs.duration_seconds) AS total_seconds
            ')
            ->groupBy('pause_reasons.name', 'pause_reasons.type')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(function ($i) {
                return [
                    'motivo' => $i->motivo,
                    'tipo' => $this->translateType($i->tipo),
                    'total_seconds' => (int) $i->total_seconds,
                ];
            })
            ->toArray();
    }

    private function translateType(string $type): string
    {
        return match ($type) {
            'dead_time' => 'Tempo morto',
            'productive_time' => 'Tempo produtivo',
            'mandatory_break' => 'Pausa obrigatória',
            default => 'Outro'
        };
    }

    /* -----------------------------------------------------------
       HISTÓRICO DE PRODUÇÃO — lead time + produto + data
    ----------------------------------------------------------- */
    public function productionHistory(string $companyId): array
    {
        return ProductionOrder::where('company_id', $companyId)
            ->whereNotNull('start_date')
            ->whereNotNull('completion_date')
            ->orderByDesc('completion_date')
            ->limit(30)
            ->with('items.product')
            ->get()
            ->map(function ($o) {
                return [
                    'date' => $o->completion_date->format('d/m/Y H:i'),
                    'product' => $o->items->first()?->product?->name ?? '—',
                    'lead_time_seconds' => $o->start_date->diffInSeconds($o->completion_date),
                    'status' => $o->status,
                ];
            })
            ->toArray();
    }
}
