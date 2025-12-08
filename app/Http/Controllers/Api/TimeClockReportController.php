<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\TimeClockReportService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class TimeClockReportController extends Controller
{
    protected TimeClockReportService $service;

    public function __construct(TimeClockReportService $service)
    {
        $this->service = $service;
    }

    public function gerarPdf(Request $request)
    {
        $request->validate([
            'inicio' => 'required|date',
            'fim' => 'required|date',
        ]);

        $data = $this->service->gerarRelatorio($request->inicio, $request->fim);

        $pdf = Pdf::loadView('reports.time_clock_pdf', $data)
            ->setPaper('a4', 'portrait');

        return $pdf->download('relatorio-consultor-rh.pdf');
    }
}
