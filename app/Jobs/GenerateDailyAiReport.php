<?php

namespace App\Jobs;

use App\Services\ProductionReportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use PDF;
use Storage;

class GenerateDailyAiReport implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public readonly string $companyId) {}

    public function handle(ProductionReportService $reportService): void
    {
        $viewData = $reportService->generate($this->companyId);
        $pdf = PDF::loadView('reports.production_ai_complete', $viewData)->setPaper('a4', 'portrait');
        $fileName = 'reports/'.$this->companyId.'/production_report_'.now()->format('Ymd_His').'.pdf';
        Storage::disk('local')->put($fileName, $pdf->output());
    }
}
