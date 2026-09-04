<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PdfPreviewResponseTest extends TestCase
{
    #[DataProvider('interactivePdfControllers')]
    public function test_interactive_pdf_controllers_return_an_inline_preview(string $controller): void
    {
        $contents = file_get_contents($controller);

        $this->assertStringContainsString('->stream(', $contents);
        $this->assertStringNotContainsString('->download(', $contents);
    }

    public static function interactivePdfControllers(): array
    {
        $controllers = [
            'app/Http/Controllers/Api/AiReportController.php',
            'app/Http/Controllers/Api/FinanceReportController.php',
            'app/Http/Controllers/Api/SalesReportController.php',
            'app/Http/Controllers/Api/TimeClockReportController.php',
            'app/Http/Controllers/Api/TransporteReportController.php',
            'app/Http/Controllers/DashboardProductionPdfController.php',
            'app/Http/Controllers/FinancialReportPdfController.php',
            'app/Http/Controllers/PricingTablePdfController.php',
            'app/Http/Controllers/ProductTechnicalSheetPdfController.php',
            'app/Http/Controllers/ProductionOrderPdfController.php',
            'app/Http/Controllers/SalesOrderPdfController.php',
            'app/Http/Controllers/TransportOrderPdfController.php',
            'app/Http/Controllers/VisitWithoutOrderPdfController.php',
        ];

        return array_map(
            fn (string $controller): array => [dirname(__DIR__, 2).'/'.$controller],
            $controllers,
        );
    }
}
