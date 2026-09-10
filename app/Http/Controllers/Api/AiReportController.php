<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ProductionReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use PDF;

class AiReportController extends Controller
{
    public function __construct(private readonly ProductionReportService $reportService) {}

    /**
     * Gera e retorna um PDF completo com gráficos base64 embutidos no arquivo.
     */
    public function generatePdf(Request $request)
    {
        $companyId = $request->user()?->company_id;
        if (! $companyId) {
            return response()->json(['error' => 'Usuário não possui empresa vinculada.'], 403);
        }

        try {
            $viewData = $this->reportService->generate($companyId);

            $pdf = PDF::loadView('reports.production_ai_complete', $viewData)->setPaper('a4', 'portrait');

            return $pdf->stream('production_report_complete_'.now()->format('Ymd_His').'.pdf');
        } catch (\Exception $e) {
            Log::error('Erro ao gerar relatório IA: '.$e->getMessage());

            return response()->json(['error' => 'Erro ao gerar relatório: '.$e->getMessage()], 500);
        }
    }
}
