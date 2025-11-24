<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiReportService;
use App\Services\AiReportDataService;
use Illuminate\Http\Request;
use PDF;
use Illuminate\Support\Facades\Log;

class AiReportController extends Controller
{
    protected AiReportService $aiService;
    protected AiReportDataService $dataService;

    public function __construct(AiReportService $aiService, AiReportDataService $dataService)
    {
        $this->aiService = $aiService;
        $this->dataService = $dataService;
    }

    /**
     * Gera e retorna um PDF completo com gráficos base64 embutidos no arquivo.
     */
    public function generatePdf(Request $request)
    {
        try {
            // 1) montar dataset completo (Dashboard + products + pause reasons + history)
            $dataset = $this->dataService->buildDataset();

            // 2) preparar gráficos
            $products = array_map(function($p){
                return [
                    'name' => $p['product'] ?? ($p['product_name'] ?? '---'),
                    'avg' => (int) ($p['avg_effective_seconds'] ?? ($p['avg_effective_seconds'] ?? 0)),
                ];
            }, $dataset['products'] ?? []);

            usort($products, fn($a,$b)=> $b['avg'] <=> $a['avg']);
            $top = array_slice($products, 0, 10);
            $labels = array_map(fn($p) => $p['name'], $top);
            $values = array_map(fn($p) => $p['avg'], $top);
            if (empty($labels)) {
                $labels = ['Sem dados'];
                $values = [0];
            }

            $barBase64 = $this->aiService->makeBarChartBase64($labels, $values);

            // Line chart: use production history lead_time_seconds
            $history = $dataset['production_history'] ?? [];
            $historyLead = array_map(fn($h) => (int)($h['lead_time_seconds'] ?? 0), $history);
            if (empty($historyLead)) { $historyLead = [0,0]; }

            $lineBase64 = $this->aiService->makeLineChartWithRegressionBase64($historyLead);

            // 3) montar prompt para IA com o dataset resumido
            $system = "Você é um analista de produção experiente. Gere um relatório completo, com resumo executivo (3-5 linhas), insights, principais gargalos por etapa, ranking de produtos mais demorado, previsão de conclusão baseada no histórico usando regressão, tempo médio por etapa, etapas mais lentas, ranking dos motivos de pausa, causas prováveis e 3 ações recomendadas. Referencie os gráficos anexados (Bar chart - ranking de produtos; Line chart - timeline + regressão).";

            $shortDataset = [
                'dashboard' => $dataset['dashboard'] ?? [],
                'top_products' => array_slice($products, 0, 10),
                'pause_reasons' => array_slice($dataset['pause_reasons'] ?? [], 0, 10),
            ];

            $userContent = "Dados resumidos:\n" . json_encode($shortDataset, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            $userContent .= "\n\nGráficos embutidos: Bar chart (ranking produtos) e Line chart (histórico + regressão).";

            // 4) pedir análise à OpenAI (se você quiser incluir IA; deixe vazio se não)
            $analysisText = '';
            try {
                $analysisText = $this->aiService->analyze($system, $userContent, ['temperature' => 0.2]);
            } catch (\Throwable $e) {
                // não interrompe a geração do PDF: incluir mensagem de erro no relatório
                Log::error('AI analyze failed: ' . $e->getMessage());
                $analysisText = "Erro ao gerar análise da IA: " . $e->getMessage();
            }

            // 5) preparar view e gerar PDF
            $viewData = [
                'generated_at' => now(),
                'dashboard' => $dataset['dashboard'] ?? [],
                'rows' => $dataset['products'] ?? [],
                'pause_reasons' => $dataset['pause_reasons'] ?? [],
                'history' => $dataset['production_history'] ?? [],
                'analysis' => $analysisText,
                'images' => [
                    'bar' => $barBase64,
                    'line' => $lineBase64,
                ],
            ];

            $pdf = PDF::loadView('reports.production_ai_complete', $viewData)->setPaper('a4','portrait');

            return $pdf->download('production_report_complete_' . now()->format('Ymd_His') . '.pdf');
        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório IA: ' . $e->getMessage());
            return response()->json(['error' => 'Erro ao gerar relatório: ' . $e->getMessage()], 500);
        }
    }
}
