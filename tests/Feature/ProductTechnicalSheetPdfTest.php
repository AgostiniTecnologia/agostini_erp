<?php

namespace Tests\Feature;

use App\Enums\CardboardProductType;
use App\Enums\OperationalProfile;
use App\Models\Company;
use App\Models\Permission;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ProductTechnicalSheetPdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_user_can_preview_product_technical_sheet(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
        ]);
        $user = User::factory()->for($company)->create();
        Permission::findOrCreate('view_product', 'web');
        $user->givePermissionTo('view_product');
        $product = Product::factory()->forCompany($company)->create([
            'name' => 'Caixa Modelo A',
            'cardboard_measurements' => [
                'left_flap' => '60',
                'left_height' => '102',
                'sheet_length' => '1747',
                'right_height' => '102',
                'right_flap' => '60',
                'top_flap' => '202.5',
                'top_height' => '107',
                'sheet_width' => '400',
                'bottom_height' => '107',
                'bottom_flap' => '202.5',
            ],
        ]);

        $response = $this->actingAs($user)->get(route('products.technical-sheet.pdf', $product->uuid));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
        $response->assertHeader('content-disposition', 'inline; filename=ficha-tecnica-caixa-modelo-a.pdf');
        $this->assertStringStartsWith('%PDF', $response->getContent());
    }

    public function test_user_cannot_preview_product_from_another_company(): void
    {
        $firstCompany = Company::factory()->create();
        $secondCompany = Company::factory()->create();
        $user = User::factory()->for($secondCompany)->create();
        Permission::findOrCreate('view_product', 'web');
        $user->givePermissionTo('view_product');
        $product = Product::factory()->forCompany($firstCompany)->create();

        $this->actingAs($user)
            ->get(route('products.technical-sheet.pdf', $product->uuid))
            ->assertNotFound();
    }

    public function test_sheet_configuration_adapts_the_technical_sheet(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
            'length_unit' => 'mm',
        ]);
        $product = Product::factory()->forCompany($company)->create([
            'cardboard_product_type' => CardboardProductType::Sheet,
            'cardboard_measurements' => [
                'simple_sheet_length' => '1200',
                'simple_sheet_width' => '800',
            ],
        ]);

        $html = $this->technicalSheetHtml($product->fresh());

        $this->assertStringContainsString('Medidas da chapa', $html);
        $this->assertStringContainsString('Tamanho da chapa: 1200 × 800 mm', $html);
        $this->assertStringNotContainsString('Medidas internas da embalagem', $html);
    }

    public function test_corner_configuration_adapts_the_technical_sheet(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::CardboardPackaging,
            'length_unit' => 'mm',
        ]);
        $product = Product::factory()->forCompany($company)->create([
            'cardboard_product_type' => CardboardProductType::Corner,
            'cardboard_measurements' => [
                'corner_length' => '1000',
                'corner_height_1' => '50',
                'corner_width' => '30',
                'corner_height_2' => '40',
            ],
        ]);

        $html = $this->technicalSheetHtml($product->fresh());

        $this->assertStringContainsString('Medidas da cantoneira', $html);
        $this->assertStringContainsString('Largura total', $html);
        $this->assertStringContainsString('Tamanho da chapa: 1000 × 120 mm', $html);
    }

    public function test_standard_company_always_uses_the_standard_technical_sheet(): void
    {
        $company = Company::factory()->create([
            'operational_profile' => OperationalProfile::Standard,
            'length_unit' => 'm',
            'weight_unit' => 'kg',
        ]);
        $product = Product::factory()->forCompany($company)->create([
            'weight_net' => 1.5,
            'weight' => 2,
            'length' => 3,
            'width' => 4,
            'height' => 5,
        ]);

        // Simula configuração antiga preservada após uma troca de perfil da empresa.
        DB::table('products')->where('uuid', $product->uuid)->update([
            'cardboard_product_type' => CardboardProductType::Corner->value,
            'cardboard_measurements' => json_encode(['corner_length' => '999']),
        ]);

        $html = $this->technicalSheetHtml($product->fresh());

        $this->assertStringContainsString('Medidas e peso', $html);
        $this->assertStringContainsString('Peso líquido', $html);
        $this->assertStringNotContainsString('Medidas da cantoneira', $html);
        $this->assertStringNotContainsString('Tamanho da chapa', $html);
    }

    private function technicalSheetHtml(Product $product): string
    {
        $product->load(['company', 'rawMaterials', 'productionSteps']);

        return view('pdf.product_technical_sheet', [
            'product' => $product,
            'lengthTotal' => \App\Support\CardboardMeasurements::lengthTotal($product->cardboard_measurements ?? []),
            'widthTotal' => \App\Support\CardboardMeasurements::widthTotal($product->cardboard_measurements ?? []),
        ])->render();
    }
}
