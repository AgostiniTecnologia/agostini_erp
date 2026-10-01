<?php

namespace Tests\Feature;

use App\Enums\CardboardProductType;
use App\Enums\OperationalProfile;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Tests\TestCase;

class ProductionOrderPdfMeasurementsTest extends TestCase
{
    public function test_cardboard_company_displays_sheet_measurements_in_production_order(): void
    {
        $product = new Product([
            'name' => 'Caixa Modelo A',
            'sku' => 'CX-001',
            'unit_of_measure' => 'unidade',
            'cardboard_measurements' => [
                'internal_length' => '1742',
                'internal_width' => '395',
                'internal_height' => '97',
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

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::CardboardPackaging, $product),
        ])->render();

        $this->assertStringContainsString('Medidas dos Produtos', $html);
        $this->assertStringContainsString('Composição do comprimento da chapa', $html);
        $this->assertStringContainsString('Tamanho da chapa: 2071 × 1019 mm', $html);
        $this->assertStringNotContainsString('Peso líquido', $html);
    }

    public function test_standard_company_displays_existing_product_measurements(): void
    {
        $product = new Product([
            'name' => 'Produto Padrão',
            'sku' => 'PD-001',
            'unit_of_measure' => 'unidade',
        ]);
        $product->weight_net = '1.5';
        $product->weight = '2';
        $product->length = '0.4';
        $product->width = '0.3';
        $product->height = '0.2';

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::Standard, $product),
        ])->render();

        $this->assertStringContainsString('Peso líquido', $html);
        $this->assertStringContainsString('1,5 kg', $html);
        $this->assertStringContainsString('0,4 m', $html);
        $this->assertStringNotContainsString('Composição do comprimento da chapa', $html);
    }

    public function test_cardboard_sheet_displays_length_and_width(): void
    {
        $product = new Product([
            'name' => 'Chapa A',
            'cardboard_product_type' => CardboardProductType::Sheet,
            'cardboard_measurements' => ['simple_sheet_length' => '1200', 'simple_sheet_width' => '800'],
        ]);

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::CardboardPackaging, $product),
        ])->render();

        $this->assertStringContainsString('Tamanho da chapa', $html);
        $this->assertStringContainsString('1200 × 800 mm', $html);
        $this->assertStringNotContainsString('Composição do comprimento da chapa', $html);
    }

    public function test_cardboard_corner_calculates_sheet_width_from_both_heights_and_width(): void
    {
        $product = new Product([
            'name' => 'Cantoneira A',
            'cardboard_product_type' => CardboardProductType::Corner,
            'cardboard_measurements' => [
                'corner_length' => '1000',
                'corner_height_1' => '50',
                'corner_width' => '30',
                'corner_height_2' => '40',
            ],
        ]);

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::CardboardPackaging, $product),
        ])->render();

        $this->assertStringContainsString('Altura 1', $html);
        $this->assertStringContainsString('Largura total', $html);
        $this->assertStringContainsString('Tamanho da chapa: 1000 × 120 mm', $html);
    }

    public function test_standard_measurement_option_uses_agostini_fields_in_cardboard_profile(): void
    {
        $product = new Product([
            'name' => 'Produto padrão',
            'cardboard_product_type' => CardboardProductType::Standard,
        ]);
        $product->length = '10';
        $product->width = '20';
        $product->height = '30';

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::CardboardPackaging, $product),
        ])->render();

        $this->assertStringContainsString('Peso líquido', $html);
        $this->assertStringContainsString('10 mm', $html);
        $this->assertStringNotContainsString('Tamanho da chapa', $html);
    }

    public function test_briefcase_configuration_uses_its_own_measurements_in_production_order(): void
    {
        $product = new Product([
            'name' => 'Maleta A',
            'cardboard_product_type' => CardboardProductType::Briefcase,
            'cardboard_measurements' => ['internal_length' => '999'],
            'briefcase_measurements' => [
                'internal_length' => '100',
                'internal_width' => '40',
                'internal_height' => '20',
                'auxiliary_height' => '30',
                'left_flap' => '60',
                'left_width' => '45',
                'sheet_length' => '105',
                'right_width' => '45',
                'second_length' => '105',
                'top_flap' => '60',
                'height' => '25',
                'width_auxiliary_height' => '35',
                'bottom_flap' => '60',
            ],
        ]);

        $html = view('pdf.production_order', [
            'order' => $this->order(OperationalProfile::CardboardPackaging, $product),
        ])->render();

        $this->assertStringContainsString('Medidas da maleta', $html);
        $this->assertStringContainsString('Tamanho da chapa: 360 × 155 mm', $html);
        $this->assertStringNotContainsString('999 mm', $html);
    }

    public function test_qr_code_generation_expression_remains_present(): void
    {
        $template = file_get_contents(resource_path('views/pdf/production_order.blade.php'));

        $this->assertStringContainsString("\$qrData = \$item->uuid . ':' . \$step->uuid;", $template);
        $this->assertStringContainsString("DNS2D::getBarcodeHTML(\$qrData, 'QRCODE', 1.8, 1.8)", $template);
    }

    private function order(OperationalProfile $profile, Product $product): ProductionOrder
    {
        $company = new Company(['name' => 'Empresa Teste', 'operational_profile' => $profile]);
        $user = new User(['name' => 'Responsável']);
        $item = new ProductionOrderItem([
            'quantity_planned' => 1,
            'notes' => null,
        ]);
        $item->setRelation('product', $product);
        $item->setRelation('productionSteps', new Collection);

        $order = new ProductionOrder([
            'order_number' => 'OP-001',
            'status' => 'Pendente',
        ]);
        $order->created_at = Carbon::parse('2026-08-31 10:00:00');
        $order->setRelation('company', $company);
        $order->setRelation('user', $user);
        $order->setRelation('items', new Collection([$item]));

        return $order;
    }
}
