<?php

namespace App\Http\Controllers\Api;

use App\Models\TransportOrder;
use App\Models\TransportOrderItem;
use App\Services\TransporteRelatorioService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class TransporteReportController extends Controller
{
    protected $service;

    public function __construct(TransporteRelatorioService $service)
    {
        $this->service = $service;
    }

    public function gerarPdf(Request $request)
    {
        // Busca completa
        $dados = $this->service->gerarRelatorioCompleto($request);

        // Gera PDF
        $pdf = Pdf::loadView('reports.transporte_relatorio_pdf', $dados)
            ->setPaper('a4', 'portrait');

        return $pdf->download('relatorio-transporte.pdf');
    }
}
