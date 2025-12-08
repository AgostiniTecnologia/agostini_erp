<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\DashboardProductionPdfController;
use App\Http\Controllers\FinancialReportPdfController;
use App\Http\Controllers\ProductionOrderPdfController;
use App\Http\Controllers\TimeClockController;
use App\Http\Controllers\VisitWithoutOrderPdfController;
use App\Http\Controllers\PricingTablePdfController;
use App\Http\Controllers\Api\FinanceReportController;
use App\Http\Controllers\Api\SalesReportController;
use App\Http\Controllers\Api\TimeClockReportController;

Route::redirect('/', '/app');

Route::middleware(['auth'])->group(function () {
    Route::get('/production-orders/{uuid}/pdf', [ProductionOrderPdfController::class, 'generatePdf'])
        ->name('production-orders.pdf');

    Route::get('/map-register-point/{actionType}', function (string $actionType) {
        return view('livewire.time-clock.map-register-point', ['actionType' => $actionType]);
    })->name('time-clock.map-register-point');

    Route::post('/time-clock/store', [TimeClockController::class, 'store'])->name('time-clock.store');

    Route::get('/sales-orders/{uuid}/pdf', [\App\Http\Controllers\SalesOrderPdfController::class, 'generatePdf'])
        ->name('sales-orders.pdf');

    Route::get('/transport-orders/{uuid}/pdf', [\App\Http\Controllers\TransportOrderPdfController::class, 'generatePdf'])
        ->name('transport-orders.pdf');

    Route::get('/dp/pdf', [DashboardProductionPdfController::class, 'generatePdf'])
        ->name('production-dashboard.pdf');

    Route::get('/visits-without-order/pdf', VisitWithoutOrderPdfController::class)
        ->name('visits.without.order.pdf');

    Route::get('/financial-report/pdf', FinancialReportPdfController::class)
        ->name('financial.report.pdf');

    Route::get('/pricing-table/pdf', [PricingTablePdfController::class, 'generatePdf'])
        ->name('pricing-table.pdf');

   Route::get('/relatorio/financeiro/pdf', [FinanceReportController::class, 'generatePdf'])
    ->name('finance.pdf');

    Route::get('/relatorio/ponto/pdf', [TimeClockReportController::class, 'gerarPdf'])
        ->name('time.clock.pdf');
             
    Route::get('/relatorio/vendas/pdf', [SalesReportController::class, 'gerarPdf'])->name('sales.performance.pdf');

});
