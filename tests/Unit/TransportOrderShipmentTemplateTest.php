<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class TransportOrderShipmentTemplateTest extends TestCase
{
    public function test_product_details_are_shown_beside_the_qr_code(): void
    {
        $template = file_get_contents(
            dirname(__DIR__, 2).'/resources/views/pdf/transport_order_shipment.blade.php',
        );

        $this->assertStringContainsString('class="qr-code"', $template);
        $this->assertStringContainsString('class="item-info"', $template);
        $this->assertStringContainsString('Nome do produto', $template);
        $this->assertStringContainsString('Unidade', $template);
        $this->assertStringContainsString('Quantidade', $template);
        $this->assertStringContainsString('Peso Bruto', $template);
        $this->assertStringContainsString('Valor Unitário', $template);
        $this->assertStringContainsString('Valor Total', $template);
        $this->assertStringContainsString('number_format($unitPrice', $template);
        $this->assertStringContainsString('number_format($itemTotal', $template);
        $this->assertStringContainsString('Total da Encomenda', $template);
    }
}
