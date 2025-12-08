<?php

namespace App\Http\Controllers\Api;

use App\Services\FinanceReportService;
use Illuminate\Http\Request;
use Pdf;
use App\Http\Controllers\Controller;

class FinanceReportController extends Controller
{
    protected FinanceReportService $service;

    public function __construct(FinanceReportService $service)
    {
        $this->service = $service;
    }

    public function generatePdf(Request $request)
    {
        $request->validate([
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $startDate = $request->input('start_date');
        $endDate = $request->input('end_date');

        $reportData = $this->service->generateReport($startDate, $endDate);

        $pdf = Pdf::loadView('reports.finance_pdf', [
            'reportData' => $reportData,
            'startDate' => $startDate,
            'endDate' => $endDate,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("relatorio_financeiro_{$startDate}_ate_{$endDate}.pdf");
    }
}
