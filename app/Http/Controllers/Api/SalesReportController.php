<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\SalesReportService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SalesReportController extends Controller
{
    protected SalesReportService $reportService;

    public function __construct(SalesReportService $reportService)
    {
        $this->reportService = $reportService;
    }

    public function gerarPdf(Request $request)
    {
        $startDate = $request->query('start') ?? now()->startOfMonth()->format('Y-m-d');
        $endDate = $request->query('end') ?? now()->endOfMonth()->format('Y-m-d');

        $user = Auth::user();

        if (!$user || !$user->company_id) {
            return response()->json(['error' => 'Usuário não possui empresa vinculada.'], 400);
        }

        $companyId = $user->company_id; // Pode ser int ou UUID dependendo do seu banco

        $reportData = $this->reportService->generateReport($startDate, $endDate, $companyId);

        $pdf = \PDF::loadView('reports.sales_performance_pdf', [
            'reportData' => $reportData,
            'generated_at' => now(),
            'startDate' => $startDate,
            'endDate' => $endDate
        ]);

        return $pdf->stream('relatorio_vendas.pdf');
    }
}
