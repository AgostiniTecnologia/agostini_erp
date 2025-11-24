<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\TaskPauseLog;
use App\Models\PauseReason;

class AiReportDataService
{
    public function buildDataset(): array
    {
        return [
            'dashboard' => $this->dashboardMetrics(),
            'products' => $this->productsMetrics(),
            'pause_reasons' => $this->pauseReasonsRanking(),
            'production_history' => $this->productionHistory(),
        ];
    }

    /* -----------------------------------------------------------
       DASHBOARD
    ----------------------------------------------------------- */
    public function dashboardMetrics(): array
    {
        $totalOrders = ProductionOrder::count();

        $completed = ProductionOrder::whereNotNull("start_date")
            ->whereNotNull("completion_date")
            ->get();

        $avgPerOrder = $completed->count() > 0
            ? $completed->avg(fn($o) => $o->start_date->diffInSeconds($o->completion_date))
            : 0;

        return [
            'total_orders' => $totalOrders,
            'avg_lead_time_seconds' => (int)$avgPerOrder,
        ];
    }

    /* -----------------------------------------------------------
       PRODUTOS — Ranking e tempos médios
    ----------------------------------------------------------- */
    public function productsMetrics(): array
    {
        $products = Product::orderBy('name')->get();
        $out = [];

        foreach ($products as $p) {

            $orders = ProductionOrder::whereHas('items', fn($q) => 
                $q->where('product_uuid', $p->uuid)
            )
            ->whereNotNull('start_date')
            ->whereNotNull('completion_date')
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

                $itemUuids = $o->items->pluck('uuid');

                $dead = TaskPauseLog::join('pause_reasons','pause_reasons.uuid','task_pause_logs.pause_reason_uuid')
                    ->whereIn('task_pause_logs.production_order_item_uuid',$itemUuids)
                    ->where('pause_reasons.type','dead_time')
                    ->sum('duration_seconds');

                $effective = max(0, $lead - $dead);

                $effSum += $effective;
                $deadSum += $dead;
                $count++;
            }

            $out[] = [
                'product' => $p->name,
                'avg_effective_seconds' => (int)($effSum / $count),
                'avg_dead_seconds' => (int)($deadSum / $count),
                'count' => $count,
            ];
        }

        return $out;
    }

    /* -----------------------------------------------------------
       MOTIVOS DE PAUSA — Ranking
    ----------------------------------------------------------- */
    public function pauseReasonsRanking(): array
    {
        return TaskPauseLog::join('pause_reasons','pause_reasons.uuid','task_pause_logs.pause_reason_uuid')
            ->selectRaw("
                pause_reasons.name AS motivo,
                pause_reasons.type AS tipo,
                SUM(task_pause_logs.duration_seconds) AS total_seconds
            ")
            ->groupBy('pause_reasons.name','pause_reasons.type')
            ->orderByDesc('total_seconds')
            ->get()
            ->map(function($i){
                return [
                    'motivo' => $i->motivo,
                    'tipo' => $this->translateType($i->tipo),
                    'total_seconds' => (int)$i->total_seconds,
                ];
            })
            ->toArray();
    }

    private function translateType(string $type): string
    {
        return match($type) {
            'dead_time'       => 'Tempo morto',
            'productive_time' => 'Tempo produtivo',
            'mandatory_break' => 'Pausa obrigatória',
            default           => 'Outro'
        };
    }

    /* -----------------------------------------------------------
       HISTÓRICO DE PRODUÇÃO — lead time + produto + data
    ----------------------------------------------------------- */
    public function productionHistory(): array
    {
        return ProductionOrder::whereNotNull('start_date')
            ->whereNotNull('completion_date')
            ->orderByDesc('completion_date')
            ->limit(30)
            ->get()
            ->map(function($o) {
                return [
                    'date' => $o->completion_date->format('d/m/Y H:i'),
                    'product' => optional($o->items->first())->product->name ?? '—',
                    'lead_time_seconds' => $o->start_date->diffInSeconds($o->completion_date),
                ];
            })
            ->toArray();
    }
}
