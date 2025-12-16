<?php

namespace App\Http\Controllers\Api;

use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Pdf;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class FinanceReportController extends Controller
{
    protected FinanceReportService $service;

    public function __construct(FinanceReportService $service)
    {
        $this->service = $service;
    }

    public function generatePdf(Request $request)
    {
        // Validações básicas de período
        $request->validate([
            'start_date' => ['required', 'date'],
            'end_date'   => ['required', 'date', 'after_or_equal:start_date'],
        ]);

        // Usuário autenticado + empresa vinculada
        $user = Auth::user();
        if (!$user || !$user->company_id) {
            return response()->json([
                'error' => 'Usuário não possui empresa vinculada.'
            ], 400);
        }

        $companyId = $user->company_id;

        $startDate = $request->input('start_date');
        $endDate   = $request->input('end_date');

        // Enviando companyId para filtrar tudo corretamente
        $reportData = $this->service->generateReport(
            $startDate,
            $endDate,
            $companyId
        );

        // Gera PDF
        $pdf = Pdf::loadView('reports.finance_pdf', [
            'reportData' => $reportData,
            'startDate'  => $startDate,
            'endDate'    => $endDate,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("relatorio_financeiro_{$startDate}_ate_{$endDate}.pdf");
    }
}
