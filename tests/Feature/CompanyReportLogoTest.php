<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyReportLogoTest extends TestCase
{
    public function test_system_footer_contains_the_discreet_erp_signature_and_system_logo(): void
    {
        $html = view('pdf.partials.system_footer')->render();

        $this->assertStringContainsString('Documento gerado pelo ERP Agostini Tecnologia.', $html);
        $this->assertStringContainsString(Company::defaultReportLogoDataUri(), $html);
        $this->assertStringContainsString('font-size: 5px', $html);
    }

    public function test_every_downloadable_pdf_template_includes_the_system_footer(): void
    {
        $templates = [
            'pdf/dashboard_production.blade.php',
            'pdf/financial_report.blade.php',
            'pdf/pricing_table_pdf.blade.php',
            'pdf/product_technical_sheet.blade.php',
            'pdf/production_order.blade.php',
            'pdf/sales_order.blade.php',
            'pdf/transport_order_shipment.blade.php',
            'pdf/visits_without_order.blade.php',
            'reports/finance_pdf.blade.php',
            'reports/production_ai_complete.blade.php',
            'reports/sales_performance_pdf.blade.php',
            'reports/time_clock_pdf.blade.php',
            'reports/transporte_relatorio_pdf.blade.php',
        ];

        foreach ($templates as $template) {
            $contents = file_get_contents(resource_path('views/'.$template));

            $this->assertStringContainsString(
                "@include('pdf.partials.system_footer')",
                $contents,
                "O template {$template} não contém o rodapé padrão.",
            );
        }
    }

    public function test_company_logo_is_embedded_only_for_its_company(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/company-a/logo.png', 'logo-a');
        Storage::disk('public')->put('company-logos/company-b/logo.png', 'logo-b');

        $companyA = new Company([
            'uuid' => 'company-a',
            'logo_path' => 'company-logos/company-a/logo.png',
        ]);
        $companyB = new Company([
            'uuid' => 'company-b',
            'logo_path' => 'company-logos/company-b/logo.png',
        ]);

        $this->assertStringContainsString(base64_encode('logo-a'), $companyA->reportLogoDataUri());
        $this->assertStringNotContainsString(base64_encode('logo-b'), $companyA->reportLogoDataUri());
        $this->assertStringContainsString(base64_encode('logo-b'), $companyB->reportLogoDataUri());
    }

    public function test_default_system_logo_is_used_without_a_valid_company_logo(): void
    {
        Storage::fake('public');
        $company = new Company(['logo_path' => null]);

        $this->assertSame(Company::defaultReportLogoDataUri(), $company->reportLogoDataUri());
    }

    public function test_pdf_logo_partial_renders_the_company_logo(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('company-logos/company-a/logo.png', 'custom-logo');
        $company = new Company([
            'uuid' => 'company-a',
            'name' => 'Company A',
            'logo_path' => 'company-logos/company-a/logo.png',
        ]);

        $html = view('pdf.partials.company_logo', ['company' => $company])->render();

        $this->assertStringContainsString(base64_encode('custom-logo'), $html);
        $this->assertStringNotContainsString(base64_encode(file_get_contents(public_path('images/logo-agostini-full_color-1-horizontal.png'))), $html);
    }
}
