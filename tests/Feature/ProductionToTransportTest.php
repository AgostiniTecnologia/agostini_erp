<?php

namespace Tests\Feature;

use App\Models\Client;
use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\SalesOrder;
use App\Models\SalesOrderItem;
use App\Models\TransportOrder;
use App\Models\User;
use App\Services\ProductionToTransportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionToTransportTest extends TestCase
{
    use RefreshDatabase;

    public function test_sales_approval_records_the_origin_on_the_generated_production_items(): void
    {
        [$company, $user, $client, $product] = $this->salesContext();
        $salesOrder = SalesOrder::query()->create([
            'company_id' => $company->uuid,
            'client_id' => $client->uuid,
            'user_id' => $user->uuid,
            'order_number' => 'PV-TESTE-AUTO',
            'order_date' => now()->toDateString(),
            'status' => SalesOrder::STATUS_PENDING,
        ]);
        $salesOrderItem = SalesOrderItem::query()->create([
            'company_id' => $company->uuid,
            'sales_order_id' => $salesOrder->uuid,
            'product_id' => $product->uuid,
            'quantity' => 3,
            'unit_price' => 10,
            'discount_amount' => 0,
        ]);

        $salesOrder->update(['status' => SalesOrder::STATUS_APPROVED]);

        $this->assertDatabaseHas('production_order_items', [
            'sales_order_item_id' => $salesOrderItem->uuid,
            'product_uuid' => $product->uuid,
            'quantity_planned' => 3,
        ]);
    }

    public function test_completed_production_can_be_selected_and_transferred_to_a_pending_transport_order(): void
    {
        [$productionOrder, $salesOrderItem, $transportOrder] = $this->scenario();
        $service = app(ProductionToTransportService::class);

        $this->assertArrayHasKey($productionOrder->uuid, $service->availableOrderOptions());

        $created = $service->transferTo($transportOrder, [$productionOrder->uuid]);

        $this->assertSame(1, $created);
        $this->assertDatabaseHas('transport_order_items', [
            'transport_order_id' => $transportOrder->uuid,
            'sales_order_item_id' => $salesOrderItem->uuid,
            'quantity' => 2.5,
            'status' => 'pending',
        ]);
        $this->assertArrayNotHasKey($productionOrder->uuid, $service->availableOrderOptions());
    }

    public function test_production_cannot_be_allocated_to_two_active_transport_orders(): void
    {
        [$productionOrder, , $firstTransportOrder] = $this->scenario();
        $service = app(ProductionToTransportService::class);
        $service->transferTo($firstTransportOrder, [$productionOrder->uuid]);

        $secondTransportOrder = TransportOrder::query()->create([
            'company_id' => $firstTransportOrder->company_id,
            'status' => TransportOrder::STATUS_PENDING,
        ]);

        $this->expectException(ValidationException::class);
        $service->transferTo($secondTransportOrder, [$productionOrder->uuid]);
    }

    public function test_cancelling_transport_order_releases_production_for_another_load(): void
    {
        [$productionOrder, , $transportOrder] = $this->scenario();
        $service = app(ProductionToTransportService::class);
        $service->transferTo($transportOrder, [$productionOrder->uuid]);

        $transportOrder->update(['status' => TransportOrder::STATUS_CANCELLED]);

        $this->assertArrayHasKey($productionOrder->uuid, $service->availableOrderOptions());
    }

    public function test_incomplete_production_is_not_available_even_if_status_is_completed(): void
    {
        [$productionOrder] = $this->scenario(producedQuantity: 2.0);

        $this->assertArrayNotHasKey(
            $productionOrder->uuid,
            app(ProductionToTransportService::class)->availableOrderOptions()
        );
    }

    private function scenario(float $producedQuantity = 2.5): array
    {
        [$company, $user, $client, $product] = $this->salesContext();
        $salesOrder = SalesOrder::query()->create([
            'company_id' => $company->uuid,
            'client_id' => $client->uuid,
            'user_id' => $user->uuid,
            'order_number' => 'PV-TESTE-0001',
            'order_date' => now()->toDateString(),
            'status' => SalesOrder::STATUS_APPROVED,
        ]);
        $salesOrderItem = SalesOrderItem::query()->create([
            'company_id' => $company->uuid,
            'sales_order_id' => $salesOrder->uuid,
            'product_id' => $product->uuid,
            'quantity' => 2.5,
            'unit_price' => 10,
            'discount_amount' => 0,
        ]);
        $productionOrder = ProductionOrder::query()->create([
            'company_id' => $company->uuid,
            'order_number' => 'OP-TESTE-0001',
            'status' => ProductionOrder::STATUS_COMPLETED,
            'completion_date' => now(),
            'user_uuid' => $user->uuid,
        ]);
        ProductionOrderItem::query()->create([
            'company_id' => $company->uuid,
            'production_order_uuid' => $productionOrder->uuid,
            'sales_order_item_id' => $salesOrderItem->uuid,
            'product_uuid' => $product->uuid,
            'quantity_planned' => 2.5,
            'quantity_produced' => $producedQuantity,
        ]);
        $transportOrder = TransportOrder::query()->create([
            'company_id' => $company->uuid,
            'status' => TransportOrder::STATUS_PENDING,
        ]);

        return [$productionOrder, $salesOrderItem, $transportOrder];
    }

    private function salesContext(): array
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create(['is_active' => true]);
        $this->actingAs($user);

        $client = Client::query()->create([
            'company_id' => $company->uuid,
            'name' => 'Cliente da produção',
            'social_name' => 'Cliente da produção Ltda.',
            'taxNumber' => '12345678000195',
            'address_street' => 'Rua da Entrega',
            'address_number' => '25',
            'address_city' => 'São Paulo',
            'address_state' => 'SP',
        ]);
        $product = Product::factory()->forCompany($company)->create();

        return [$company, $user, $client, $product];
    }
}
