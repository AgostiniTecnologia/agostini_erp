<?php

namespace Tests\Feature;

use App\Models\Company;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CompanyReportLogoTest extends TestCase
{
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
}
