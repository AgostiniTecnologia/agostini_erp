<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Product;
use App\Models\ProductionOrder;
use App\Models\ProductionOrderItem;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ProductionOrderStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_production_statuses_are_updated_by_the_operational_flow(): void
    {
        $order = $this->order();

        $order->startProduction();
        $this->assertSame(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->start_date);

        $order->pauseProduction();
        $this->assertSame(ProductionOrder::STATUS_PAUSED, $order->fresh()->status);

        $order->startProduction();
        $this->assertSame(ProductionOrder::STATUS_IN_PROGRESS, $order->fresh()->status);

        $order->completeProduction();
        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completion_date);
    }

    public function test_automatically_managed_statuses_cannot_be_set_manually(): void
    {
        foreach (ProductionOrder::automaticallyManagedStatuses() as $status) {
            $order = $this->order();

            try {
                $order->update(['status' => $status]);
                $this->fail("O status {$status} foi alterado manualmente.");
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
                $this->assertSame('Pendente', $order->fresh()->status);
            }
        }
    }

    public function test_manual_control_can_complete_an_order_and_sets_progress_to_one_hundred_percent(): void
    {
        $company = Company::factory()->create(['production_order_qr_control' => false]);
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);
        $order = ProductionOrder::factory()->for($company)->create([
            'status' => 'Pendente',
            'start_date' => null,
            'completion_date' => null,
            'user_uuid' => $user->uuid,
        ]);
        $product = Product::factory()->forCompany($company)->create();
        $item = ProductionOrderItem::factory()->create([
            'company_id' => $company->uuid,
            'production_order_uuid' => $order->uuid,
            'product_uuid' => $product->uuid,
            'quantity_planned' => 12,
            'quantity_produced' => 3,
        ]);

        $order->refresh()->update(['status' => ProductionOrder::STATUS_COMPLETED]);

        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completion_date);
        $this->assertSame('12.0000', $item->fresh()->quantity_produced);
    }

    public function test_optional_control_still_accepts_the_qr_operational_flow(): void
    {
        $company = Company::factory()->create(['production_order_qr_control' => false]);
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);
        $order = ProductionOrder::factory()->for($company)->create([
            'status' => 'Pendente',
            'start_date' => null,
            'completion_date' => null,
            'user_uuid' => $user->uuid,
        ]);

        $order->startProduction();
        $order->completeProduction();

        $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->completion_date);
    }

    public function test_completed_order_status_cannot_be_changed_in_either_control_mode(): void
    {
        foreach ([true, false] as $qrControl) {
            $company = Company::factory()->create(['production_order_qr_control' => $qrControl]);
            $user = User::factory()->for($company)->create();
            $this->actingAs($user);
            $order = ProductionOrder::factory()->for($company)->create([
                'status' => ProductionOrder::STATUS_COMPLETED,
                'completion_date' => now(),
                'user_uuid' => $user->uuid,
            ]);

            try {
                $order->update(['status' => 'Cancelada']);
                $this->fail('Uma OP concluída teve o status alterado.');
            } catch (ValidationException $exception) {
                $this->assertArrayHasKey('status', $exception->errors());
                $this->assertSame(ProductionOrder::STATUS_COMPLETED, $order->fresh()->status);
            }
        }
    }

    private function order(): ProductionOrder
    {
        $company = Company::factory()->create();
        $user = User::factory()->for($company)->create();
        $this->actingAs($user);

        return ProductionOrder::factory()->for($company)->create([
            'status' => 'Pendente',
            'start_date' => null,
            'completion_date' => null,
            'user_uuid' => $user->uuid,
        ]);
    }
}
