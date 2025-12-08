<?php

namespace App\Services;

use App\Models\TransportOrder;
use Carbon\Carbon;

class TransporteRelatorioService
{
    public function gerarRelatorioCompleto($request)
    {
        // Período filtrado ou padrão (mês atual)
        $inicio = $request->inicio ?? Carbon::now()->startOfMonth();
        $fim    = $request->fim ?? Carbon::now()->endOfMonth();

        // Buscar ordens de transporte completas com todas as relações reais
        $transportes = TransportOrder::with([
            'items',
            'items.client',
            'items.product',
            'vehicle',
            'driver'
        ])
        ->whereBetween('created_at', [$inicio, $fim])
        ->orderBy('created_at', 'asc')
        ->get();

        // Totais baseados na estrutura real da sua tabela
        $totalItens          = 0;
        $totalEntregasFeitas = 0;
        $totalRetornados     = 0;

        foreach ($transportes as $t) {
            foreach ($t->items as $item) {

                // Total de itens
                $totalItens += $item->quantity ?? 0;

                // Status REAL do item (conforme sua model)
                if ($item->status === $item::STATUS_COMPLETED) {
                    $totalEntregasFeitas++;
                }

                if ($item->status === $item::STATUS_RETURNED) {
                    $totalRetornados++;
                }
            }
        }

        return [
            'inicio' => $inicio,
            'fim' => $fim,
            'transportes' => $transportes,
            'totalItens' => $totalItens,
            'totalEntregasFeitas' => $totalEntregasFeitas,
            'totalRetornados' => $totalRetornados,
        ];
    }
}
