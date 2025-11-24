<?php

namespace App\Jobs;

use App\Services\AiReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use PDF;
use Storage;

class GenerateDailyAiReport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function handle(AiReportService $aiService)
    {
        // aqui podemos reaproveitar a lógica do controller: montar os dados e pedir PDF via view
        // Exemplo simplificado: chamar o controller poderia ser feito via in-project call,
        // mas para simplicidade, copiamo lógica breve semelhante:

        $products = \App\Models\Product::orderBy('name')->get();
        $reportRows = [];
        foreach ($products as $p) {
            // similar cálculo do controller...
            $reportRows[] = [
                'product_name' => $p->name,
                'avg_effective_seconds' => 0,
                'avg_non_prod_pause_seconds' => 0,
                'completed_count' => 0,
            ];
        }

        $analysisText = $aiService->analyze("Você é um analista...", "Dados: ...");

        $viewData = [
            'generated_at' => now(),
            'rows' => $reportRows,
            'analysis' => $analysisText,
        ];

        $pdf = PDF::loadView('reports.production_ai', $viewData)->setPaper('a4','portrait');
        $fileName = 'reports/production_report_' . now()->format('Ymd_His') . '.pdf';
        Storage::disk('local')->put($fileName, $pdf->output());
        // opcional: enviar email ou notificação com link
    }
}
